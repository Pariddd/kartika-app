"""Impor file JSON hasil ekspor Apify untuk X, Instagram, dan TikTok.

Paket gratis Apify umumnya tidak mengizinkan Actor dipanggil lewat API, sehingga alurnya:
analis menjalankan Actor di Apify Console, mengekspor JSON, lalu mengunggahnya ke Radar Narasi.

Tiap Actor memberi nama field yang berbeda. Karena itu pembaca ini mencoba beberapa nama
yang lazim untuk tiap keterangan (lihat FIELDS) dan gagal dengan pesan jelas bila tidak ada
satu pun baris yang cocok. Sudah diuji dengan ekspor asli apidojo/tweet-scraper (X).

Yang dibaca dari tiap baris hanya: id post, teks, waktu, id balasan/percakapan, URL di teks,
id penulis (langsung di-HMAC oleh ingest; username hanya dipakai sebagai id bila id tidak ada,
dan tetap di-HMAC), tanggal pembuatan akun, jumlah followers/following, dan, bila diizinkan,
nama tampilan. Bio, lokasi, foto, dan URL profil tidak dibaca. File dihapus pemanggil setelah dibaca.
"""

from __future__ import annotations

import json
import re
from dataclasses import dataclass, field
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Any, Iterator

from radar.errors import PayloadError
from radar.ingest import RawPost

PLATFORMS = ("x", "instagram", "tiktok")
MAX_BYTES = 200 * 1024 * 1024
MAX_RAW_ITEMS = 200_000
MAX_ID_LENGTH = 300
MIN_USEFUL_POSTS = 50

# Nama field yang dicoba, berurutan. Titik berarti objek bersarang.
FIELDS: dict[str, tuple[str, ...]] = {
    "post_id": ("id", "cid", "commentId", "comment_id", "pk"),
    "author_id": (
        "author.id", "owner.id", "user.id", "uid", "userId", "user_id", "ownerId", "owner.pk", "user.pk", "user.uid",
        # Tanpa id numerik, username dipakai sebagai pengenal (tetap di-HMAC, tidak pernah disimpan).
        "ownerUsername", "uniqueId", "username", "author.userName", "owner.username", "user.username", "user.uniqueId", "user.unique_id",
    ),
    "text": ("fullText", "text", "commentText", "comment", "content"),
    "posted_at": ("createdAt", "timestamp", "createTimeISO", "createTime", "create_time", "created_at", "createdAtISO", "date"),
    "reply_to": ("inReplyToId", "repliesToId", "replyToId", "parentId", "parentCommentId", "parent_comment_id", "replyToCommentId"),
    "conversation": ("conversationId", "postId", "post_id", "awemeId", "aweme_id", "postUrl", "videoWebUrl", "videoUrl", "submittedVideoUrl", "inputUrl"),
    "name": ("author.name", "owner.full_name", "owner.fullName", "ownerFullName", "user.nickname", "nickname", "user.full_name", "fullName", "displayName"),
    # @username; hanya dibaca bila fitur nama menyala (mode lokal), digabung dengan nama tampilan.
    "handle": ("author.userName", "ownerUsername", "owner.username", "user.username", "uniqueId", "user.uniqueId", "user.unique_id", "username"),
    "account_created_at": ("author.createdAt",),
    "followers": ("author.followers",),
    "following": ("author.following",),
}
NESTED_REPLIES = ("replies", "childComments", "reply_comment", "replyComments")

MONTHS = {m: i for i, m in enumerate(("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"), 1)}
# Format waktu X: "Tue Oct 13 10:55:46 +0000 2026"
TWITTER_TIME = re.compile(r"^[A-Z][a-z]{2} ([A-Z][a-z]{2}) (\d{2}) (\d{2}):(\d{2}):(\d{2}) ([+-])(\d{2})(\d{2}) (\d{4})$")
CONTROL = re.compile(r"[\x00-\x1f\x7f]")


@dataclass
class ImportResult:
    posts: list[RawPost] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)
    skipped: int = 0
    retweets_skipped: int = 0


def parse_time(value: object) -> datetime:
    """Waktu dalam format X, ISO 8601 berzona, atau epoch (detik/milidetik).

    Diurai tanpa bergantung pada locale sistem (Windows berbahasa Indonesia).
    """
    if isinstance(value, bool):
        raise ValueError("waktu")
    if isinstance(value, (int, float)) or (isinstance(value, str) and value.strip().isdigit()):
        number = float(value)
        if number > 1e11:  # milidetik
            number /= 1000
        if not 946684800 <= number <= 4102444800:  # tahun 2000-2100
            raise ValueError("epoch di luar rentang")
        return datetime.fromtimestamp(number, tz=timezone.utc)
    if not isinstance(value, str):
        raise ValueError("waktu")
    value = value.strip()
    if match := TWITTER_TIME.fullmatch(value):
        month, day, hh, mm, ss, sign, oh, om, year = match.groups()
        if month not in MONTHS:
            raise ValueError("bulan")
        parsed = datetime(int(year), MONTHS[month], int(day), int(hh), int(mm), int(ss), tzinfo=timezone.utc)
        offset = (int(oh) * 60 + int(om)) * (1 if sign == "+" else -1)
        return parsed - timedelta(minutes=offset)
    parsed = datetime.fromisoformat(value.replace("Z", "+00:00"))
    if parsed.tzinfo is None:
        raise ValueError("zona waktu")
    return parsed


def _get(item: dict[str, Any], path: str) -> Any:
    value: Any = item
    for part in path.split("."):
        if not isinstance(value, dict):
            return None
        value = value.get(part)
    return value


def _first(item: dict[str, Any], key: str) -> Any:
    for path in FIELDS[key]:
        value = _get(item, path)
        if value is not None and value != "":
            return value
    return None


def _id(value: object) -> str | None:
    """Pengenal dari platform: teks atau angka, tanpa karakter kontrol, panjang terbatas."""
    if isinstance(value, bool) or not isinstance(value, (str, int)):
        return None
    value = str(value).strip()
    if not value or len(value) > MAX_ID_LENGTH or CONTROL.search(value):
        return None
    return value


def _count(value: object) -> int | None:
    return value if isinstance(value, int) and not isinstance(value, bool) and 0 <= value < 2**31 else None


def _time_or_none(value: object) -> datetime | None:
    try:
        return parse_time(value)
    except (ValueError, OverflowError, OSError):
        return None


def _urls(item: dict[str, Any]) -> tuple[str, ...]:
    found = []
    entities = item.get("entities")
    for url in (entities.get("urls") if isinstance(entities, dict) else None) or []:
        expanded = url.get("expanded_url") if isinstance(url, dict) else None
        if isinstance(expanded, str) and expanded.lower().startswith(("http://", "https://")):
            found.append(expanded)
    return tuple(dict.fromkeys(found))


def _walk(items: list[Any], parent: str | None = None) -> Iterator[tuple[dict[str, Any], str | None]]:
    """Baris beserta id induknya; balasan bersarang (Instagram, TikTok) ikut dijelajahi."""
    for item in items:
        if not isinstance(item, dict):
            yield {}, parent
            continue
        yield item, parent
        own_id = _id(_first(item, "post_id"))
        for key in NESTED_REPLIES:
            nested = item.get(key)
            if isinstance(nested, list) and nested:
                yield from _walk(nested, own_id)


def _author_label(item: dict[str, Any]) -> str | None:
    """Nama tampilan dan @username penulis, misalnya "Budi Santoso (@budi_s)"."""
    name = _first(item, "name")
    handle = _first(item, "handle")
    name = name.strip() if isinstance(name, str) else ""
    handle = handle.strip().lstrip("@") if isinstance(handle, str) else ""
    if name and handle and name.lower() != handle.lower():
        return f"{name} (@{handle})"
    if handle:
        return f"@{handle}"
    return name or None


def read(path: Path, platform: str, max_items: int, *, include_names: bool = False) -> ImportResult:
    if platform not in PLATFORMS:
        raise PayloadError("platform impor tidak dikenal")
    if not path.is_file():
        raise PayloadError("file unggahan tidak ditemukan (mungkin sudah dihapus)")
    if path.stat().st_size > MAX_BYTES:
        raise PayloadError("ukuran file melebihi 200 MB")
    try:
        data = json.loads(path.read_text(encoding="utf-8-sig"))
    except (UnicodeDecodeError, json.JSONDecodeError) as error:
        raise PayloadError("file bukan JSON yang valid; ekspor ulang dari Apify dengan format JSON") from error
    if not isinstance(data, list):
        raise PayloadError("JSON harus berupa daftar (format ekspor dataset Apify: Export, lalu JSON)")
    if len(data) > MAX_RAW_ITEMS:
        raise PayloadError(f"file berisi lebih dari {MAX_RAW_ITEMS} baris")

    result = ImportResult()
    seen: set[str] = set()
    rows = 0
    for item, parent in _walk(data):
        if len(result.posts) >= max_items:
            break
        if item.get("noResults"):
            continue
        rows += 1
        if item.get("isRetweet"):
            # Retweet murni menyalin teks orang lain persis; kemiripannya selalu 1 dan
            # akan menenggelamkan sinyal teks. Dicatat sebagai peringatan, tidak dianalisis.
            result.retweets_skipped += 1
            continue

        post_id = _id(_first(item, "post_id"))
        author_id = _id(_first(item, "author_id"))
        text = _first(item, "text")
        posted_at = _time_or_none(_first(item, "posted_at"))
        if not post_id or not author_id or not isinstance(text, str) or not text.strip() or posted_at is None:
            result.skipped += 1
            continue
        if post_id in seen:
            continue
        seen.add(post_id)

        name = _author_label(item) if include_names else None
        result.posts.append(
            RawPost(
                platform=platform,
                author_id=author_id,
                post_id=post_id,
                text=text,
                posted_at=posted_at,
                account_created_at=_time_or_none(_first(item, "account_created_at")),
                followers=_count(_first(item, "followers")),
                following=_count(_first(item, "following")),
                reply_to_id=_id(_first(item, "reply_to")) or parent,
                conversation_id=_id(_first(item, "conversation")),
                urls=_urls(item),
                author_name=name,
            )
        )

    if rows and not result.posts and not result.retweets_skipped:
        raise PayloadError(
            "tidak ada baris yang bisa dibaca: nama field dari Actor ini belum dikenali. "
            "Butuh id, penulis, teks, dan waktu di tiap baris"
        )
    if result.retweets_skipped:
        result.warnings.append(f"{result.retweets_skipped} retweet dilewati (teksnya salinan persis tweet lain)")
    if result.skipped:
        result.warnings.append(f"{result.skipped} baris dilewati karena tanpa id, penulis, teks, atau waktu yang valid")
    if len(result.posts) >= max_items:
        result.warnings.append(f"batas max_items ({max_items}) tercapai; sisa isi file tidak diimpor")
    if len(result.posts) < MIN_USEFUL_POSTS:
        result.warnings.append(
            f"hanya {len(result.posts)} post; analisis koordinasi butuh ratusan post agar hasilnya bermakna"
        )
    return result
