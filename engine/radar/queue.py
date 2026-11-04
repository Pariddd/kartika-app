"""Antrean analysis_jobs (docs/kontrak.md bagian 3).

Setiap fungsi menjalankan satu transaksi pendek dan langsung commit, supaya
baris job tidak terkunci selama pemrosesan yang lama.
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Any

import psycopg
from psycopg.types.json import Jsonb

MAX_ATTEMPTS = 3
STALE_AFTER_MINUTES = 10


@dataclass(frozen=True)
class Job:
    id: int
    case_id: int
    job_type: str
    payload: Any
    attempts: int


def claim_next(conn: psycopg.Connection, worker_id: str) -> Job | None:
    """Ambil satu job pending tertua. SKIP LOCKED: dua worker tidak pernah mendapat job yang sama."""
    with conn.transaction():
        row = conn.execute(
            """
            WITH next AS (
                SELECT id FROM analysis_jobs
                WHERE status = 'pending'
                ORDER BY created_at, id
                FOR UPDATE SKIP LOCKED
                LIMIT 1
            )
            UPDATE analysis_jobs AS j
            SET status = 'running', locked_at = now(), worker_id = %s,
                attempts = j.attempts + 1, error = NULL, updated_at = now()
            FROM next
            WHERE j.id = next.id
            RETURNING j.id, j.case_id, j.job_type, j.payload, j.attempts
            """,
            (worker_id,),
        ).fetchone()

    return Job(**row) if row else None


def heartbeat(conn: psycopg.Connection, job: Job, worker_id: str) -> None:
    with conn.transaction():
        conn.execute(
            "UPDATE analysis_jobs SET locked_at = now() WHERE id = %s AND worker_id = %s AND status = 'running'",
            (job.id, worker_id),
        )


def complete(conn: psycopg.Connection, job: Job, summary: dict[str, Any]) -> None:
    with conn.transaction():
        conn.execute(
            """
            UPDATE analysis_jobs
            SET status = 'done', result_summary = %s, locked_at = NULL, updated_at = now()
            WHERE id = %s
            """,
            (Jsonb(summary), job.id),
        )


def fail(conn: psycopg.Connection, job: Job, error: str) -> None:
    """Job gagal permanen dan kasusnya ditandai gagal. Pesan error tidak boleh memuat data pribadi."""
    with conn.transaction():
        conn.execute(
            """
            UPDATE analysis_jobs
            SET status = 'failed', error = %s, locked_at = NULL, updated_at = now()
            WHERE id = %s
            """,
            (error[:500], job.id),
        )
        set_case_status(conn, job.case_id, "failed")


def set_case_status(conn: psycopg.Connection, case_id: int, status: str) -> None:
    """Satu-satunya kolom cases yang boleh ditulis Python (docs/kontrak.md, tabel cases)."""
    conn.execute(
        "UPDATE cases SET status = %s, updated_at = now() WHERE id = %s",
        (status, case_id),
    )


def enqueue(conn: psycopg.Connection, *, case_id: int, job_type: str, payload: dict[str, Any]) -> int:
    row = conn.execute(
        """
        INSERT INTO analysis_jobs (case_id, job_type, status, payload, attempts, created_at, updated_at)
        VALUES (%s, %s, 'pending', %s, 0, now(), now())
        RETURNING id
        """,
        (case_id, job_type, Jsonb(payload)),
    ).fetchone()
    return int(row["id"])


def requeue_stale(conn: psycopg.Connection) -> int:
    """Job running yang heartbeat-nya berhenti lebih dari 10 menit dikembalikan ke pending,
    atau ditandai gagal setelah 3 percobaan."""
    with conn.transaction():
        rows = conn.execute(
            f"""
            UPDATE analysis_jobs
            SET status = CASE WHEN attempts >= {MAX_ATTEMPTS} THEN 'failed' ELSE 'pending' END,
                error = CASE WHEN attempts >= {MAX_ATTEMPTS} THEN 'macet setelah {MAX_ATTEMPTS} percobaan' ELSE error END,
                locked_at = NULL, worker_id = NULL, updated_at = now()
            WHERE status = 'running' AND locked_at < now() - interval '{STALE_AFTER_MINUTES} minutes'
            RETURNING id, case_id, status
            """
        ).fetchall()
        for row in rows:
            if row["status"] == "failed":
                set_case_status(conn, row["case_id"], "failed")
    return len(rows)
