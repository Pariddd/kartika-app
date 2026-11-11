"""Validasi payload job sesuai docs/kontrak.md bagian 4.

Laravel sudah memvalidasi, tetapi worker memeriksa ulang: baris di analysis_jobs
bisa saja ditulis oleh kode lain atau diubah manual.
"""

from __future__ import annotations

import os
import re
from datetime import date
from typing import Any

from radar.errors import PayloadError

__all__ = ["PayloadError", "validate_envelope", "validate_collect_params", "SYNTHETIC_PRESETS"]

SCHEMA_VERSION = 1
VIDEO_ID = re.compile(r"^[A-Za-z0-9_-]{11}$")
MAX_VIDEO_IDS = 10
# Batas per kasus untuk semua sumber data nyata. Memori analisis tumbuh kira-kira kuadratik:
# 10.000 post butuh sekitar 1,6 GB, 20.000 post sekitar 3 GB.
# Batas bisa diubah lewat RADAR_MAX_ITEMS (isi nilai yang sama di web/.env dan engine/.env).
MAX_ITEMS = 20_000
MAX_ITEMS_CEILING = 200_000


def max_items() -> int:
    """Dibaca saat dipakai, bukan saat impor modul, karena .env dimuat setelahnya."""
    raw = os.getenv("RADAR_MAX_ITEMS", "").strip()
    if not raw:
        return MAX_ITEMS
    try:
        value = int(raw)
    except ValueError as exc:
        raise PayloadError("RADAR_MAX_ITEMS di engine/.env harus bilangan bulat") from exc
    if not 1 <= value <= MAX_ITEMS_CEILING:
        raise PayloadError(f"RADAR_MAX_ITEMS di engine/.env harus 1-{MAX_ITEMS_CEILING}")
    return value
QUERY_MAX_LENGTH = 200
IMPORT_PLATFORMS = ("x", "instagram", "tiktok")
IMPORT_FILE_REF = re.compile(r"^uploads/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.json$")
SYNTHETIC_PRESETS = ("standar", "kontrol", "parafrase")
MAX_SEED = 2**31 - 1

DEFAULT_ANALYZE_PARAMS: dict[str, Any] = {
    "min_signals": 2,
    "min_cluster_size": 3,
    "time_window_seconds": 300,
    "min_co_events": 3,
    "minhash_threshold": 0.8,
    "tfidf_threshold": 0.6,
    "min_shared_texts": 2,
    "seed": 42,
}




def _is_int(value: Any) -> bool:
    return isinstance(value, int) and not isinstance(value, bool)


def validate_envelope(payload: Any, *, job_type: str, case_id: int) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise PayloadError("payload bukan objek JSON")
    if payload.get("schema_version") != SCHEMA_VERSION:
        raise PayloadError(f"schema_version harus {SCHEMA_VERSION}")
    if payload.get("job_type") != job_type:
        raise PayloadError("job_type di payload tidak sama dengan kolom job_type")
    if payload.get("case_id") != case_id:
        raise PayloadError("case_id di payload tidak sama dengan kolom case_id")
    if not _is_int(payload.get("requested_by")):
        raise PayloadError("requested_by harus bilangan bulat")
    if not isinstance(payload.get("params"), dict):
        raise PayloadError("params harus objek JSON")
    return payload


def _check_max_items(value: Any, upper: int) -> None:
    if not _is_int(value) or not 1 <= value <= upper:
        raise PayloadError(f"max_items harus 1-{upper}")


def _check_date(name: str, value: Any) -> date | None:
    if value is None:
        return None
    if not isinstance(value, str):
        raise PayloadError(f"{name} harus YYYY-MM-DD atau null")
    try:
        return date.fromisoformat(value)
    except ValueError as exc:
        raise PayloadError(f"{name} harus YYYY-MM-DD atau null") from exc


def validate_collect_params(params: dict[str, Any]) -> dict[str, Any]:
    platform = params.get("platform")

    if platform == "youtube":
        video_ids = params.get("video_ids")
        if not isinstance(video_ids, list) or not 1 <= len(video_ids) <= MAX_VIDEO_IDS:
            raise PayloadError(f"video_ids harus berisi 1-{MAX_VIDEO_IDS} ID")
        if not all(isinstance(v, str) and VIDEO_ID.fullmatch(v) for v in video_ids):
            raise PayloadError("video_ids berisi ID yang tidak valid")
        _check_max_items(params.get("max_items"), max_items())
        return params

    if platform in IMPORT_PLATFORMS:
        # Impor hasil ekspor Apify (paket gratis tidak bisa memanggil Actor lewat API).
        file_ref = params.get("file_ref")
        if not isinstance(file_ref, str) or not IMPORT_FILE_REF.fullmatch(file_ref):
            raise PayloadError("file_ref harus uploads/<uuid>.json")
        query = params.get("query")
        if query is not None and (not isinstance(query, str) or len(query) > QUERY_MAX_LENGTH):
            raise PayloadError(f"query maksimal {QUERY_MAX_LENGTH} karakter")
        _check_max_items(params.get("max_items"), max_items())
        return params

    if platform == "synthetic":
        if params.get("preset") not in SYNTHETIC_PRESETS:
            raise PayloadError("preset sintetis harus salah satu dari: " + ", ".join(SYNTHETIC_PRESETS))
        seed = params.get("seed")
        if not _is_int(seed) or not 0 <= seed <= MAX_SEED:
            raise PayloadError(f"seed harus bilangan bulat 0-{MAX_SEED}")
        return params

    raise PayloadError("platform tidak dikenal")
