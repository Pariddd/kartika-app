"""Ko-aktivitas waktu: pasangan akun yang aktif di jendela waktu yang sama
pada banyak kejadian berbeda. Satu kali muncul bersamaan tidak berarti apa-apa."""

from __future__ import annotations

from collections import Counter, defaultdict
from itertools import combinations

from radar.analysis.model import Params, Post

# Jendela yang terlalu ramai (misalnya lonjakan berita) dilewati: ratusan akun
# aktif bersamaan karena peristiwa nyata, bukan karena koordinasi.
MAX_ACCOUNTS_PER_WINDOW = 150


def windows(posts: list[Post], params: Params) -> dict[int, set[int]]:
    by_window: dict[int, set[int]] = defaultdict(set)
    for post in posts:
        by_window[int(post.posted_at.timestamp()) // params.time_window_seconds].add(post.account_id)
    return by_window


def co_events(posts: list[Post], params: Params) -> tuple[Counter[tuple[int, int]], dict[int, set[int]], int]:
    """Jumlah jendela berbeda tempat setiap pasangan akun aktif bersama."""
    by_window = windows(posts, params)
    counts: Counter[tuple[int, int]] = Counter()
    skipped = 0
    for accounts in by_window.values():
        if len(accounts) < 2:
            continue
        if len(accounts) > MAX_ACCOUNTS_PER_WINDOW:
            skipped += 1
            continue
        for a, b in combinations(sorted(accounts), 2):
            counts[(a, b)] += 1
    return counts, by_window, skipped
