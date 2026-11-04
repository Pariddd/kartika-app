import random
from collections import Counter

from radar.synthetic import PERIOD_HOURS, PERIOD_START, PRESETS, TALKING_POINTS, generate, render

TEMPLATE = TALKING_POINTS["A"][0]


def test_seed_sama_menghasilkan_dataset_identik():
    assert generate("standar", 42).posts == generate("standar", 42).posts


def test_seed_berbeda_menghasilkan_dataset_berbeda():
    assert generate("standar", 1).posts != generate("standar", 2).posts


def test_parafrase_nol_adalah_salin_tempel():
    rng = random.Random(0)
    assert len({render(TEMPLATE, rng, 0.0) for _ in range(50)}) == 1


def test_parafrase_tinggi_menghasilkan_variasi():
    rng = random.Random(0)
    # Template A[0] punya 2 x 2 x 2 = 8 kombinasi alternatif; parafrase 1,0 tidak pernah memakai teks kanonik.
    variants = {render(TEMPLATE, rng, 1.0) for _ in range(200)}
    assert len(variants) == 8
    assert render(TEMPLATE, random.Random(0), 0.0) not in variants


def test_parafrase_kalimat_menambah_variasi_di_luar_slot():
    from radar.synthetic import paraphrase_sentence

    rng = random.Random(0)
    assert len({paraphrase_sentence(TEMPLATE, rng, 0.8) for _ in range(200)}) > 20


def test_preset_standar_sesuai_spesifikasi():
    data = generate("standar", 42)
    spec = PRESETS["standar"]

    assert {label: len(members) for label, members in data.planted.items()} == {
        s.label: s.size for s in spec.planted
    }
    assert len(data.decoys) == spec.decoy_size
    assert 1500 < len(data.posts) < 5000

    planted = {a for members in data.planted.values() for a in members}
    assert planted.isdisjoint(data.decoys)


def test_preset_kontrol_tanpa_klaster_tanaman():
    data = generate("kontrol", 42)
    assert data.planted == {}
    assert len(data.decoys) > 0


def test_id_unik_waktu_berzona_dan_dalam_periode():
    data = generate("standar", 7)
    end = PERIOD_START.timestamp() + PERIOD_HOURS * 3600 + 3600

    assert len({p.post_id for p in data.posts}) == len(data.posts)
    for post in data.posts:
        assert post.posted_at.tzinfo is not None
        assert PERIOD_START.timestamp() <= post.posted_at.timestamp() <= end
        assert post.platform == "synthetic"
        assert post.text.strip()


def test_tanaman_muncul_serentak_di_banyak_kejadian():
    data = generate("standar", 42)
    members = set(data.planted["Tanaman A"])
    # Bucket 30 menit: anggota tanaman A berkumpul di 6 kejadian.
    buckets = Counter(
        int((p.posted_at - PERIOD_START).total_seconds() // 1800)
        for p in data.posts
        if p.author_id in members and "berita-sentosa-terkini" in p.text
    )
    busy = [b for b, n in buckets.items() if n >= len(members) * 0.4]
    assert len(busy) >= 5


def test_akun_tanaman_muda_dibuat_berdekatan():
    data = generate("standar", 42)
    by_author = {p.author_id: p.account_created_at for p in data.posts}
    created = sorted(by_author[a] for a in data.planted["Tanaman A"])
    assert (created[-1] - created[0]).days <= 14


def test_tidak_ada_domain_sungguhan():
    data = generate("standar", 42)
    for post in data.posts:
        for url in post.urls:
            assert url.split("/")[2].endswith(".example")
