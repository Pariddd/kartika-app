"""Louvain yang dijamin berhenti.

Implementasi networkx (3.4) bisa berputar tanpa henti: dua simpul bolak-balik pindah
komunitas karena selisih modularitas sebesar galat pembulatan (~1e-17) dianggap perbaikan.
Kasus itu muncul saat uji ketahanan (seed 6, sebaran 3.600 detik) dan akan membuat worker
macet pada data nyata. Di sini sebuah perpindahan hanya diterima bila perbaikannya melebihi
MIN_GAIN, dan jumlah sapuan per tingkat dibatasi, sehingga algoritma selalu selesai.

Graf tak berarah dan berbobot. Hasil bisa diulang untuk seed yang sama.
"""

from __future__ import annotations

import random
from collections import defaultdict
from typing import Hashable

import networkx as nx

MIN_GAIN = 1e-9
MAX_SWEEPS = 100
MAX_LEVELS = 50


def louvain_communities(graph: nx.Graph, weight: str = "weight", seed: int = 42, resolution: float = 1.0) -> list[set[Hashable]]:
    rng = random.Random(seed)
    # Urutan simpul dibuat tetap lebih dulu supaya hasil tidak bergantung pada urutan penyisipan.
    nodes = sorted(graph.nodes)
    members: dict[int, set[Hashable]] = {i: {node} for i, node in enumerate(nodes)}
    index = {node: i for i, node in enumerate(nodes)}

    adjacency: dict[int, dict[int, float]] = {i: {} for i in members}
    loops: dict[int, float] = defaultdict(float)
    for u, v, data in graph.edges(data=True):
        w = float(data.get(weight, 1.0))
        a, b = index[u], index[v]
        if a == b:
            loops[a] += w
        else:
            adjacency[a][b] = adjacency[a].get(b, 0.0) + w
            adjacency[b][a] = adjacency[b].get(a, 0.0) + w

    total = sum(loops.values()) + sum(sum(n.values()) for n in adjacency.values()) / 2
    if total <= 0:
        return [set(m) for m in members.values()]

    for _ in range(MAX_LEVELS):
        community, moved = _one_level(adjacency, loops, total, rng, resolution)
        if not moved:
            break
        adjacency, loops, members = _aggregate(adjacency, loops, members, community)

    return [set(m) for m in members.values()]


def _one_level(
    adjacency: dict[int, dict[int, float]],
    loops: dict[int, float],
    total: float,
    rng: random.Random,
    resolution: float,
) -> tuple[dict[int, int], bool]:
    community = {node: node for node in adjacency}
    degree = {node: sum(nbrs.values()) + 2 * loops.get(node, 0.0) for node, nbrs in adjacency.items()}
    community_degree = dict(degree)

    order = sorted(adjacency)
    rng.shuffle(order)
    moved_any = False

    for _ in range(MAX_SWEEPS):
        moves = 0
        for node in order:
            current = community[node]
            links: dict[int, float] = defaultdict(float)
            for neighbor, w in adjacency[node].items():
                links[community[neighbor]] += w

            k = degree[node]
            community_degree[current] -= k
            # Perbaikan modularitas bila simpul (yang sudah dilepas) dimasukkan ke komunitas c.
            best, best_gain = current, links.get(current, 0.0) / total - resolution * community_degree[current] * k / (2 * total**2)
            for candidate in sorted(links):
                if candidate == current:
                    continue
                gain = links[candidate] / total - resolution * community_degree[candidate] * k / (2 * total**2)
                if gain > best_gain + MIN_GAIN:
                    best, best_gain = candidate, gain
            community_degree[best] += k
            if best != current:
                community[node] = best
                moves += 1
        if moves == 0:
            break
        moved_any = True

    return community, moved_any


def _aggregate(
    adjacency: dict[int, dict[int, float]],
    loops: dict[int, float],
    members: dict[int, set[Hashable]],
    community: dict[int, int],
) -> tuple[dict[int, dict[int, float]], dict[int, float], dict[int, set[Hashable]]]:
    relabel = {c: i for i, c in enumerate(sorted(set(community.values())))}
    new_members: dict[int, set[Hashable]] = {i: set() for i in relabel.values()}
    new_adjacency: dict[int, dict[int, float]] = {i: {} for i in relabel.values()}
    new_loops: dict[int, float] = defaultdict(float)

    for node, com in community.items():
        a = relabel[com]
        new_members[a] |= members[node]
        new_loops[a] += loops.get(node, 0.0)
        for neighbor, w in adjacency[node].items():
            b = relabel[community[neighbor]]
            if a == b:
                new_loops[a] += w / 2  # tiap garis dalam komunitas terhitung dari dua ujungnya
            else:
                new_adjacency[a][b] = new_adjacency[a].get(b, 0.0) + w

    return new_adjacency, new_loops, new_members
