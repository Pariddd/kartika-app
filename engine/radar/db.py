"""Koneksi PostgreSQL."""

from __future__ import annotations

import psycopg
from psycopg.rows import dict_row

from radar.config import Settings
from radar.errors import ConfigError

SETUP_HINT = "buat ulang database dengan ENCODING 'UTF8' (lihat docs/setup-database.sql bagian A)"


def connect(settings: Settings) -> psycopg.Connection:
    """Sesi selalu memakai TimeZone=UTC (docs/kontrak.md bagian 1).

    Tanpa ini, waktu tanpa zona ditafsirkan memakai zona server (WIB di Windows).

    autocommit=True: setiap perubahan dibungkus eksplisit dengan conn.transaction().
    Tanpa autocommit, query biasa membuka transaksi implisit dan blok transaction()
    di dalamnya hanya menjadi savepoint yang tidak pernah di-commit.
    """
    return psycopg.connect(
        host=settings.db_host,
        port=settings.db_port,
        dbname=settings.db_name,
        user=settings.db_user,
        password=settings.db_password,
        options="-c TimeZone=UTC",
        # Teks media sosial penuh emoji dan aksara non-Latin: klien selalu UTF-8,
        # tidak bergantung pada code page Windows (cp1252).
        client_encoding="UTF8",
        application_name="radar-engine",
        row_factory=dict_row,
        connect_timeout=10,
        autocommit=True,
    )


def check_encoding(conn: psycopg.Connection) -> None:
    """Gagal di awal bila database bukan UTF-8.

    PostgreSQL di Windows membuat database dengan encoding WIN1252 bila ENCODING tidak
    disebut. Database seperti itu tidak bisa menyimpan emoji, sehingga job collect
    data nyata gagal di tengah jalan.
    """
    row = conn.execute("SHOW server_encoding").fetchone()
    encoding = (row["server_encoding"] if isinstance(row, dict) else row[0]) if row else ""
    if str(encoding).upper() not in {"UTF8", "UTF-8"}:
        raise ConfigError(f"database memakai encoding {encoding}, bukan UTF8; {SETUP_HINT}")
