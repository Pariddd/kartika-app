"""Penanganan per jenis job.

- collect sintetis: membuat dataset demo beserta kunci jawaban (boleh di mode publik).
- collect YouTube/X: masih stub sampai adapter dibuat (hanya mode lokal).
- analyze: kemiripan teks + ko-aktivitas waktu -> klaster -> empat sinyal (radar.analysis).
"""

from __future__ import annotations

import time
from typing import Any

import psycopg

from radar import queue, synthetic
from radar.analysis import store
from radar.analysis.model import Params
from radar.analysis.pipeline import analyze
from radar.config import Settings
from radar.errors import PayloadError
from radar.ingest import ingest
from radar.payload import DEFAULT_ANALYZE_PARAMS, SCHEMA_VERSION, validate_collect_params, validate_envelope
from radar.pseudo import Pseudonymizer
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


def _finish_collect(conn: psycopg.Connection, job: Job, payload: dict[str, Any], summary: dict[str, Any]) -> None:
    """Dipanggil di dalam transaksi pemanggil: job selesai, job analyze dibuat, status kasus maju."""
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


def handle_collect(
    conn: psycopg.Connection, job: Job, payload: dict[str, Any], settings: Settings, started: float
) -> dict[str, Any]:
    params = validate_collect_params(payload["params"])

    if params["platform"] == "synthetic":
        return collect_synthetic(conn, job, payload, settings, started)

    # Data nyata: pemeriksaan kedua setelah middleware Laravel. Server publik tidak pernah mengumpulkan.
    if not settings.is_local:
        raise PayloadError(COLLECT_DISABLED)

    summary = {
        "schema_version": SCHEMA_VERSION,
        "posts": 0,
        "accounts": 0,
        "duplicates_skipped": 0,
        "duration_ms": _elapsed_ms(started),
        "warnings": [f"stub: adapter {params['platform']} belum diimplementasikan"],
    }
    with conn.transaction():
        _finish_collect(conn, job, payload, summary)
    return summary


def collect_synthetic(
    conn: psycopg.Connection, job: Job, payload: dict[str, Any], settings: Settings, started: float
) -> dict[str, Any]:
    params = payload["params"]
    pseudo = Pseudonymizer(settings.pseudo_key)
    dataset = synthetic.generate(params["preset"], params["seed"])

    # Satu transaksi: data, kunci jawaban, dan status job tersimpan bersama atau tidak sama sekali.
    with conn.transaction():
        result = ingest(conn, job.case_id, dataset.posts, pseudo)

        answer_rows = [
            (job.case_id, label, result.account_ids[author])
            for label, members in dataset.planted.items()
            for author in members
            if author in result.account_ids
        ]
        if answer_rows:
            with conn.cursor() as cur:
                cur.executemany(
                    """
                    INSERT INTO demo_ground_truth (case_id, planted_label, account_id)
                    VALUES (%s, %s, %s)
                    ON CONFLICT (case_id, account_id) DO NOTHING
                    """,
                    answer_rows,
                )

        summary = {
            "schema_version": SCHEMA_VERSION,
            "posts": result.posts_inserted,
            "accounts": result.accounts_inserted,
            "duplicates_skipped": result.duplicates_skipped,
            "duration_ms": _elapsed_ms(started),
            "warnings": [],
        }
        _finish_collect(conn, job, payload, summary)

    return summary


def handle_analyze(
    conn: psycopg.Connection, job: Job, payload: dict[str, Any], started: float
) -> dict[str, Any]:
    params = Params.from_payload(payload["params"])
    posts, accounts = store.load_case(conn, job.case_id)
    result = analyze(posts, accounts, params)

    summary = {
        "schema_version": SCHEMA_VERSION,
        "posts": result.total_posts,
        "accounts": result.total_accounts,
        "clusters": len(result.clusters),
        "flagged": len(result.flagged),
        "organic_score": round(result.organic_score, 3),
        "duration_ms": _elapsed_ms(started),
        "warnings": result.warnings,
    }

    with conn.transaction():
        store.save(conn, job.case_id, job.id, result)
        queue.complete(conn, job, summary)
        queue.set_case_status(conn, job.case_id, "done")

    return summary
