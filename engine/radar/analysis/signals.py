"""Empat sinyal independen per klaster, masing-masing dibandingkan dengan baseline
seluruh dataset. Ambang di sini adalah titik awal dan dikalibrasi dengan data sintetis."""

from __future__ import annotations

from collections import Counter, defaultdict
from datetime import datetime, timezone
from itertools import combinations
from typing import Any
from urllib.parse import urlsplit

from radar.analysis.model import Account, Params, Post, SignalResult
from radar.analysis.text import SimilarPair

# Kepadatan pasangan = proporsi pasangan akun dalam klaster yang punya bukti (teks/waktu).
# Lift = kepadatan klaster dibagi kepadatan seluruh dataset.
MIN_PAIR_DENSITY = 0.3
MIN_LIFT = 5.0
PROFILE_MIN_COVERAGE = 0.8
PROFILE_WINDOW_DAYS = 30
PROFILE_MIN_SHARE = 0.5
# Dukungan minimum absolut: persentase dari klaster sangat kecil tidak bermakna
# (2 dari 3 akun yang kebetulan dibuat berdekatan sudah "67%").
PROFILE_MIN_ACCOUNTS = 5
INFRA_MIN_ACCOUNTS = 3
INFRA_MIN_MEMBER_SHARE = 0.3
INFRA_MAX_OTHER_SHARE = 0.02


def _iso(ts: datetime) -> str:
    return ts.astimezone(timezone.utc).isoformat().replace("+00:00", "Z")


# ------------------------------------------------------------------ teks


def _pair_density(members: set[int], linked: set[tuple[int, int]]) -> tuple[float, int]:
    n = len(members)
    if n < 2:
        return 0.0, 0
    hits = sum(1 for a, b in combinations(sorted(members), 2) if (a, b) in linked)
    return hits / (n * (n - 1) / 2), hits


def _global_density(linked: set[tuple[int, int]], total_accounts: int) -> float:
    possible = total_accounts * (total_accounts - 1) / 2
    return len(linked) / possible if possible else 0.0


def _lift(density: float, baseline: float) -> float:
    return density / baseline if baseline > 0 else float("inf")


class TextContext:
    """Prakomputasi: pasangan akun yang saling menulis teks mirip (minimal satu pasangan post)."""

    def __init__(self, posts: list[Post], pairs: list[SimilarPair], total_accounts: int) -> None:
        self.post_account = {p.id: p.account_id for p in posts}
        self.pairs = pairs
        self.linked: set[tuple[int, int]] = set()
        for pair in pairs:
            a, b = self.post_account[pair.post_a], self.post_account[pair.post_b]
            self.linked.add((min(a, b), max(a, b)))
        self.baseline = _global_density(self.linked, total_accounts)


def text_signal(members: set[int], ctx: TextContext) -> SignalResult:
    density, _ = _pair_density(members, ctx.linked)
    inside = [
        p for p in ctx.pairs
        if ctx.post_account[p.post_a] in members and ctx.post_account[p.post_b] in members
    ]
    top = sorted(inside, key=lambda p: (-p.score, p.post_a))[:5]
    account_of = ctx.post_account

    return SignalResult(
        "text",
        active=density >= MIN_PAIR_DENSITY and _lift(density, ctx.baseline) >= MIN_LIFT,
        strength=round(density, 3),
        evidence={
            "pair_ratio": round(density, 3),
            "baseline_ratio": round(ctx.baseline, 4),
            "similar_post_pairs": len(inside),
            "top_pairs": [{"post_a": p.post_a, "post_b": p.post_b, "method": p.method, "score": p.score} for p in top],
            "narratives": narratives(inside, account_of, len(members)),
        },
    )


MAX_NARRATIVES = 3


def narratives(pairs: list[SimilarPair], post_account: dict[int, int], cluster_size: int) -> list[dict[str, Any]]:
    """Narasi utama klaster: kelompok post yang saling mirip, diurutkan menurut jumlah akun yang menulisnya.

    Tiap kelompok diwakili satu post yang mirip dengan paling banyak post lain di kelompoknya
    (yang paling "tengah"). Tidak ada teks yang dibuat sistem: wakilnya selalu post asli.
    """
    parent: dict[int, int] = {}

    def find(x: int) -> int:
        while parent.setdefault(x, x) != x:
            parent[x] = parent[parent[x]]
            x = parent[x]
        return x

    links: Counter[int] = Counter()
    for pair in pairs:
        parent[find(pair.post_a)] = find(pair.post_b)
        links[pair.post_a] += 1
        links[pair.post_b] += 1

    groups: dict[int, list[int]] = defaultdict(list)
    for post in parent:
        groups[find(post)].append(post)

    found = []
    for posts in groups.values():
        accounts = {post_account[p] for p in posts}
        if len(accounts) < 2:
            continue
        representative = min(posts, key=lambda p: (-links[p], p))
        found.append({
            "post": representative,
            "posts": len(posts),
            "accounts": len(accounts),
            "share": round(len(accounts) / cluster_size, 3) if cluster_size else 0.0,
        })

    found.sort(key=lambda n: (-n["accounts"], -n["posts"], n["post"]))
    return found[:MAX_NARRATIVES]


# ------------------------------------------------------------------ waktu


def co_linked(co_counts: Counter[tuple[int, int]], params: Params) -> set[tuple[int, int]]:
    return {pair for pair, n in co_counts.items() if n >= params.min_co_events}


def time_signal(
    members: set[int],
    linked: set[tuple[int, int]],
    baseline: float,
    by_window: dict[int, set[int]],
    params: Params,
) -> SignalResult:
    density, _ = _pair_density(members, linked)
    involved = {a for pair in linked if pair[0] in members and pair[1] in members for a in pair}

    events = []
    for window, accounts in by_window.items():
        together = len(accounts & members)
        if together >= max(3, int(0.3 * len(members))):
            start = datetime.fromtimestamp(window * params.time_window_seconds, timezone.utc)
            events.append({"start": _iso(start), "accounts": together})
    events.sort(key=lambda e: e["start"])

    return SignalResult(
        "time",
        active=density >= MIN_PAIR_DENSITY
        and _lift(density, baseline) >= MIN_LIFT
        and len(events) >= params.min_co_events,
        strength=round(density, 3),
        evidence={
            "window_seconds": params.time_window_seconds,
            "pair_ratio": round(density, 3),
            "baseline_ratio": round(baseline, 4),
            "events": events[:20],
            "accounts_in_min_events": len(involved),
        },
    )


# ------------------------------------------------------------------ profil akun


def _densest_window(dates: list[datetime], days: int) -> tuple[int, datetime | None]:
    """Jumlah terbanyak tanggal yang jatuh dalam satu jendela `days` hari, dan awal jendelanya."""
    ordered = sorted(dates)
    best, best_start, left = 0, None, 0
    span = days * 86400
    for right, current in enumerate(ordered):
        while current.timestamp() - ordered[left].timestamp() > span:
            left += 1
        if right - left + 1 > best:
            best, best_start = right - left + 1, ordered[left]
    return best, best_start


def profile_signal(members: set[int], accounts: dict[int, Account]) -> SignalResult:
    dates = [accounts[a].created_at for a in members if a in accounts and accounts[a].created_at]
    coverage = len(dates) / len(members)
    all_dates = [a.created_at for a in accounts.values() if a.created_at]

    if coverage < PROFILE_MIN_COVERAGE or len(dates) < 3 or len(all_dates) < 10:
        return SignalResult("profile", active=False, strength=0.0, evidence={"available": False, "coverage": round(coverage, 3)})

    count, start = _densest_window(dates, PROFILE_WINDOW_DAYS)
    end = start.timestamp() + PROFILE_WINDOW_DAYS * 86400
    share = count / len(dates)
    baseline = sum(1 for d in all_dates if start.timestamp() <= d.timestamp() <= end) / len(all_dates)

    return SignalResult(
        "profile",
        active=count >= PROFILE_MIN_ACCOUNTS and share >= PROFILE_MIN_SHARE and _lift(share, baseline) >= MIN_LIFT,
        strength=round(share, 3),
        evidence={
            "available": True,
            "window_days": PROFILE_WINDOW_DAYS,
            "window_start": _iso(start),
            "accounts_in_window": count,
            "member_share": round(share, 3),
            "baseline_share": round(baseline, 3),
        },
    )


# ------------------------------------------------------------------ infrastruktur (OSINT konten)


def domain_of(url: str) -> str | None:
    try:
        host = (urlsplit(url).hostname or "").lower()
    except ValueError:
        return None
    return host[4:] if host.startswith("www.") else host or None


def domain_accounts(posts: list[Post]) -> dict[str, set[int]]:
    users: dict[str, set[int]] = defaultdict(set)
    for post in posts:
        for url in post.urls:
            domain = domain_of(url)
            if domain:
                users[domain].add(post.account_id)
    return users


def infra_signal(members: set[int], users_by_domain: dict[str, set[int]], total_accounts: int) -> SignalResult:
    others_total = max(total_accounts - len(members), 1)
    domains = []
    for domain, users in users_by_domain.items():
        inside = len(users & members)
        if inside == 0:
            continue
        member_share = inside / len(members)
        other_share = len(users - members) / others_total
        domains.append(
            {
                "domain": domain,
                "accounts": inside,
                "member_share": round(member_share, 3),
                "other_share": round(other_share, 3),
                "distinctive": inside >= INFRA_MIN_ACCOUNTS
                and member_share >= INFRA_MIN_MEMBER_SHARE
                and other_share <= INFRA_MAX_OTHER_SHARE,
                # Diisi lewat RDAP (tier Bonus); kosong bila belum diperiksa.
                "age_days_at_first_post": None,
            }
        )
    domains.sort(key=lambda d: (-d["distinctive"], -d["accounts"]))
    distinctive = [d for d in domains if d["distinctive"]]

    return SignalResult(
        "infra",
        active=bool(distinctive),
        strength=round(max((d["member_share"] for d in distinctive), default=0.0), 3),
        evidence={"domains": domains[:5]},
    )
