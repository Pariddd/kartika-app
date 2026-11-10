"""Worker antrean Radar Narasi.

Jalankan dari folder engine:
    python -m radar.worker          (terus berjalan, Ctrl+C untuk berhenti)
    python -m radar.worker --once   (proses paling banyak satu job lalu keluar)
"""

from __future__ import annotations

import argparse
import logging
import signal
import threading
import time
from typing import Callable

import psycopg

from radar import queue
from radar.config import Settings, load_settings
from radar.db import check_encoding, connect
from radar.handlers import handle
from radar.errors import ConfigError, JobError
from radar.queue import Job

log = logging.getLogger("radar.worker")

HEARTBEAT_SECONDS = 60
STALE_CHECK_SECONDS = 60


class Heartbeat:
    """Memperbarui locked_at tiap 60 detik selama job berjalan, lewat koneksi terpisah
    (koneksi psycopg tidak boleh dipakai bersama antar-thread)."""

    def __init__(self, connect_fn: Callable[[], psycopg.Connection], job: Job, worker_id: str) -> None:
        self._connect_fn = connect_fn
        self._job = job
        self._worker_id = worker_id
        self._stop = threading.Event()
        self._thread = threading.Thread(target=self._run, name=f"heartbeat-{job.id}", daemon=True)

    def _run(self) -> None:
        try:
            with self._connect_fn() as conn:
                while not self._stop.wait(HEARTBEAT_SECONDS):
                    queue.heartbeat(conn, self._job, self._worker_id)
        except Exception:  # noqa: BLE001 - heartbeat gagal tidak boleh menghentikan job
            log.exception("heartbeat job %s gagal", self._job.id)

    def __enter__(self) -> "Heartbeat":
        self._thread.start()
        return self

    def __exit__(self, *exc: object) -> None:
        self._stop.set()
        self._thread.join(timeout=5)


def process(
    conn: psycopg.Connection,
    job: Job,
    settings: Settings,
    connect_fn: Callable[[], psycopg.Connection] | None = None,
) -> None:
    log.info("job %s (%s, kasus %s) diambil, percobaan ke-%s", job.id, job.job_type, job.case_id, job.attempts)

    try:
        if connect_fn is None:
            summary = handle(conn, job, settings)
        else:
            with Heartbeat(connect_fn, job, settings.worker_id):
                summary = handle(conn, job, settings)
    except JobError as exc:
        log.warning("job %s gagal: %s", job.id, exc)
        queue.fail(conn, job, str(exc))
        return
    except psycopg.OperationalError:
        # Koneksi putus: job tetap "running" dan akan dikembalikan ke antrean oleh requeue_stale.
        raise
    except Exception:  # noqa: BLE001
        log.exception("job %s gagal karena kesalahan internal", job.id)
        queue.fail(conn, job, "kesalahan internal worker; lihat log worker")
        return

    log.info("job %s selesai dalam %s ms", job.id, summary.get("duration_ms"))


def run_once(
    conn: psycopg.Connection,
    settings: Settings,
    connect_fn: Callable[[], psycopg.Connection] | None = None,
) -> Job | None:
    job = queue.claim_next(conn, settings.worker_id)
    if job is not None:
        process(conn, job, settings, connect_fn)
    return job


def run(settings: Settings, *, once: bool = False) -> None:
    stopping = threading.Event()

    def request_stop(signum: int, frame: object) -> None:
        if stopping.is_set():
            raise KeyboardInterrupt
        log.info("berhenti setelah job yang sedang berjalan selesai (Ctrl+C lagi untuk paksa)")
        stopping.set()

    signal.signal(signal.SIGINT, request_stop)

    log.info("worker %s mulai, mode %s", settings.worker_id, settings.app_mode)
    connect_fn = lambda: connect(settings)  # noqa: E731
    last_stale_check = 0.0


    while not stopping.is_set():
        try:
            with connect_fn() as conn:
                check_encoding(conn)
                while not stopping.is_set():
                    if time.monotonic() - last_stale_check >= STALE_CHECK_SECONDS:
                        requeued = queue.requeue_stale(conn)
                        if requeued:
                            log.warning("%s job macet dikembalikan ke antrean atau ditandai gagal", requeued)
                        last_stale_check = time.monotonic()

                    job = run_once(conn, settings, connect_fn)
                    if once:
                        if job is None:
                            log.info("tidak ada job pending")
                        return
                    if job is None:
                        stopping.wait(settings.poll_seconds)
        except ConfigError as exc:
            log.error("worker dihentikan: %s", exc)
            raise SystemExit(1) from exc
        except psycopg.OperationalError as exc:
            log.error("koneksi database gagal: %s. Mencoba lagi dalam 10 detik.", exc)
            if once or stopping.wait(10):
                return

    log.info("worker berhenti")


def main() -> None:
    parser = argparse.ArgumentParser(description="Worker antrean Radar Narasi")
    parser.add_argument("--once", action="store_true", help="proses paling banyak satu job lalu keluar")
    args = parser.parse_args()

    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s: %(message)s")
    run(load_settings(), once=args.once)


if __name__ == "__main__":
    main()
