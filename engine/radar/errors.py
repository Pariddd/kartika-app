"""Kesalahan yang pesannya aman ditampilkan sebagai analysis_jobs.error.

Pesan tidak boleh memuat data pribadi, token, atau isi post.
"""


class JobError(Exception):
    """Job gagal permanen dengan alasan yang bisa dibaca analis."""


class PayloadError(JobError, ValueError):
    """Payload tidak sesuai kontrak."""


class ConfigError(JobError):
    """Konfigurasi worker (engine/.env) belum lengkap."""
