"""Validasi payload job sesuai docs/kontrak.md bagian 4.

Laravel sudah memvalidasi, tetapi worker memeriksa ulang: baris di analysis_jobs
bisa saja ditulis oleh kode lain atau diubah manual.
"""

from __future__ import annotations

import re
from datetime import date
from typing import Any

SCHEMA_VERSION = 1
VIDEO_ID = re.compile(r"^[A-Za-z0-9_-]{11}$")
MAX_VIDEO_IDS = 10
YOUTUBE_MAX_ITEMS = 5000
X_MAX_ITEMS = 1000
QUERY_MAX_LENGTH = 200

DEFAULT_ANALYZE_PARAMS: dict[str, Any] = {
    "min_signals": 2,
    "min_cluster_size": 3,
    "time_window_seconds": 300,
    "min_co_events": 3,
    "minhash_threshold": 0.8,
    "embedding_threshold": 0.85,
    "seed": 42,
}


class PayloadError(ValueError):
    """Payload tidak sesuai kontrak. Pesan dipakai sebagai analysis_jobs.error."""


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
        _check_max_items(params.get("max_items"), YOUTUBE_MAX_ITEMS)
        return params

    if platform == "x":
        query = params.get("query")
        if not isinstance(query, str) or not 1 <= len(query.strip()) <= QUERY_MAX_LENGTH:
            raise PayloadError(f"query harus 1-{QUERY_MAX_LENGTH} karakter")
        _check_max_items(params.get("max_items"), X_MAX_ITEMS)
        since = _check_date("since", params.get("since"))
        until = _check_date("until", params.get("until"))
        if since and until and until < since:
            raise PayloadError("until tidak boleh sebelum since")
        return params

    raise PayloadError("platform tidak dikenal")
