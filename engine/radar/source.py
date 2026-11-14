"""Pengenal konten sumber sebuah komentar: video atau post tempat komentar itu ditulis.

Bentuknya "<platform>:<id konten>", misalnya "youtube:dQw4w9WgXcQ" atau "tiktok:7332...".
Yang disimpan hanya ID konten publik, tidak pernah username pembuat konten: URL TikTok dan
Instagram memuat username, jadi ID diambil dari URL lalu URL-nya dibuang. ID ini tidak di-HMAC
karena menunjuk konten, bukan orang (OSINT berpusat pada konten), dan analis butuh membukanya.
"""

from __future__ import annotations

import re
from urllib.parse import urlsplit

PATTERN = re.compile(r"^(youtube|x|tiktok|instagram):[A-Za-z0-9_-]{1,64}$")
_PLAIN_ID = re.compile(r"^[A-Za-z0-9_-]{1,64}$")
_DIGITS = re.compile(r"^\d{1,30}$")
# Pola dicocokkan pada PATH URL saja, setelah nama host diperiksa (lihat _path).
_TIKTOK_PATH = re.compile(r"^/(?:@[^/]*/)?(?:video|photo)/(\d{1,30})")
_INSTAGRAM_PATH = re.compile(r"^/(?:[^/]+/)?(?:p|reel|reels|tv)/([A-Za-z0-9_-]{1,64})")
_X_PATH = re.compile(r"^/(?:[^/]+|i(?:/web)?)/status(?:es)?/(\d{1,30})")
_HOSTS = {"tiktok": ("tiktok.com",), "instagram": ("instagram.com",), "x": ("x.com", "twitter.com")}


def _path(platform: str, value: str) -> str | None:
    """Path URL bila host-nya milik platform itu (atau subdomainnya); selain itu None."""
    if not value.lower().startswith(("http://", "https://")):
        return None
    parts = urlsplit(value)
    host = (parts.hostname or "").lower()
    if not any(host == domain or host.endswith("." + domain) for domain in _HOSTS[platform]):
        return None
    return parts.path


def build(platform: str, value: object) -> str | None:
    """Pengenal sumber dari ID atau URL konten. None bila tidak dikenali (tidak pernah menebak)."""
    if isinstance(value, bool) or not isinstance(value, (str, int)):
        return None
    value = str(value).strip()
    if not value:
        return None
    if platform == "youtube":
        return f"youtube:{value}" if _PLAIN_ID.fullmatch(value) and len(value) == 11 else None
    if platform in ("x", "tiktok"):
        path = _path(platform, value)
        if path is not None:
            match = (_X_PATH if platform == "x" else _TIKTOK_PATH).match(path)
            found = match.group(1) if match else None
        else:
            found = value if _DIGITS.fullmatch(value) else None
        return f"{platform}:{found}" if found else None
    if platform == "instagram":
        path = _path(platform, value)
        match = _INSTAGRAM_PATH.match(path) if path is not None else None
        return f"instagram:{match.group(1)}" if match else None
    return None


def valid(ref: str | None) -> str | None:
    return ref if isinstance(ref, str) and PATTERN.fullmatch(ref) else None
