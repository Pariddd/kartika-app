"""Impor file JSON hasil ekspor Apify untuk X, Instagram, dan TikTok.

Paket gratis Apify umumnya tidak mengizinkan Actor dipanggil lewat API, sehingga alurnya:
analis menjalankan Actor di Apify Console, mengekspor JSON, lalu mengunggahnya ke Radar Narasi.

Tiap Actor memberi nama field yang berbeda. Pembaca ini mencoba nama yang lazim lebih dulu
(lihat FIELDS); untuk keterangan wajib yang tidak ditemukan, kolom dikenali otomatis dari isi
datanya (detect_fields): teks = kolom string terpanjang, waktu = kolom yang bisa diurai sebagai
waktu, penulis = kolom pengenal akun yang nilainya berulang. Tanpa id post, id dibentuk dari
hash penulis|teks|waktu (seperti CSV). Bila tetap gagal, pesan galat menyebut nama kolom yang
ada di file. Sudah diuji dengan ekspor asli apidojo/tweet-scraper (X).

Yang dibaca dari tiap baris hanya: id post, teks, waktu, id balasan/percakapan, URL di teks,
id penulis (langsung di-HMAC oleh ingest; username hanya dipakai sebagai id bila id tidak ada,
dan tetap di-HMAC), tanggal pembuatan akun, jumlah followers/following, dan, bila diizinkan,
nama tampilan. Bio, lokasi, foto, dan URL profil tidak dibaca. File dihapus pemanggil setelah dibaca.
"""

from __future__ import annotations

import hashlib
import json
import re
from dataclasses import dataclass, field
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Any, Iterator

from radar import source
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
        "author.id", "authorMeta.id", "owner.id", "user.id", "uid", "userId", "user_id", "ownerId", "owner.pk", "user.pk", "user.uid",
        # Tanpa id numerik, username dipakai sebagai pengenal (tetap di-HMAC, tidak pernah disimpan).
        "ownerUsername", "uniqueId", "username", "author.userName", "owner.username", "user.username", "user.uniqueId", "user.unique_id",
    ),
    "text": ("fullText", "text", "commentText", "comment", "content"),
    "posted_at": ("createdAt", "timestamp", "createTimeISO", "createTime", "create_time", "created_at", "createdAtISO", "date"),
    "reply_to": ("inReplyToId", "repliesToId", "replyToId", "parentId", "parentCommentId", "parent_comment_id", "replyToCommentId"),
    "conversation": ("conversationId", "postId", "post_id", "awemeId", "aweme_id", "postUrl", "videoWebUrl", "webVideoUrl", "videoUrl", "submittedVideoUrl", "inputUrl"),
    # Konten tempat komentar ditulis (URL atau ID video/post); hanya ID kontennya yang disimpan.
    "source": ("conversationId", "videoWebUrl", "submittedVideoUrl", "awemeId", "aweme_id", "postUrl", "url", "inputUrl", "webVideoUrl", "videoUrl", "postId", "post_id"),
    "name": ("author.name", "authorMeta.nickName", "owner.full_name", "owner.fullName", "ownerFullName", "user.nickname", "nickname", "user.full_name", "fullName", "displayName"),
    # @username; hanya dibaca bila fitur nama menyala (mode lokal), digabung dengan nama tampilan.
    "handle": ("author.userName", "authorMeta.name", "ownerUsername", "owner.username", "user.username", "uniqueId", "user.uniqueId", "user.unique_id", "username"),
    "account_created_at": ("author.createdAt",),
    "followers": ("author.followers", "authorMeta.fans"),
    "following": ("author.following", "authorMeta.following"),
}
NESTED_REPLIES = ("replies", "childComments", "reply_comment", "replyComments")

# Pengenalan otomatis: jumlah baris contoh, kedalaman objek, dan kata petunjuk pada nama kolom.
DETECT_SAMPLE = 300
DETECT_DEPTH = 3
MIN_COVERAGE = 0.5
TEXT_HINTS = ("text", "content", "comment", "caption", "body", "message", "desc", "isi", "tweet", "post")
TIME_HINTS = ("time", "date", "created", "publish", "posted", "timestamp", "waktu")
AUTHOR_PARENTS = ("author", "user", "owner", "channel", "profile", "creator", "account", "commenter", "poster", "from")
AUTHOR_KEYS = ("id", "uid", "pk", "username", "handle", "screen_name", "screenname", "uniqueid", "user_id", "userid", "name")


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


def _flatten(item: dict[str, Any], prefix: str = "", depth: int = 0) -> Iterator[tuple[str, Any]]:
    for key, value in item.items():
        if not isinstance(key, str):
            continue
        path = f"{prefix}{key}"
        if isinstance(value, dict):
            if depth + 1 < DETECT_DEPTH:
                yield from _flatten(value, path + ".", depth + 1)
        elif not isinstance(value, list):
            yield path, value


def _is_author_path(path: str) -> bool:
    parts = path.lower().split(".")
    return len(parts) > 1 and any(hint in part for part in parts[:-1] for hint in AUTHOR_PARENTS)


def detect_fields(items: list[dict[str, Any]]) -> dict[str, str]:
    """Menebak kolom teks, waktu, penulis, dan id post dari isi baris, bukan dari namanya.

    Hanya dipakai untuk keterangan yang tidak ditemukan lewat FIELDS. Mengembalikan path bertitik.
    """
    sample = [item for item in items[:DETECT_SAMPLE] if isinstance(item, dict)]
    if not sample:
        return {}
    columns: dict[str, list[Any]] = {}
    for item in sample:
        for path, value in _flatten(item):
            if value is not None and value != "":
                columns.setdefault(path, []).append(value)
    n = len(sample)
    covered = {path: values for path, values in columns.items() if len(values) / n >= MIN_COVERAGE}
    found: dict[str, str] = {}

    # Waktu: kolom yang hampir selalu bisa diurai sebagai waktu; waktu pembuatan akun dihindari.
    best = None
    for path, values in covered.items():
        if isinstance(values[0], bool) or _is_author_path(path):
            continue
        parsed = sum(1 for value in values if _time_or_none(value) is not None)
        if parsed / len(values) < 0.9:
            continue
        name = path.lower().rsplit(".", 1)[-1]
        if isinstance(values[0], (int, float)) and not any(hint in name for hint in TIME_HINTS):
            continue  # angka biasa (jumlah like, dsb.) yang kebetulan masuk rentang epoch
        score = (any(hint in name for hint in TIME_HINTS), len(values), isinstance(values[0], str))
        if best is None or score > best[0]:
            best = (score, path)
    if best:
        found["posted_at"] = best[1]

    # Teks: kolom string yang panjang dan beragam, bukan tautan.
    best = None
    for path, values in covered.items():
        strings = [v for v in values if isinstance(v, str)]
        if len(strings) < len(values) * 0.9 or path == found.get("posted_at") or _is_author_path(path):
            continue
        if sum(1 for v in strings if v.lstrip().lower().startswith(("http://", "https://"))) > len(strings) / 2:
            continue
        average = sum(len(v) for v in strings) / len(strings)
        distinct = len(set(strings)) / len(strings)
        if average < 8 or distinct < 0.3:
            continue
        name = path.lower().rsplit(".", 1)[-1]
        score = (any(hint in name for hint in TEXT_HINTS), average)
        if best is None or score > best[0]:
            best = (score, path)
    if best:
        found["text"] = best[1]

    # Penulis: pengenal akun di dalam objek penulis, atau kolom bernama user/author/owner/...
    best = None
    for path, values in covered.items():
        if path in found.values() or not all(isinstance(v, (str, int)) and not isinstance(v, bool) for v in values):
            continue
        lower = path.lower()
        leaf = lower.rsplit(".", 1)[-1]
        nested = _is_author_path(path) and any(leaf == key or leaf.endswith(key) for key in AUTHOR_KEYS)
        flat = "." not in lower and any(hint in leaf for hint in AUTHOR_PARENTS + ("username", "handle"))
        if not (nested or flat):
            continue
        rank = 2 if leaf in ("id", "uid", "pk", "user_id", "userid") or leaf.endswith("id") else (1 if "name" not in leaf or "username" in leaf else 0)
        score = (rank, len(values))
        if best is None or score > best[0]:
            best = (score, path)
    if best:
        found["author_id"] = best[1]

    # Nama tampilan dan @username penulis (hanya dipakai bila fitur nama menyala).
    def string_column(path: str) -> bool:
        # Nilai boleh sama di semua baris: file bisa berisi post dari satu akun saja.
        return all(isinstance(v, str) for v in covered[path])

    handle_leaves = ("username", "user_name", "handle", "screen_name", "screenname", "uniqueid", "unique_id", "login")
    name_leaves = ("name", "nickname", "displayname", "fullname", "full_name", "title")
    for path in covered:
        if path in (found.get("text"), found.get("posted_at")) or not string_column(path):
            continue
        lower = path.lower()
        leaf = lower.rsplit(".", 1)[-1]
        nested = _is_author_path(path)
        flat = "." not in lower and any(hint in leaf for hint in AUTHOR_PARENTS)
        if "handle" not in found and (nested or flat or "." not in lower) and any(leaf == h or leaf.endswith(h) for h in handle_leaves):
            found["handle"] = path
        elif "name" not in found and (nested or flat) and (any(leaf.endswith(n) for n in name_leaves) or (flat and leaf in AUTHOR_PARENTS)):
            found["name"] = path

    # Id post: kolom pengenal di tingkat atas yang (hampir) unik per baris.
    for path, values in covered.items():
        leaf = path.lower()
        if "." in leaf or path in found.values():
            continue
        if (leaf in ("id", "_id", "key", "uuid") or leaf.endswith("id")) and all(isinstance(v, (str, int)) and not isinstance(v, bool) for v in values):
            if len(set(map(str, values))) >= 0.95 * len(values) and len(values) / n >= 0.9:
                found["post_id"] = path
                break
    return found


def _load(path: Path) -> list[Any]:
    """Daftar JSON (ekspor dataset Apify), objek berisi daftar, atau JSON Lines."""
    raw = path.read_text(encoding="utf-8-sig")
    try:
        data = json.loads(raw)
    except json.JSONDecodeError:
        data = []
        for line in raw.splitlines():
            if line.strip():
                try:
                    data.append(json.loads(line))
                except json.JSONDecodeError as error:
                    raise PayloadError("file bukan JSON yang valid; ekspor ulang dari Apify dengan format JSON") from error
    if isinstance(data, dict):
        for key in ("items", "data", "results", "comments", "posts", "tweets"):
            if isinstance(data.get(key), list):
                return data[key]
        raise PayloadError("JSON harus berupa daftar (format ekspor dataset Apify: Export, lalu JSON)")
    if not isinstance(data, list):
        raise PayloadError("JSON harus berupa daftar (format ekspor dataset Apify: Export, lalu JSON)")
    return data


def _field_names(items: list[Any]) -> list[str]:
    names: dict[str, None] = {}
    for item in items[:50]:
        if isinstance(item, dict):
            for path, _ in _flatten(item):
                names.setdefault(path, None)
    return list(names)[:25]


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


def _source(platform: str, item: dict[str, Any]) -> str | None:
    """ID konten sumber dari kolom pertama yang bisa dikenali; tweet tanpa conversationId = tweet itu sendiri."""
    for path in FIELDS["source"]:
        ref = source.build(platform, _get(item, path))
        if ref:
            return ref
    if platform == "x":
        return source.build("x", _first(item, "post_id"))
    return None


def _author_label(item: dict[str, Any], get: Any = None) -> str | None:
    """Nama tampilan dan @username penulis, misalnya "Budi Santoso (@budi_s)"."""
    get = get or _first
    name = get(item, "name")
    handle = get(item, "handle")
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
        data = _load(path)
    except UnicodeDecodeError as error:
        raise PayloadError("file bukan JSON yang valid; ekspor ulang dari Apify dengan format JSON") from error
    if len(data) > MAX_RAW_ITEMS:
        raise PayloadError(f"file berisi lebih dari {MAX_RAW_ITEMS} baris")

    detected = detect_fields([item for item in data if isinstance(item, dict) and not item.get("noResults")])

    def value(item: dict[str, Any], key: str) -> Any:
        known = _first(item, key)
        if known is not None or key not in detected:
            return known
        fallback = _get(item, detected[key])
        return None if fallback == "" else fallback

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

        author_id = _id(value(item, "author_id"))
        text = value(item, "text")
        posted_at = _time_or_none(value(item, "posted_at"))
        if not author_id or not isinstance(text, str) or not text.strip() or posted_at is None:
            result.skipped += 1
            continue
        raw_id = value(item, "post_id")
        if raw_id is None:
            # Actor tanpa id post: id dibentuk dari isi baris, sehingga baris identik dihitung sekali.
            post_id = "h:" + hashlib.sha256(f"{author_id}|{text}|{posted_at.isoformat()}".encode()).hexdigest()
        else:
            post_id = _id(raw_id)  # id ada tapi rusak (karakter kontrol, terlalu panjang): baris dilewati
            if not post_id:
                result.skipped += 1
                continue
        if post_id in seen:
            continue
        seen.add(post_id)

        name = _author_label(item, value) if include_names else None
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
                source_ref=_source(platform, item),
            )
        )

    if rows and not result.posts and not result.retweets_skipped:
        raise PayloadError(
            "tidak ada baris yang bisa dibaca: tiap baris butuh penulis, teks, dan waktu, "
            "dan kolomnya tidak ditemukan di file ini. Pastikan Actor menghasilkan post atau komentar "
            "(bukan profil). Kolom yang ada: " + (", ".join(_field_names(data)) or "tidak ada")
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
