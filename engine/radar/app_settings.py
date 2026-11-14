"""Pengaturan yang diubah admin dari aplikasi web (tabel app_settings). Worker hanya membaca.

pseudonym_mode: bila aktif, nama akun dan teks dengan @username asli tidak disimpan sama sekali.
Fail closed: tabel belum dimigrasi, baris tidak ada, atau nilai rusak berarti pseudonim AKTIF.
"""

from __future__ import annotations

import psycopg

PSEUDONYM_KEY = "pseudonym_mode"


def pseudonym_active(conn: psycopg.Connection) -> bool:
    exists = conn.execute("SELECT to_regclass('public.app_settings') IS NOT NULL AS ok").fetchone()
    if not _first(exists):
        return True
    row = conn.execute("SELECT value FROM app_settings WHERE key = %s", (PSEUDONYM_KEY,)).fetchone()
    if row is None:
        return True
    return _first(row) is not False


def store_names(conn: psycopg.Connection, names_enabled: bool) -> bool:
    """Nama disimpan hanya bila .env mengizinkan (mode lokal) DAN admin mematikan mode pseudonim."""
    return names_enabled and not pseudonym_active(conn)


def _first(row):
    if isinstance(row, dict):
        return next(iter(row.values()))
    return row[0]
