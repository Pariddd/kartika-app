"""Graf akun dan klaster Louvain.

Garis antar-akun dibentuk dari bukti teks (minimal `min_shared_texts` pasangan post
mirip) dan/atau bukti waktu (minimal `min_co_events` jendela bersama).
"""

from __future__ import annotations

from collections import Counter

import networkx as nx

from radar.analysis.louvain import louvain_communities
from radar.analysis.model import Params
from radar.analysis.text import SimilarPair


def build_graph(
    pairs: list[SimilarPair],
    post_account: dict[int, int],
    co_counts: Counter[tuple[int, int]],
    params: Params,
) -> nx.Graph:
    text_counts: Counter[tuple[int, int]] = Counter()
    for pair in pairs:
        a, b = post_account[pair.post_a], post_account[pair.post_b]
        text_counts[(min(a, b), max(a, b))] += 1

    graph = nx.Graph()
    for key in set(text_counts) | set(co_counts):
        text_n = text_counts.get(key, 0)
        time_n = co_counts.get(key, 0)
        has_text = text_n >= params.min_shared_texts
        has_time = time_n >= params.min_co_events
        if not (has_text or has_time):
            continue
        weight = (min(text_n, 6) / 6 if has_text else 0) + (min(time_n, 6) / 6 if has_time else 0)
        graph.add_edge(*key, weight=weight)
    return graph


def clusters(graph: nx.Graph, params: Params) -> list[set[int]]:
    if graph.number_of_edges() == 0:
        return []
    communities = louvain_communities(graph, weight="weight", seed=params.seed)
    found = [set(c) for c in communities if len(c) >= params.min_cluster_size]
    # Urutan stabil: terbesar dulu, lalu ID akun terkecil.
    return sorted(found, key=lambda c: (-len(c), min(c)))
