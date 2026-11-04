"""Test integrasi antrean terhadap PostgreSQL sungguhan.

Dilewati kecuali RADAR_TEST_DSN diisi, misalnya:
    RADAR_TEST_DSN="host=127.0.0.1 dbname=radar_narasi user=radar_web password=..."
Memakai user pemilik skema karena test perlu membuat baris users dan cases.
Semua data uji dihapus setelah test.
"""

from __future__ import annotations

import os
import uuid

import psycopg
import pytest
from psycopg.rows import dict_row
from psycopg.types.json import Jsonb

from radar import queue
from radar.config import Settings
from radar.handlers import COLLECT_DISABLED
from radar.worker import run_once

DSN = os.getenv("RADAR_TEST_DSN")
pytestmark = pytest.mark.skipif(not DSN, reason="RADAR_TEST_DSN tidak diisi")


def settings(mode: str = "local", worker: str = "uji:1") -> Settings:
    return Settings(mode, "", 0, "", "", "", 0.1, worker)


@pytest.fixture
def conn():
    with psycopg.connect(DSN, options="-c TimeZone=UTC", row_factory=dict_row, autocommit=True) as c:
        yield c


@pytest.fixture
def make_case(conn):
    created = {"users": [], "cases": []}

    def _make(params=None, job_type="collect", payload_override=None):
        user_id = conn.execute(
            "INSERT INTO users (name, email, password, role, created_at, updated_at) "
            "VALUES ('Uji', %s, 'x', 'analis', now(), now()) RETURNING id",
            (f"uji-{uuid.uuid4().hex}@radar.local",),
        ).fetchone()["id"]
        created["users"].append(user_id)
        case_id = conn.execute(
            "INSERT INTO cases (name, mode, sources, status, created_by, created_at, updated_at) "
            "VALUES ('Kasus uji', 'live', '[\"youtube\"]', 'collecting', %s, now(), now()) RETURNING id",
            (user_id,),
        ).fetchone()["id"]
        created["cases"].append(case_id)
        payload = {
            "schema_version": 1,
            "job_type": job_type,
            "case_id": case_id,
            "requested_by": user_id,
            "params": params or {"platform": "youtube", "video_ids": ["dQw4w9WgXcQ"], "max_items": 100},
        }
        if payload_override:
            payload.update(payload_override)
        job_id = queue.enqueue(conn, case_id=case_id, job_type=job_type, payload=payload)
        return case_id, job_id

    yield _make

    for case_id in created["cases"]:
        conn.execute("DELETE FROM cases WHERE id = %s", (case_id,))
    for user_id in created["users"]:
        conn.execute("DELETE FROM users WHERE id = %s", (user_id,))


def job_row(conn, job_id):
    return conn.execute("SELECT * FROM analysis_jobs WHERE id = %s", (job_id,)).fetchone()


def case_status(conn, case_id):
    return conn.execute("SELECT status FROM cases WHERE id = %s", (case_id,)).fetchone()["status"]


@pytest.fixture(autouse=True)
def antrean_bersih(conn):
    # Job pending dari data lain akan mengganggu urutan; test ini butuh antrean kosong.
    pending = conn.execute("SELECT count(*) AS n FROM analysis_jobs WHERE status IN ('pending', 'running')").fetchone()["n"]
    if pending:
        pytest.skip("antrean berisi job pending atau running dari data lain")


def test_alur_lengkap_collect_lalu_analyze(conn, make_case):
    case_id, collect_id = make_case()

    assert run_once(conn, settings()).id == collect_id
    collect = job_row(conn, collect_id)
    assert collect["status"] == "done"
    assert collect["attempts"] == 1
    assert collect["result_summary"]["posts"] == 0
    assert case_status(conn, case_id) == "analyzing"

    analyze_job = run_once(conn, settings())
    assert analyze_job.job_type == "analyze"
    analyze = job_row(conn, analyze_job.id)
    assert analyze["status"] == "done"
    assert analyze["payload"]["params"]["min_signals"] == 2
    assert case_status(conn, case_id) == "done"

    assert run_once(conn, settings()) is None


def test_mode_publik_menolak_collect(conn, make_case):
    case_id, job_id = make_case()

    run_once(conn, settings(mode="public"))

    job = job_row(conn, job_id)
    assert job["status"] == "failed"
    assert job["error"] == COLLECT_DISABLED
    assert case_status(conn, case_id) == "failed"
    assert conn.execute("SELECT count(*) AS n FROM analysis_jobs WHERE case_id = %s", (case_id,)).fetchone()["n"] == 1


def test_payload_tidak_valid_ditandai_gagal(conn, make_case):
    case_id, job_id = make_case(params={"platform": "youtube", "video_ids": ["salah"], "max_items": 100})

    run_once(conn, settings())

    job = job_row(conn, job_id)
    assert job["status"] == "failed"
    assert "video_ids" in job["error"]
    assert case_status(conn, case_id) == "failed"


def test_dua_worker_tidak_mengambil_job_yang_sama(conn, make_case):
    _, first = make_case()
    _, second = make_case()

    with psycopg.connect(DSN, options="-c TimeZone=UTC", row_factory=dict_row, autocommit=True) as other:
        # Worker A menahan kunci baris job pertama di dalam transaksi yang belum selesai.
        with conn.transaction():
            conn.execute("SELECT id FROM analysis_jobs WHERE id = %s FOR UPDATE", (first,))
            claimed = queue.claim_next(other, "uji:2")
            assert claimed is not None and claimed.id == second

    assert queue.claim_next(conn, "uji:1").id == first


def test_job_macet_dikembalikan_lalu_gagal_setelah_3_percobaan(conn, make_case):
    case_id, job_id = make_case()
    conn.execute(
        "UPDATE analysis_jobs SET status = 'running', attempts = 1, locked_at = now() - interval '11 minutes' WHERE id = %s",
        (job_id,),
    )
    assert queue.requeue_stale(conn) == 1
    assert job_row(conn, job_id)["status"] == "pending"

    conn.execute(
        "UPDATE analysis_jobs SET status = 'running', attempts = 3, locked_at = now() - interval '11 minutes' WHERE id = %s",
        (job_id,),
    )
    assert queue.requeue_stale(conn) == 1
    assert job_row(conn, job_id)["status"] == "failed"
    assert case_status(conn, case_id) == "failed"


def test_job_running_yang_masih_hidup_tidak_disentuh(conn, make_case):
    _, job_id = make_case()
    conn.execute("UPDATE analysis_jobs SET status = 'running', attempts = 1, locked_at = now() WHERE id = %s", (job_id,))

    assert queue.requeue_stale(conn) == 0
    assert job_row(conn, job_id)["status"] == "running"
