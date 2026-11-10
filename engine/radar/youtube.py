"""Adapter YouTube Data API v3: komentar dan balasan dari video tertentu.

- Hanya field yang dibutuhkan analisis yang dipakai: ID kanal penulis (langsung
  di-HMAC oleh ingest), ID komentar, teks, waktu, dan ID induk balasan.
  Foto dan URL kanal penulis tidak pernah dibaca.
- Nama tampilan penulis hanya dibaca bila include_names=True (RADAR_STORE_ACCOUNT_NAMES
  di mode lokal). Bawaannya tidak dibaca.
- API key tidak pernah ditulis ke log atau pesan error.
- Data mentah hanya hidup di memori, tidak disimpan ke disk.
"""

from __future__ import annotations

import json
import time
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass, field
from datetime import datetime
from typing import Any, Callable

from radar.errors import ConfigError, JobError
from radar.ingest import RawPost

API = "https://www.googleapis.com/youtube/v3"
PLATFORM = "youtube"
PAGE_SIZE = 100
TIMEOUT_SECONDS = 20
RETRIES = 2

# fetch(url) -> (status HTTP, isi JSON). Bisa diganti di test.
Fetch = Callable[[str], "tuple[int, dict[str, Any]]"]


def _default_fetch(url: str) -> tuple[int, dict[str, Any]]:
    request = urllib.request.Request(url, headers={"User-Agent": "RadarNarasi/0.1", "Accept": "application/json"})
    try:
        with urllib.request.urlopen(request, timeout=TIMEOUT_SECONDS) as response:
            return response.status, json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as error:
        try:
            body = json.loads(error.read().decode("utf-8"))
        except (ValueError, UnicodeDecodeError):
            body = {}
        return error.code, body


def _reason(body: dict[str, Any]) -> str:
    errors = body.get("error", {}).get("errors") or [{}]
    return str(errors[0].get("reason", ""))


@dataclass
class CollectResult:
    posts: list[RawPost] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)
    requests: int = 0
    skipped_without_author: int = 0


class YouTubeClient:
    def __init__(
        self,
        api_key: str,
        fetch: Fetch | None = None,
        sleep: Callable[[float], None] = time.sleep,
        *,
        include_names: bool = False,
    ) -> None:
        if not api_key:
            raise ConfigError("YOUTUBE_API_KEY di engine/.env wajib diisi untuk kasus YouTube")
        self._key = api_key
        self._fetch = fetch or _default_fetch
        self._sleep = sleep
        self._include_names = include_names
        self.requests = 0

    def _get(self, resource: str, params: dict[str, Any]) -> tuple[int, dict[str, Any]]:
        url = f"{API}/{resource}?" + urllib.parse.urlencode({**params, "key": self._key})
        for attempt in range(RETRIES + 1):
            self.requests += 1
            try:
                status, body = self._fetch(url)
            except (urllib.error.URLError, TimeoutError, OSError, ValueError):
                status, body = 0, {}
            if status == 0 or status >= 500:
                if attempt < RETRIES:
                    self._sleep(2 ** attempt)
                    continue
                raise JobError("YouTube API tidak bisa dihubungi; periksa koneksi internet lalu coba lagi")
            return status, body
        raise AssertionError("tidak tercapai")

    def _check(self, status: int, body: dict[str, Any]) -> None:
        reason = _reason(body)
        if status == 200:
            return
        if reason in {"quotaExceeded", "dailyLimitExceeded", "rateLimitExceeded"}:
            raise JobError("kuota YouTube Data API habis; coba lagi besok (kuota direset tengah malam waktu Pasifik)")
        if status == 400 and reason in {"keyInvalid", "badRequest"}:
            raise ConfigError("YOUTUBE_API_KEY ditolak Google (tidak valid atau salah salin)")
        if status == 403 and reason in {"accessNotConfigured", "forbidden", "ipRefererBlocked"}:
            raise ConfigError("API key tidak diizinkan memakai YouTube Data API v3; aktifkan API-nya di Google Cloud Console")
        raise JobError(f"YouTube API mengembalikan status {status} ({reason or 'tanpa keterangan'})")

    def collect(self, video_ids: list[str], max_items: int) -> CollectResult:
        result = CollectResult()

        for video_id in video_ids:
            if len(result.posts) >= max_items:
                break
            page_token: str | None = None
            while len(result.posts) < max_items:
                params: dict[str, Any] = {
                    "part": "snippet,replies",
                    "videoId": video_id,
                    "maxResults": PAGE_SIZE,
                    "order": "time",
                    "textFormat": "plainText",
                }
                if page_token:
                    params["pageToken"] = page_token
                status, body = self._get("commentThreads", params)

                reason = _reason(body)
                if status == 403 and reason == "commentsDisabled":
                    result.warnings.append(f"komentar dinonaktifkan pada video {video_id}")
                    break
                if status == 404:
                    result.warnings.append(f"video {video_id} tidak ditemukan atau privat")
                    break
                self._check(status, body)

                for thread in body.get("items", []):
                    if len(result.posts) >= max_items:
                        break
                    top = thread.get("snippet", {}).get("topLevelComment", {})
                    thread_id = thread.get("id") or top.get("id")
                    self._add(result, top, thread_id, None)

                    included = thread.get("replies", {}).get("comments", [])
                    total_replies = int(thread.get("snippet", {}).get("totalReplyCount", 0))
                    replies = included if total_replies <= len(included) else self._replies(thread_id, max_items - len(result.posts))
                    for reply in replies:
                        if len(result.posts) >= max_items:
                            break
                        self._add(result, reply, thread_id, thread_id)

                page_token = body.get("nextPageToken")
                if not page_token:
                    break

        result.requests = self.requests
        return result

    def _replies(self, parent_id: str, budget: int) -> list[dict[str, Any]]:
        """Semua balasan sebuah thread (commentThreads hanya menyertakan maksimal 5)."""
        replies: list[dict[str, Any]] = []
        page_token: str | None = None
        while len(replies) < budget:
            params: dict[str, Any] = {"part": "snippet", "parentId": parent_id, "maxResults": PAGE_SIZE, "textFormat": "plainText"}
            if page_token:
                params["pageToken"] = page_token
            status, body = self._get("comments", params)
            self._check(status, body)
            replies.extend(body.get("items", []))
            page_token = body.get("nextPageToken")
            if not page_token:
                break
        return replies

    def _add(self, result: CollectResult, comment: dict[str, Any], thread_id: str | None, parent_id: str | None) -> None:
        snippet = comment.get("snippet", {})
        author = (snippet.get("authorChannelId") or {}).get("value")
        # textDisplay (dengan textFormat=plainText) selalu tersedia; textOriginal tidak dijamin untuk API key.
        text = snippet.get("textDisplay") or snippet.get("textOriginal") or ""
        published = snippet.get("publishedAt")
        if not author or not comment.get("id") or not published or not text.strip():
            result.skipped_without_author += 1
            return
        result.posts.append(
            RawPost(
                platform=PLATFORM,
                author_id=author,
                post_id=comment["id"],
                text=text,
                posted_at=datetime.fromisoformat(published.replace("Z", "+00:00")),
                reply_to_id=parent_id,
                conversation_id=thread_id,
                author_name=snippet.get("authorDisplayName") if self._include_names else None,
            )
        )
