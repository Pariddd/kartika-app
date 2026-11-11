"""Menjalankan seluruh analisis atas data di memori (tanpa database)."""

from __future__ import annotations

from collections import defaultdict

from radar.analysis import clustering, coactivity, network, signals, text
from radar.analysis.model import Account, AnalysisResult, ClusterResult, Params, Post


def analyze(posts: list[Post], accounts: dict[int, Account], params: Params) -> AnalysisResult:
    warnings: list[str] = []
    if not posts:
        return AnalysisResult([], 0, 0, len(accounts), ["tidak ada post untuk dianalisis"])

    posts_by_account: dict[int, list[Post]] = defaultdict(list)
    for post in posts:
        posts_by_account[post.account_id].append(post)
    post_account = {p.id: p.account_id for p in posts}

    pairs, eligible = text.similar_pairs(posts, params)
    co_counts, by_window, skipped = coactivity.co_events(posts, params)
    if skipped:
        warnings.append(f"{skipped} jendela waktu sangat ramai dilewati untuk sinyal waktu")

    graph = clustering.build_graph(pairs, post_account, co_counts, params)
    groups = clustering.clusters(graph, params)

    total_accounts = len(posts_by_account)
    text_ctx = signals.TextContext(posts, pairs, total_accounts)
    time_linked = signals.co_linked(co_counts, params)
    time_base = signals._global_density(time_linked, total_accounts)
    if not any(a.created_at for a in accounts.values()):
        warnings.append("sinyal profil tidak tersedia: data tanggal pembuatan akun tidak ada (misalnya data YouTube)")
    users_by_domain = signals.domain_accounts(posts)

    results: list[ClusterResult] = []
    for members in groups:
        sig = [
            signals.text_signal(members, text_ctx),
            signals.time_signal(members, time_linked, time_base, by_window, params),
            signals.profile_signal(members, accounts),
            signals.infra_signal(members, users_by_domain, total_accounts),
        ]
        active = sum(1 for s in sig if s.active)
        # Skor koordinasi = rata-rata kekuatan keempat sinyal (0-1).
        score = round(sum(s.strength for s in sig) / len(sig), 3)
        results.append(ClusterResult(members=members, signals=sig, flagged=active >= params.min_signals, score=score))

    results.sort(key=lambda c: (not c.flagged, -c.score, -len(c.members)))
    for number, cluster in enumerate(results, start=1):
        cluster.label = f"K-{number:02d}"

    flagged_members = {a for c in results if c.flagged for a in c.members}
    coordinated = sum(len(posts_by_account[a]) for a in flagged_members)

    cluster_of = {account: (c.label, c.flagged) for c in results for account in c.members}
    post_counts = {account: len(items) for account, items in posts_by_account.items()}
    view = network.build(graph, cluster_of, post_counts, params.seed)

    return AnalysisResult(results, len(posts), coordinated, len(posts_by_account), warnings, view)
