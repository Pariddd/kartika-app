from datetime import datetime, timedelta, timezone

import pytest

from radar import synthetic
from radar.analysis import signals
from radar.analysis.evaluate import evaluate, jaccard
from radar.analysis.model import Account, Params, Post
from radar.analysis.offline import to_analysis_input
from radar.analysis.pipeline import analyze
from radar.analysis.text import normalize
from radar.errors import PayloadError


def run(preset, seed):
    data = synthetic.generate(preset, seed)
    posts, accounts, ids = to_analysis_input(data)
    planted = {label: {ids[a] for a in members} for label, members in data.planted.items()}
    decoys = {ids[a] for a in data.decoys}
    return analyze(posts, accounts, Params()), planted, decoys


# Seed 4 dan 5 tidak dipakai saat menyetel ambang (kalibrasi memakai seed 1-3).
@pytest.mark.parametrize("seed", [4, 5])
def test_standar_semua_tanaman_tertangkap_tanpa_salah_tanda(seed):
    result, planted, decoys = run("standar", seed)
    flagged = [c.members for c in result.flagged]

    ev = evaluate(flagged, planted)
    assert ev.recall == 1.0
    assert ev.precision == 1.0
    assert not any(len(c & decoys) / len(c) > 0.5 for c in flagged)


@pytest.mark.parametrize("seed", [4, 5])
def test_kontrol_organik_tidak_ada_yang_ditandai(seed):
    result, _, _ = run("kontrol", seed)
    assert result.flagged == []
    assert result.organic_score == 1.0


def test_pengecoh_sah_membentuk_klaster_tetapi_tidak_ditandai():
    result, _, decoys = run("standar", 4)
    decoy_clusters = [c for c in result.clusters if len(c.members & decoys) / len(c.members) > 0.5]

    assert decoy_clusters, "pengecoh seharusnya tetap terlihat sebagai klaster untuk ditinjau"
    for cluster in decoy_clusters:
        assert not cluster.flagged
        assert cluster.signals_active < 2


def test_label_klaster_berurutan_dan_yang_ditandai_lebih_dulu():
    result, _, _ = run("standar", 4)
    assert [c.label for c in result.clusters] == [f"K-{i:02d}" for i in range(1, len(result.clusters) + 1)]
    flags = [c.flagged for c in result.clusters]
    assert flags == sorted(flags, reverse=True)


def test_skor_keorganikan_sesuai_definisi():
    result, _, _ = run("standar", 4)
    assert result.organic_score == pytest.approx(1 - result.coordinated_posts / result.total_posts)
    assert 0 < result.organic_score < 1


def test_tanpa_tanggal_akun_sinyal_profil_tidak_tersedia_dengan_peringatan():
    data = synthetic.generate("standar", 4)
    posts, accounts, _ = to_analysis_input(data)
    accounts = {i: Account(i, None) for i in accounts}

    result = analyze(posts, accounts, Params())

    assert any("sinyal profil tidak tersedia" in w for w in result.warnings)
    for cluster in result.clusters:
        profile = next(s for s in cluster.signals if s.signal == "profile")
        assert profile.active is False and profile.evidence["available"] is False


def test_klaster_kecil_tidak_cukup_untuk_sinyal_profil():
    created = datetime(2026, 10, 15, tzinfo=timezone.utc)
    accounts = {i: Account(i, created + timedelta(days=i)) for i in (1, 2, 3)}
    accounts.update({i: Account(i, datetime(2015, 1, 1, tzinfo=timezone.utc) + timedelta(days=37 * i)) for i in range(4, 60)})

    result = signals.profile_signal({1, 2, 3}, accounts)

    assert result.evidence["member_share"] == 1.0
    assert result.active is False  # hanya 3 akun, di bawah dukungan minimum


def test_tanpa_post_menghasilkan_peringatan():
    result = analyze([], {}, Params())
    assert result.clusters == [] and result.warnings


def test_normalisasi_teks_membuang_url_mention_dan_tanda_baca():
    assert normalize("Pasar LAMA!! https://a.example/x @u_1a2b3c4d kumuh.") == "pasar lama kumuh"


def test_jaccard_dan_evaluasi():
    assert jaccard({1, 2}, {2, 3}) == pytest.approx(1 / 3)

    ev = evaluate([{1, 2, 3, 4}, {9, 10, 11}], {"A": {1, 2, 3}, "B": {20, 21, 22}})
    assert ev.recall == 0.5 and ev.precision == 0.5 and ev.matched == {"A": (0, 0.75)}


def test_satu_klaster_hanya_dicocokkan_ke_satu_tanaman():
    ev = evaluate([{1, 2, 3, 4, 5, 6}], {"A": {1, 2, 3}, "B": {4, 5, 6}})
    assert ev.recall == 0.5 and ev.precision == 1.0


def test_parameter_payload_divalidasi():
    assert Params.from_payload({"min_signals": 3, "embedding_threshold": 0.85}).min_signals == 3
    assert Params.from_payload({"tfidf_threshold": 1}).tfidf_threshold == 1.0

    for bad in ({"min_signals": 0}, {"min_signals": "2"}, {"tfidf_threshold": 2}, {"seed": 1.5}, {"min_co_events": True}):
        with pytest.raises(PayloadError):
            Params.from_payload(bad)


def test_post_dari_akun_yang_sama_tidak_dianggap_mirip():
    now = datetime(2026, 11, 1, tzinfo=timezone.utc)
    text = "pasar lama sudah tidak layak dipindah demi warga sentosa"
    posts = [Post(i, 1, text, now + timedelta(minutes=i)) for i in range(1, 6)]
    from radar.analysis.text import similar_pairs

    pairs, _ = similar_pairs(posts, Params())
    assert pairs == []
