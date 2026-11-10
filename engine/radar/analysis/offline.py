"""Menjalankan analisis langsung atas dataset sintetis, tanpa database.
Dipakai untuk kalibrasi ambang, test, dan kurva ketahanan."""

from __future__ import annotations

from radar.analysis.model import Account, Post
from radar.synthetic import SyntheticDataset


def to_analysis_input(dataset: SyntheticDataset) -> tuple[list[Post], dict[int, Account], dict[str, int]]:
    account_ids: dict[str, int] = {}
    accounts: dict[int, Account] = {}
    posts: list[Post] = []
    for number, raw in enumerate(sorted(dataset.posts, key=lambda p: (p.posted_at, p.post_id)), start=1):
        if raw.author_id not in account_ids:
            account_ids[raw.author_id] = len(account_ids) + 1
            accounts[account_ids[raw.author_id]] = Account(account_ids[raw.author_id], raw.account_created_at)
        posts.append(Post(number, account_ids[raw.author_id], raw.text, raw.posted_at, raw.urls))
    return posts, accounts, account_ids
