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


UPLOAD_DIR = os.getenv("RADAR_TEST_UPLOAD_DIR", "")


def settings(mode: str = "local", worker: str = "uji:1") -> Settings:
    return Settings(
        mode, "", 0, "", "", "", 0.1, worker,
        pseudo_key="kunci-uji-" + "x" * 32, upload_dir=UPLOAD_DIR, youtube_api_key="kunci-youtube-uji",
    )


@pytest.fixture(autouse=True)
def youtube_tiruan(monkeypatch):
    """Tidak ada test yang menghubungi Google. Default: video tanpa komentar."""
    responses = {"items": []}

    def fake_fetch(url):
        return 200, responses

    monkeypatch.setattr("radar.youtube._default_fetch", fake_fetch)
    return responses


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
    assert analyze["status"] == "done", analyze["error"]
    assert analyze["payload"]["params"]["min_signals"] == 2
    assert "tidak ada post untuk dianalisis" in analyze["result_summary"]["warnings"]
    assert case_status(conn, case_id) == "done"

    assert run_once(conn, settings()) is None


def test_demo_sintetis_dianalisis_dan_hasil_tersimpan_sesuai_kontrak(conn, make_case):
    case_id, _ = make_case(params={"platform": "synthetic", "preset": "standar", "seed": 4})

    run_once(conn, settings())  # collect sintetis
    analyze_job = run_once(conn, settings())
    summary = job_row(conn, analyze_job.id)["result_summary"]

    assert job_row(conn, analyze_job.id)["status"] == "done"
    assert summary["flagged"] == 3
    assert 0 < summary["organic_score"] < 1
    assert case_status(conn, case_id) == "done"

    clusters = conn.execute(
        "SELECT * FROM clusters WHERE case_id = %s ORDER BY display_label", (case_id,)
    ).fetchall()
    assert len(clusters) == summary["clusters"]
    assert sum(c["is_flagged"] for c in clusters) == 3
    for cluster in clusters:
        rows = conn.execute("SELECT signal, active FROM cluster_signals WHERE cluster_id = %s", (cluster["id"],)).fetchall()
        assert sorted(r["signal"] for r in rows) == ["infra", "profile", "text", "time"]
        assert sum(r["active"] for r in rows) == cluster["signals_active"]
        assert cluster["is_flagged"] == (cluster["signals_active"] >= 2)

    # Setiap klaster tanaman cocok dengan satu klaster yang ditandai (Jaccard >= 0,5).
    overlap = conn.execute(
        """
        SELECT g.planted_label, max(cnt)::float / min(planted) AS best
        FROM (
            SELECT g.planted_label, c.id, count(*) AS cnt, (SELECT count(*) FROM demo_ground_truth x
                   WHERE x.case_id = g.case_id AND x.planted_label = g.planted_label) AS planted
            FROM demo_ground_truth g
            JOIN cluster_members m ON m.account_id = g.account_id
            JOIN clusters c ON c.id = m.cluster_id AND c.is_flagged
            WHERE g.case_id = %s
            GROUP BY g.planted_label, c.id, g.case_id
        ) g GROUP BY g.planted_label
        """,
        (case_id,),
    ).fetchall()
    assert len(overlap) == 3 and all(r["best"] >= 0.5 for r in overlap)

    # ID dalam bukti teks benar-benar post milik kasus ini.
    evidence = conn.execute(
        """SELECT s.evidence FROM cluster_signals s JOIN clusters c ON c.id = s.cluster_id
           WHERE c.case_id = %s AND s.signal = 'text' AND c.display_label = 'K-01'""",
        (case_id,),
    ).fetchone()["evidence"]
    post_ids = {i for p in evidence["top_pairs"] for i in (p["post_a"], p["post_b"])}
    found = conn.execute("SELECT count(*) AS n FROM posts WHERE case_id = %s AND id = ANY(%s)", (case_id, list(post_ids))).fetchone()["n"]
    assert post_ids and found == len(post_ids)

    score = conn.execute("SELECT * FROM issue_scores WHERE case_id = %s", (case_id,)).fetchone()
    assert float(score["organic_score"]) == pytest.approx(summary["organic_score"])


def test_analisis_ulang_mengganti_hasil_lama(conn, make_case):
    case_id, _ = make_case(params={"platform": "synthetic", "preset": "standar", "seed": 4})
    run_once(conn, settings())
    run_once(conn, settings())
    first = conn.execute("SELECT count(*) AS n FROM clusters WHERE case_id = %s", (case_id,)).fetchone()["n"]

    queue.enqueue(conn, case_id=case_id, job_type="analyze", payload={
        "schema_version": 1, "job_type": "analyze", "case_id": case_id, "requested_by": 1, "params": {},
    })
    run_once(conn, settings())

    assert conn.execute("SELECT count(*) AS n FROM clusters WHERE case_id = %s", (case_id,)).fetchone()["n"] == first
    assert conn.execute("SELECT count(*) AS n FROM issue_scores WHERE case_id = %s", (case_id,)).fetchone()["n"] == 1


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


def test_collect_sintetis_menyimpan_data_terpseudonimisasi_dan_kunci_jawaban(conn, make_case):
    case_id, job_id = make_case(params={"platform": "synthetic", "preset": "standar", "seed": 42})

    run_once(conn, settings(mode="public"))  # data sintetis boleh dibuat di server publik

    job = job_row(conn, job_id)
    assert job["status"] == "done", job["error"]
    summary = job["result_summary"]
    assert summary["posts"] > 1500 and summary["accounts"] > 900
    assert case_status(conn, case_id) == "analyzing"

    # Tidak ada ID asli di database.
    leaked = conn.execute(
        """
        SELECT (SELECT count(*) FROM accounts WHERE case_id = %(c)s AND account_key LIKE 'syn-%%') +
               (SELECT count(*) FROM posts WHERE case_id = %(c)s AND post_key LIKE 'syn-%%') AS n
        """,
        {"c": case_id},
    ).fetchone()["n"]
    assert leaked == 0

    labels = [r["display_label"] for r in conn.execute(
        "SELECT display_label FROM accounts WHERE case_id = %s ORDER BY display_label", (case_id,)
    )]
    assert labels[0] == "Akun-0001" and labels[-1] == f"Akun-{len(labels):04d}"

    truth = conn.execute(
        "SELECT planted_label, count(*) AS n FROM demo_ground_truth WHERE case_id = %s GROUP BY 1 ORDER BY 1",
        (case_id,),
    ).fetchall()
    assert [(r["planted_label"], r["n"]) for r in truth] == [("Tanaman A", 38), ("Tanaman B", 24), ("Tanaman C", 17)]

    # Label tidak membocorkan kunci jawaban: anggota Tanaman A tidak bernomor berurutan.
    numbers = sorted(int(r["display_label"][5:]) for r in conn.execute(
        """SELECT a.display_label FROM demo_ground_truth g JOIN accounts a ON a.id = g.account_id
           WHERE g.case_id = %s AND g.planted_label = 'Tanaman A'""", (case_id,)
    ))
    assert numbers[-1] - numbers[0] > 38 * 3


def test_collect_sintetis_tanpa_pseudo_key_gagal_dengan_pesan_jelas(conn, make_case):
    case_id, job_id = make_case(params={"platform": "synthetic", "preset": "standar", "seed": 1})

    run_once(conn, Settings("local", "", 0, "", "", "", 0.1, "uji:1"))

    job = job_row(conn, job_id)
    assert job["status"] == "failed"
    assert "PSEUDO_KEY" in job["error"]
    assert conn.execute("SELECT count(*) AS n FROM posts WHERE case_id = %s", (case_id,)).fetchone()["n"] == 0


@pytest.mark.skipif(not UPLOAD_DIR, reason="RADAR_TEST_UPLOAD_DIR tidak diisi")
def test_csv_unggahan_diingest_dianalisis_dan_filenya_dihapus(conn, make_case):
    from pathlib import Path

    ref = f"uploads/{uuid.uuid4()}.csv"
    path = Path(UPLOAD_DIR) / ref
    path.parent.mkdir(parents=True, exist_ok=True)
    rows = ["akun,teks,waktu,url"]
    for event in range(4):
        for member in range(6):
            rows.append(f"ops_{member},Relokasi pasar adalah langkah paling masuk akal untuk kota yang tertata,"
                        f"2026-11-01T{8 + event * 3:02d}:0{member}:00+07:00,https://palsu-{event}.example/a")
    for organic in range(40):
        rows.append(f"warga_{organic},Saya warga biasa nomor {organic} dengan pendapat berbeda-beda soal pasar,"
                    f"2026-11-01T{7 + organic % 12:02d}:{(organic * 7) % 60:02d}:00+07:00,")
    path.write_text("\n".join(rows) + "\n", encoding="utf-8")

    case_id, job_id = make_case(
        job_type="analyze_csv",
        params={"file_ref": ref, "delete_after_ingest": True, "use_embedding": False},
    )
    run_once(conn, settings(mode="public"))  # CSV milik pengguna boleh di server publik

    job = job_row(conn, job_id)
    assert job["status"] == "done", job["error"]
    assert job["result_summary"]["posts"] == 64
    assert job["result_summary"]["flagged"] == 1
    assert not path.exists()
    assert case_status(conn, case_id) == "done"
    leaked = conn.execute("SELECT count(*) AS n FROM accounts WHERE case_id = %s AND account_key LIKE 'ops_%%'", (case_id,)).fetchone()["n"]
    assert leaked == 0


@pytest.mark.skipif(not UPLOAD_DIR, reason="RADAR_TEST_UPLOAD_DIR tidak diisi")
def test_csv_tidak_valid_gagal_dengan_pesan_dan_filenya_tetap_dihapus(conn, make_case):
    from pathlib import Path

    ref = f"uploads/{uuid.uuid4()}.csv"
    path = Path(UPLOAD_DIR) / ref
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text("akun,teks,waktu\na,b,kemarin\n", encoding="utf-8")

    case_id, job_id = make_case(job_type="analyze_csv", params={"file_ref": ref, "delete_after_ingest": True})
    run_once(conn, settings())

    job = job_row(conn, job_id)
    assert job["status"] == "failed" and "waktu" in job["error"]
    assert not path.exists()
    assert case_status(conn, case_id) == "failed"


def test_job_running_yang_masih_hidup_tidak_disentuh(conn, make_case):
    _, job_id = make_case()
    conn.execute("UPDATE analysis_jobs SET status = 'running', attempts = 1, locked_at = now() WHERE id = %s", (job_id,))

    assert queue.requeue_stale(conn) == 0
    assert job_row(conn, job_id)["status"] == "running"


def test_collect_youtube_menyimpan_komentar_terpseudonimisasi(conn, make_case, youtube_tiruan):
    def c(cid, author, text, at, parent=None):
        snippet = {
            "authorDisplayName": "@NamaAsliUji",
            "authorChannelUrl": "http://www.youtube.com/@NamaAsliUji",
            "authorChannelId": {"value": author},
            "textDisplay": text,
            "publishedAt": at,
        }
        if parent:
            snippet["parentId"] = parent
        return {"id": cid, "snippet": snippet}

    youtube_tiruan["items"] = [
        {
            "id": "UgxThreadAsli1",
            "snippet": {"topLevelComment": c("UgxThreadAsli1", "UCkanalAsliA", "@NamaAsliUji mantap", "2026-11-01T12:00:00Z"), "totalReplyCount": 1},
            "replies": {"comments": [c("UgxThreadAsli1.r1", "UCkanalAsliB", "setuju", "2026-11-01T12:01:00Z", "UgxThreadAsli1")]},
        },
    ]
    case_id, job_id = make_case()

    run_once(conn, settings())
    job = job_row(conn, job_id)
    assert job["status"] == "done", job["error"]
    assert job["result_summary"]["posts"] == 2
    assert job["result_summary"]["accounts"] == 2
    assert job["result_summary"]["api_requests"] == 1

    accounts = conn.execute("SELECT * FROM accounts WHERE case_id = %s ORDER BY id", (case_id,)).fetchall()
    posts = conn.execute("SELECT * FROM posts WHERE case_id = %s ORDER BY posted_at", (case_id,)).fetchall()
    assert [a["display_label"] for a in accounts] == ["Akun-0001", "Akun-0002"]
    assert posts[1]["reply_to_key"] == posts[0]["post_key"]

    assert all(a["display_name"] is None for a in accounts)  # bawaan: nama tidak disimpan
    dump = repr(accounts) + repr(posts) + repr(job)
    for raw in ("UCkanalAsliA", "UCkanalAsliB", "UgxThreadAsli1", "NamaAsliUji", "kunci-youtube-uji"):
        assert raw not in dump
    assert posts[0]["text"].startswith("@u_")

    assert run_once(conn, settings()).job_type == "analyze"
    assert case_status(conn, case_id) == "done"


def test_collect_youtube_tanpa_api_key_gagal_dengan_pesan_jelas(conn, make_case):
    case_id, job_id = make_case()
    no_key = Settings("local", "", 0, "", "", "", 0.1, "uji:1", pseudo_key="kunci-uji-" + "x" * 32)
    run_once(conn, no_key)
    job = job_row(conn, job_id)
    assert job["status"] == "failed"
    assert "YOUTUBE_API_KEY" in job["error"]
    assert case_status(conn, case_id) == "failed"


def test_collect_youtube_menyimpan_nama_hanya_bila_dinyalakan(conn, make_case, youtube_tiruan):
    youtube_tiruan["items"] = [{
        "id": "UgxThreadAsli9",
        "snippet": {"totalReplyCount": 0, "topLevelComment": {"id": "UgxThreadAsli9", "snippet": {
            "authorDisplayName": "@NamaTampil", "authorChannelUrl": "http://www.youtube.com/@NamaTampil",
            "authorChannelId": {"value": "UCkanalAsliZ"}, "textDisplay": "halo 😂", "publishedAt": "2026-11-01T12:00:00Z",
        }}},
    }]
    case_id, job_id = make_case()
    import dataclasses

    run_once(conn, dataclasses.replace(settings(), store_account_names=True))
    job = job_row(conn, job_id)
    assert job["status"] == "done", job["error"]
    assert job["result_summary"]["names_stored"] is True

    account = conn.execute("SELECT * FROM accounts WHERE case_id = %s", (case_id,)).fetchone()
    assert account["display_name"] == "@NamaTampil"
    assert "UCkanalAsliZ" not in repr(account)
    post = conn.execute("SELECT text FROM posts WHERE case_id = %s", (case_id,)).fetchone()
    assert "😂" in post["text"]  # emoji tersimpan utuh (database UTF-8)
