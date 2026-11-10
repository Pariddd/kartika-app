"""Louvain buatan sendiri: benar pada graf yang jawabannya jelas, bisa diulang, dan selalu berhenti."""

from __future__ import annotations

import random
from dataclasses import asdict

import networkx as nx

from radar.analysis.louvain import louvain_communities
from radar.analysis.model import Params
from radar.analysis.offline import to_analysis_input
from radar.analysis.pipeline import analyze
from radar.synthetic import PlantedSpec, Preset, generate_from


def two_cliques() -> nx.Graph:
    g = nx.Graph()
    for group in (range(0, 6), range(10, 16)):
        nodes = list(group)
        g.add_weighted_edges_from((a, b, 1.0) for i, a in enumerate(nodes) for b in nodes[i + 1:])
    g.add_edge(0, 10, weight=0.1)
    return g


def test_dua_klik_yang_terhubung_lemah_dipisah():
    found = sorted(sorted(c) for c in louvain_communities(two_cliques(), seed=42))
    assert found == [list(range(0, 6)), list(range(10, 16))]


def test_hasil_sama_untuk_seed_yang_sama_dan_tidak_bergantung_urutan_penyisipan():
    g = two_cliques()
    shuffled = nx.Graph()
    edges = list(g.edges(data=True))
    random.Random(1).shuffle(edges)
    shuffled.add_edges_from(edges)
    key = lambda cs: sorted(sorted(c) for c in cs)  # noqa: E731
    assert key(louvain_communities(g, seed=7)) == key(louvain_communities(shuffled, seed=7))


def test_setiap_simpul_masuk_tepat_satu_komunitas():
    g = nx.gnm_random_graph(200, 900, seed=3)
    for u, v in g.edges:
        g[u][v]["weight"] = 1 / 6 + (u * v % 5) / 6
    communities = louvain_communities(g, seed=42)
    assert sorted(n for c in communities for n in c) == sorted(g.nodes)


def test_modularitas_setara_dengan_networkx_pada_graf_acak():
    g = nx.planted_partition_graph(6, 25, 0.5, 0.02, seed=5)
    ours = nx.community.modularity(g, louvain_communities(g, seed=42))
    theirs = nx.community.modularity(g, nx.community.louvain_communities(g, seed=42))
    assert ours >= theirs - 0.02


def test_graf_tanpa_garis_dan_bobot_nol_tidak_error():
    g = nx.Graph()
    g.add_nodes_from([1, 2, 3])
    assert len(louvain_communities(g)) == 3


def test_kasus_yang_membuat_networkx_berputar_tanpa_henti_kini_selesai():
    # Seed 6 dengan sebaran 3.600 detik: Louvain networkx 3.4 tidak pernah selesai di sini.
    base = PlantedSpec("Tanaman A", 25, 5, 0.3, 3600, False, None)
    dataset = generate_from(Preset(900, (PlantedSpec(**asdict(base)),), 12), 6)
    posts, accounts, _ = to_analysis_input(dataset)
    result = analyze(posts, accounts, Params())
    assert result.total_posts == len(posts)
