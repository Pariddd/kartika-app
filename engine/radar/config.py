"""Konfigurasi worker dari engine/.env."""

from __future__ import annotations

import os
import socket
from dataclasses import dataclass, field
from pathlib import Path

from dotenv import load_dotenv

LOCAL = "local"
PUBLIC = "public"


def normalize_mode(value: str | None) -> str:
    """Sama dengan App\\Support\\AppMode: nilai kosong atau asing dianggap publik (fail closed)."""
    return LOCAL if (value or "").strip().lower() == LOCAL else PUBLIC


@dataclass(frozen=True)
class Settings:
    app_mode: str
    db_host: str
    db_port: int
    db_name: str
    db_user: str
    db_password: str = field(repr=False)
    poll_seconds: float
    worker_id: str
    pseudo_key: str = field(default="", repr=False)
    upload_dir: str = ""
    youtube_api_key: str = field(default="", repr=False)

    @property
    def is_local(self) -> bool:
        return self.app_mode == LOCAL


def load_settings(env_file: Path | None = None) -> Settings:
    load_dotenv(env_file or Path(__file__).resolve().parent.parent / ".env")

    return Settings(
        app_mode=normalize_mode(os.getenv("APP_MODE")),
        db_host=os.getenv("RADAR_DB_HOST", "127.0.0.1"),
        db_port=int(os.getenv("RADAR_DB_PORT", "5432")),
        db_name=os.getenv("RADAR_DB_NAME", "radar_narasi"),
        db_user=os.getenv("RADAR_DB_USER", "radar_engine"),
        db_password=os.getenv("RADAR_DB_PASSWORD", ""),
        poll_seconds=float(os.getenv("WORKER_POLL_SECONDS", "3")),
        # Maksimal 64 karakter sesuai kolom analysis_jobs.worker_id.
        worker_id=f"{socket.gethostname()}:{os.getpid()}"[:64],
        pseudo_key=os.getenv("PSEUDO_KEY", ""),
        upload_dir=os.getenv("RADAR_UPLOAD_DIR", ""),
        youtube_api_key=os.getenv("YOUTUBE_API_KEY", "").strip(),
    )
