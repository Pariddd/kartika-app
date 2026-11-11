"""Data graf untuk tampilan: isi benar, terpangkas, dan bisa diulang."""

from __future__ import annotations

import networkx as nx

from radar.analysis import network
from radar.analysis.model import Params
from radar.analysis.offline import to_analysis_input
from radar.analysis.pipeline import analyze
from radar.synthetic import generate


def demo():
    dataset = generate("standar", 4)
    posts, accounts, _ = to_analysis_input(dataset)
    return analyze(posts, accounts, Params())


def test_semua_anggota_klaster_ditandai_ada_di_graf_dengan_labelnya():
    result = demo()
    nodes = {n["id"]: n for n in result.graph["nodes"]}
    for cluster in (c for c in result.clusters if c.flagged):
        for member in cluster.members:
            assert nodes[member]["cluster"] == cluster.label
            assert nodes[member]["flagged"] is True


def test_garis_hanya_antar_simpul_yang_ada_dan_membawa_jenis_hubungan():
    graph = demo().graph
    ids = {n["id"] for n in graph["nodes"]}
    assert graph["edges"]
    for edge in graph["edges"]:
        assert edge["a"] in ids and edge["b"] in ids and edge["a"] < edge["b"]
        assert edge["text"] or edge["time"]
        assert 0 < edge["w"] <= 2


def test_koordinat_di_dalam_kanvas_dan_hasil_bisa_diulang():
    first, second = demo().graph, demo().graph
    assert first == second
    for node in first["nodes"]:
        assert 0 <= node["x"] <= network.SIZE and 0 <= node["y"] <= network.SIZE


def test_graf_besar_dipangkas_dengan_mendahulukan_anggota_klaster_ditandai():
    g = nx.complete_graph(600)
    nx.set_edge_attributes(g, 1.0, "weight")
    nx.set_edge_attributes(g, True, "text")
    flagged = {n: ("K-01", True) for n in range(500, 600)}
    view = network.build(g, flagged, {}, seed=42)

    assert len(view["nodes"]) == network.MAX_NODES
    assert len(view["edges"]) <= network.MAX_EDGES
    assert view["truncated"] is True and view["total_nodes"] == 600
    assert set(range(500, 600)) <= {n["id"] for n in view["nodes"]}


def test_graf_kosong():
    assert network.build(nx.Graph(), {}, {}, seed=42)["nodes"] == []
