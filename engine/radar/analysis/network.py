"""Data graf koordinasi untuk ditampilkan (tabel case_graphs, docs/kontrak.md).

Simpul = akun, garis = hubungan teks dan/atau waktu yang dipakai membentuk klaster.
Tata letak dihitung di sini dengan seed tetap, sehingga gambar yang sama muncul setiap
kali dibuka dan browser tidak perlu menjalankan simulasi.

Graf dipangkas agar tetap terbaca dan ringan: tiap akun hanya membawa beberapa hubungan
terkuatnya, dan jumlah simpul dibatasi dengan mendahulukan anggota klaster.
"""

from __future__ import annotations

from typing import Any

import networkx as nx

MAX_NODES = 400
MAX_EDGES = 4000
EDGES_PER_NODE = 8
SIZE = 1000  # koordinat 0..SIZE


def build(
    graph: nx.Graph,
    cluster_of: dict[int, tuple[str, bool]],
    post_counts: dict[int, int],
    seed: int,
) -> dict[str, Any]:
    """cluster_of: akun -> (label klaster, ditandai?) untuk akun yang masuk klaster."""
    if graph.number_of_edges() == 0:
        return {"nodes": [], "edges": [], "total_nodes": 0, "total_edges": 0, "truncated": False}

    # Simpul: anggota klaster ditandai dulu, lalu klaster lain, lalu akun lepas dengan hubungan terkuat.
    strength = dict(graph.degree(weight="weight"))

    def priority(node: int) -> tuple[int, float, int]:
        label = cluster_of.get(node)
        rank = 0 if label and label[1] else 1 if label else 2
        return (rank, -strength[node], node)

    kept = set(sorted(graph.nodes, key=priority)[:MAX_NODES])

    # Garis: tiap simpul mempertahankan EDGES_PER_NODE hubungan terkuatnya.
    chosen: set[tuple[int, int]] = set()
    for node in kept:
        neighbors = sorted(
            (n for n in graph[node] if n in kept),
            key=lambda n: (-graph[node][n]["weight"], n),
        )[:EDGES_PER_NODE]
        chosen.update((min(node, n), max(node, n)) for n in neighbors)
    edges = sorted(chosen, key=lambda e: (-graph[e[0]][e[1]]["weight"], e))[:MAX_EDGES]

    layout_graph = nx.Graph()
    layout_graph.add_nodes_from(sorted(kept))
    layout_graph.add_weighted_edges_from((a, b, graph[a][b]["weight"]) for a, b in edges)
    positions = _layout(layout_graph, cluster_of, seed)

    degree = dict(layout_graph.degree())
    nodes = [
        {
            "id": node,
            "x": positions[node][0],
            "y": positions[node][1],
            "cluster": cluster_of[node][0] if node in cluster_of else None,
            "flagged": bool(cluster_of[node][1]) if node in cluster_of else False,
            "posts": post_counts.get(node, 0),
            "links": degree[node],
        }
        for node in sorted(kept)
    ]
    return {
        "nodes": nodes,
        "edges": [
            {
                "a": a,
                "b": b,
                "text": bool(graph[a][b].get("text")),
                "time": bool(graph[a][b].get("time")),
                "w": round(float(graph[a][b]["weight"]), 3),
            }
            for a, b in edges
        ],
        "total_nodes": graph.number_of_nodes(),
        "total_edges": graph.number_of_edges(),
        "truncated": graph.number_of_nodes() > len(kept) or graph.number_of_edges() > len(edges),
    }


def _layout(graph: nx.Graph, cluster_of: dict[int, tuple[str, bool]], seed: int) -> dict[int, tuple[int, int]]:
    """Tata letak dua tingkat: kelompok ditempatkan dulu, lalu akun di dalam kelompoknya.

    Kelompok = klaster hasil analisis; akun tanpa klaster menjadi kelompok tunggal.
    Dengan begitu tiap klaster tampak sebagai satu gumpalan terpisah, dan jarak antargumpalan
    mencerminkan seberapa banyak hubungan di antara mereka.
    """
    group_of = {n: (cluster_of[n][0] if n in cluster_of else f"_{n}") for n in graph.nodes}
    groups: dict[str, list[int]] = {}
    for node in sorted(graph.nodes):
        groups.setdefault(group_of[node], []).append(node)

    meta = nx.Graph()
    meta.add_nodes_from(sorted(groups))
    for a, b, data in graph.edges(data=True):
        ga, gb = group_of[a], group_of[b]
        if ga != gb:
            previous = meta[ga][gb]["weight"] if meta.has_edge(ga, gb) else 0.0
            meta.add_edge(ga, gb, weight=previous + float(data.get("weight", 1.0)))

    radius = {g: 1.0 + 1.5 * (len(members) ** 0.5) for g, members in groups.items()}
    spread = sum(r * r for r in radius.values()) ** 0.5 * 1.3
    centers = {
        g: [float(x) * spread, float(y) * spread]
        for g, (x, y) in nx.spring_layout(meta, weight="weight", seed=seed, iterations=80, k=2.0 / max(1, len(groups)) ** 0.5).items()
    } if len(groups) > 1 else {next(iter(groups)): [0.0, 0.0]}

    # Dorong kelompok yang bertumpuk sampai tidak saling menutupi.
    order = sorted(groups)
    for _ in range(200):
        moved = False
        for i, ga in enumerate(order):
            for gb in order[i + 1:]:
                dx = centers[gb][0] - centers[ga][0]
                dy = centers[gb][1] - centers[ga][1]
                distance = (dx * dx + dy * dy) ** 0.5
                need = radius[ga] + radius[gb] + 0.6
                if distance < need:
                    if distance < 1e-6:
                        dx, dy, distance = 1.0, 0.0, 1.0
                    push = (need - distance) / 2
                    # Kelompok kecil mengalah lebih banyak daripada kelompok besar.
                    share_a = radius[gb] / (radius[ga] + radius[gb])
                    centers[ga][0] -= dx / distance * push * 2 * share_a
                    centers[ga][1] -= dy / distance * push * 2 * share_a
                    centers[gb][0] += dx / distance * push * 2 * (1 - share_a)
                    centers[gb][1] += dy / distance * push * 2 * (1 - share_a)
                    moved = True
        if not moved:
            break

    raw: dict[int, tuple[float, float]] = {}
    for g, members in groups.items():
        cx, cy = centers[g]
        if len(members) == 1:
            raw[members[0]] = (cx, cy)
            continue
        local = nx.spring_layout(graph.subgraph(members), weight="weight", seed=seed, iterations=60, k=1.6 / len(members) ** 0.5)
        extent = max(max(abs(float(x)), abs(float(y))) for x, y in local.values()) or 1.0
        for node, (x, y) in local.items():
            raw[node] = (cx + float(x) / extent * radius[g] * 0.9, cy + float(y) / extent * radius[g] * 0.9)

    xs = [p[0] for p in raw.values()]
    ys = [p[1] for p in raw.values()]
    span = max(max(xs) - min(xs), max(ys) - min(ys)) or 1.0
    margin = 40
    scale = (SIZE - 2 * margin) / span
    # Rata tengah pada sumbu yang lebih pendek.
    off_x = margin + ((SIZE - 2 * margin) - (max(xs) - min(xs)) * scale) / 2
    off_y = margin + ((SIZE - 2 * margin) - (max(ys) - min(ys)) * scale) / 2
    return {
        node: (int(round(off_x + (x - min(xs)) * scale)), int(round(off_y + (y - min(ys)) * scale)))
        for node, (x, y) in raw.items()
    }
