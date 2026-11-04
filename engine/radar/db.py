"""Koneksi PostgreSQL."""

from __future__ import annotations

import psycopg
from psycopg.rows import dict_row

from radar.config import Settings


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
        application_name="radar-engine",
        row_factory=dict_row,
        connect_timeout=10,
        autocommit=True,
    )
