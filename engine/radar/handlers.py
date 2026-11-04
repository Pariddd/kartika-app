"""Penanganan per jenis job.

Tahap ini masih stub: alur antrean lengkap (collect -> analyze -> selesai),
tetapi belum mengambil data atau menganalisis. Adapter YouTube dan modul
analisis menggantikan isi handle_collect dan handle_analyze di tahap berikutnya.
"""

from __future__ import annotations

import time
from typing import Any

import psycopg

from radar import queue
from radar.config import Settings
from radar.payload import (
    DEFAULT_ANALYZE_PARAMS,
    SCHEMA_VERSION,
    PayloadError,
    validate_collect_params,
    validate_envelope,
)
from radar.queue import Job

COLLECT_DISABLED = "collect_disabled_in_public_mode"


def handle(conn: psycopg.Connection, job: Job, settings: Settings) -> dict[str, Any]:
    started = time.monotonic()
    payload = validate_envelope(job.payload, job_type=job.job_type, case_id=job.case_id)

    if job.job_type == "collect":
        return handle_collect(conn, job, payload, settings, started)
    if job.job_type == "analyze":
        return handle_analyze(conn, job, payload, started)

    raise PayloadError(f"job_type '{job.job_type}' belum didukung worker")


def _elapsed_ms(started: float) -> int:
    return int((time.monotonic() - started) * 1000)


def handle_collect(
    conn: psycopg.Connection, job: Job, payload: dict[str, Any], settings: Settings, started: float
) -> dict[str, Any]:
    # Pemeriksaan kedua setelah middleware Laravel: server publik tidak pernah mengumpulkan data nyata.
    if not settings.is_local:
        raise PayloadError(COLLECT_DISABLED)

    validate_collect_params(payload["params"])

    summary = {
        "schema_version": SCHEMA_VERSION,
        "posts": 0,
        "accounts": 0,
        "duplicates_skipped": 0,
        "duration_ms": _elapsed_ms(started),
        "warnings": ["stub: pengumpulan data belum diimplementasikan"],
    }

    # Selesaikan collect, buat job analyze, dan ubah status kasus dalam satu transaksi.
    with conn.transaction():
        queue.complete(conn, job, summary)
        queue.enqueue(
            conn,
            case_id=job.case_id,
            job_type="analyze",
            payload={
                "schema_version": SCHEMA_VERSION,
                "job_type": "analyze",
                "case_id": job.case_id,
                "requested_by": payload["requested_by"],
                "params": dict(DEFAULT_ANALYZE_PARAMS),
            },
        )
        queue.set_case_status(conn, job.case_id, "analyzing")

    return summary


def handle_analyze(
    conn: psycopg.Connection, job: Job, payload: dict[str, Any], started: float
) -> dict[str, Any]:
    counts = conn.execute(
        """
        SELECT (SELECT count(*) FROM posts WHERE case_id = %(case)s) AS posts,
               (SELECT count(*) FROM accounts WHERE case_id = %(case)s) AS accounts
        """,
        {"case": job.case_id},
    ).fetchone()

    summary = {
        "schema_version": SCHEMA_VERSION,
        "posts": int(counts["posts"]),
        "accounts": int(counts["accounts"]),
        "clusters": 0,
        "flagged": 0,
        "organic_score": None,
        "duration_ms": _elapsed_ms(started),
        "warnings": ["stub: analisis belum diimplementasikan"],
    }

    with conn.transaction():
        queue.complete(conn, job, summary)
        queue.set_case_status(conn, job.case_id, "done")

    return summary
