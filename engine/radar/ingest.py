"""Normalisasi dan penyimpanan post dari semua sumber (YouTube, CSV, sintetis).

ID asli tidak pernah ditulis ke database: akun dan post di-HMAC lebih dulu,
mention di dalam teks disamarkan, dan label tampilan akun dibuat berurutan per kasus.
Pengecualian (docs/aturan-kerja.md): di mode lokal dengan fitur nama menyala, nama tampilan
akun dan teks dengan @username asli ikut disimpan untuk admin dan analis.
"""

from __future__ import annotations

import re
from dataclasses import dataclass, field
from datetime import datetime
from typing import Iterable

import psycopg
from psycopg.types.json import Jsonb

from radar.pseudo import Pseudonymizer

MAX_TEXT_LENGTH = 5000
MAX_URLS = 20
MAX_NAME_LENGTH = 100
CONTROL_CHARS = re.compile(r"[\x00-\x1f\x7f\u200b-\u200f\u202a-\u202e\u2066-\u2069]")
URL = re.compile(r"https?://[^\s<>\"']+", re.IGNORECASE)


@dataclass(frozen=True)
class RawPost:
    """Satu post dari sumber mana pun, sebelum pseudonimisasi. Hanya hidup di memori."""

    platform: str
    author_id: str
    post_id: str
    text: str
    posted_at: datetime
    account_created_at: datetime | None = None
    followers: int | None = None
    following: int | None = None
    reply_to_id: str | None = None
    conversation_id: str | None = None
    urls: tuple[str, ...] = ()
    # Nama tampilan penulis; hanya disimpan bila ingest dipanggil dengan store_names=True.
    author_name: str | None = None


@dataclass
class IngestResult:
    posts_inserted: int = 0
    accounts_inserted: int = 0
    duplicates_skipped: int = 0
    # author_id asli -> accounts.id; hanya di memori, dipakai untuk menulis kunci jawaban sintetis.
    account_ids: dict[str, int] = field(default_factory=dict)


def extract_urls(text: str) -> list[str]:
    urls = [match.rstrip(".,;:!?)]}") for match in URL.findall(text)]
    return list(dict.fromkeys(urls))[:MAX_URLS]


def clean_name(name: str | None) -> str | None:
    """Buang karakter kontrol dan pembalik arah teks (bisa mengacaukan tampilan), batasi panjang."""
    if not name:
        return None
    cleaned = CONTROL_CHARS.sub("", name).strip()[:MAX_NAME_LENGTH]
    return cleaned or None


def _require_aware(value: datetime | None, name: str) -> None:
    if value is not None and value.tzinfo is None:
        raise ValueError(f"{name} harus punya zona waktu (docs/kontrak.md bagian 1)")


def ingest(
    conn: psycopg.Connection,
    case_id: int,
    posts: Iterable[RawPost],
    pseudo: Pseudonymizer,
    *,
    store_names: bool = False,
) -> IngestResult:
    posts = list(posts)
    for post in posts:
        _require_aware(post.posted_at, "posted_at")
        _require_aware(post.account_created_at, "account_created_at")

    result = IngestResult()

    with conn.transaction():
        # Kunci baris kasus: penomoran label akun tidak boleh balapan dengan proses lain.
        conn.execute("SELECT id FROM cases WHERE id = %s FOR UPDATE", (case_id,))

        existing = {
            row["account_key"]: row["id"]
            for row in conn.execute("SELECT account_key, id FROM accounts WHERE case_id = %s", (case_id,))
        }
        next_label = len(existing) + 1

        # Label diberikan menurut urutan post pertama, bukan urutan data masuk,
        # supaya label tidak membocorkan pengelompokan (misalnya klaster sintetis).
        first_seen: dict[tuple[str, str], RawPost] = {}
        for post in sorted(posts, key=lambda p: (p.posted_at, p.post_id)):
            first_seen.setdefault((post.platform, post.author_id), post)

        new_accounts = []
        for (platform, author_id), post in first_seen.items():
            key = pseudo.key_for(platform, author_id)
            if key in existing:
                continue
            new_accounts.append(
                (
                    case_id, platform, key, f"Akun-{next_label:04d}", post.account_created_at, post.followers, post.following,
                    clean_name(post.author_name) if store_names else None,
                )
            )
            existing[key] = None
            next_label += 1

        if new_accounts:
            with conn.cursor() as cur:
                cur.executemany(
                    """
                    INSERT INTO accounts (case_id, platform, account_key, display_label, account_created_at, followers, following, display_name)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                    ON CONFLICT (case_id, platform, account_key) DO NOTHING
                    """,
                    new_accounts,
                )
            result.accounts_inserted = len(new_accounts)

        ids_by_key = {
            row["account_key"]: row["id"]
            for row in conn.execute("SELECT account_key, id FROM accounts WHERE case_id = %s", (case_id,))
        }
        for platform, author_id in first_seen:
            result.account_ids[author_id] = ids_by_key[pseudo.key_for(platform, author_id)]

        before = conn.execute("SELECT count(*) AS n FROM posts WHERE case_id = %s", (case_id,)).fetchone()["n"]

        rows = []
        for post in posts:
            text = pseudo.mask_mentions(post.platform, post.text)[:MAX_TEXT_LENGTH]
            # Teks dengan @username asli hanya disimpan bila fitur nama menyala (mode lokal) dan
            # memang berbeda dari teks tersamar. Analisis selalu memakai kolom text yang tersamar.
            original = post.text[:MAX_TEXT_LENGTH]
            text_raw = original if store_names and original != text else None
            urls = list(post.urls)[:MAX_URLS] if post.urls else extract_urls(post.text)
            rows.append(
                (
                    case_id,
                    ids_by_key[pseudo.key_for(post.platform, post.author_id)],
                    post.platform,
                    pseudo.key_for(post.platform, post.post_id),
                    text,
                    post.posted_at,
                    pseudo.optional_key(post.platform, post.reply_to_id),
                    pseudo.optional_key(post.platform, post.conversation_id),
                    Jsonb(urls),
                    text_raw,
                )
            )

        if rows:
            with conn.cursor() as cur:
                cur.executemany(
                    """
                    INSERT INTO posts (case_id, account_id, platform, post_key, text, posted_at, reply_to_key, conversation_key, urls, text_raw)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                    ON CONFLICT (case_id, platform, post_key) DO NOTHING
                    """,
                    rows,
                )

        after = conn.execute("SELECT count(*) AS n FROM posts WHERE case_id = %s", (case_id,)).fetchone()["n"]
        result.posts_inserted = after - before
        result.duplicates_skipped = len(rows) - result.posts_inserted

    return result
