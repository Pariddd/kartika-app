from datetime import timedelta, timezone

import pytest

from radar import csv_input
from radar.errors import ConfigError, PayloadError

REF = "uploads/0b5e4c1a-2f3d-4e5f-8a9b-0c1d2e3f4a5b.csv"


def write(tmp_path, content: str):
    path = tmp_path / "uploads" / REF.split("/")[1]
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")
    return path


def test_baris_valid_dibaca_dengan_zona_waktu(tmp_path):
    path = write(tmp_path, "﻿akun,teks,waktu,url,tanggal_akun\n"
                 "budi,\"Halo, dunia\",2026-11-01T19:02:00+07:00,https://a.example/x,2026-10-01\n"
                 "ani,Tanpa zona,2026-11-01 19:05,,\n"
                 "cici,Zulu,2026-11-01T12:00:00Z,bukan-url,\n")
    posts = csv_input.read(path)

    assert [p.author_id for p in posts] == ["budi", "ani", "cici"]
    assert posts[0].text == "Halo, dunia" and posts[0].urls == ("https://a.example/x",)
    assert posts[0].account_created_at.year == 2026
    assert posts[1].posted_at.utcoffset() == timedelta(hours=7)  # tanpa zona dianggap WIB
    assert posts[2].posted_at.utcoffset() == timedelta(0)
    assert posts[2].urls == ()  # hanya http/https yang dipakai


def test_baris_identik_mendapat_id_yang_sama_untuk_deduplikasi(tmp_path):
    path = write(tmp_path, "akun,teks,waktu\na,sama,2026-11-01T10:00:00+07:00\na,sama,2026-11-01T10:00:00+07:00\n")
    first, second = csv_input.read(path)
    assert first.post_id == second.post_id


@pytest.mark.parametrize(
    ("content", "message"),
    [
        ("", "kosong"),
        ("akun,teks\nx,y\n", "kolom wajib"),
        ("akun,teks,waktu,rahasia\nx,y,2026-11-01T10:00:00+07:00,z\n", "tidak dikenal"),
        ("akun,teks,waktu\n", "tidak ada baris"),
        ("akun,teks,waktu\n,teks,2026-11-01T10:00:00+07:00\n", "baris 2"),
        ("akun,teks,waktu\na,,2026-11-01T10:00:00+07:00\n", "teks"),
        ("akun,teks,waktu\na,b,01/11/2026\n", "waktu"),
        ("akun,teks,waktu,tanggal_akun\na,b,2026-11-01T10:00:00+07:00,2026/10/01\n", "tanggal_akun"),
        ("akun,teks,waktu\na," + "x" * 1001 + ",2026-11-01T10:00:00+07:00\n", "teks"),
    ],
)
def test_isi_tidak_valid_ditolak(tmp_path, content, message):
    with pytest.raises(PayloadError, match=message):
        csv_input.read(write(tmp_path, content))


def test_lebih_dari_2000_baris_ditolak(tmp_path):
    rows = "".join(f"a{i},teks {i},2026-11-01T10:00:00+07:00\n" for i in range(2001))
    with pytest.raises(PayloadError, match="2000"):
        csv_input.read(write(tmp_path, "akun,teks,waktu\n" + rows))


@pytest.mark.parametrize(
    "ref",
    [
        "../../etc/passwd",
        "uploads/../../.env",
        "/etc/passwd",
        "uploads/contoh.csv",
        "uploads/0b5e4c1a-2f3d-4e5f-8a9b-0c1d2e3f4a5b.php",
        "uploads\\0b5e4c1a-2f3d-4e5f-8a9b-0c1d2e3f4a5b.csv",
        None,
        42,
    ],
)
def test_file_ref_selain_uuid_di_folder_unggahan_ditolak(tmp_path, ref):
    with pytest.raises(PayloadError):
        csv_input.resolve(str(tmp_path), ref)


def test_file_ref_valid_berada_di_dalam_folder_unggahan(tmp_path):
    assert csv_input.resolve(str(tmp_path), REF) == (tmp_path / REF).resolve()


def test_folder_unggahan_wajib_dikonfigurasi():
    with pytest.raises(ConfigError, match="RADAR_UPLOAD_DIR"):
        csv_input.resolve("", REF)
