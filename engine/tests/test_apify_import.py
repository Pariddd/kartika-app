"""Impor ekspor Apify. Data uji fiktif; bentuk X sama dengan ekspor asli apidojo/tweet-scraper."""

from __future__ import annotations

import json
from datetime import datetime, timezone

import pytest

from radar import apify_import
from radar.errors import PayloadError

UTC = timezone.utc


def tweet(tid, author_id, text="isi tweet", at="Tue Oct 13 10:55:46 +0000 2026", **extra):
    item = {
        "type": "tweet", "id": tid, "url": f"https://x.com/fiktif/status/{tid}", "text": text, "fullText": text,
        "createdAt": at, "lang": "in", "isReply": False, "conversationId": tid, "isRetweet": False, "isQuote": False,
        "author": {
            "type": "user", "userName": "username_fiktif", "id": author_id, "name": "Nama Fiktif",
            "url": "https://x.com/username_fiktif", "profilePicture": "https://pbs.example/foto.jpg",
            "description": "bio fiktif", "location": "Kota Fiktif", "followers": 120, "following": 80,
            "createdAt": "Sat Apr 25 13:13:52 +0000 2020",
        },
        "entities": {"urls": [{"expanded_url": "https://contoh.example/artikel", "url": "https://t.co/abc"}], "user_mentions": []},
    }
    item.update(extra)
    return item


def write(tmp_path, data):
    path = tmp_path / "ekspor.json"
    path.write_text(json.dumps(data), encoding="utf-8")
    return path


def test_tweet_dipetakan_tanpa_username_bio_lokasi_atau_foto(tmp_path):
    data = [{"noResults": True}, tweet("2109961287283384573", "1253999000111", "Pasar Lama harus dipindah")]
    result = apify_import.read(write(tmp_path, data), "x", 100)

    post = result.posts[0]
    assert post.platform == "x" and post.post_id == "2109961287283384573" and post.author_id == "1253999000111"
    assert post.text == "Pasar Lama harus dipindah"
    assert post.posted_at == datetime(2026, 10, 13, 10, 55, 46, tzinfo=UTC)
    assert post.account_created_at == datetime(2020, 4, 25, 13, 13, 52, tzinfo=UTC)
    assert (post.followers, post.following) == (120, 80)
    assert post.urls == ("https://contoh.example/artikel",)
    assert post.author_name is None
    for private in ("username_fiktif", "bio fiktif", "Kota Fiktif", "pbs.example", "Nama Fiktif"):
        assert private not in repr(post)


def test_nama_dan_username_hanya_bila_diizinkan(tmp_path):
    result = apify_import.read(write(tmp_path, [tweet("1", "2")]), "x", 100, include_names=True)
    assert result.posts[0].author_name == "Nama Fiktif (@username_fiktif)"

    tanpa = apify_import.read(write(tmp_path, [tweet("1", "2")]), "x", 100)
    assert tanpa.posts[0].author_name is None
    assert "username_fiktif" not in repr(tanpa.posts[0])


def test_label_penulis_tanpa_nama_atau_tanpa_username():
    assert apify_import._author_label({"author": {"userName": "@hanya_handle"}}) == "@hanya_handle"
    assert apify_import._author_label({"author": {"name": "Sama", "userName": "sama"}}) == "@sama"
    assert apify_import._author_label({"displayName": "Hanya Nama"}) == "Hanya Nama"
    assert apify_import._author_label({}) is None


def test_balasan_retweet_dan_baris_rusak(tmp_path):
    data = [
        tweet("10", "1", isReply=True, inReplyToId="9", conversationId="9"),
        tweet("11", "2", isRetweet=True),
        tweet("12", "3", text="", fullText=""),
        tweet("13", "4", at="bukan waktu"),
        {"id": "14", "text": "tanpa penulis", "createdAt": "Tue Oct 13 10:55:46 +0000 2026"},
        "bukan objek",
    ]
    result = apify_import.read(write(tmp_path, data), "x", 100)

    assert [p.post_id for p in result.posts] == ["10"]
    assert result.posts[0].reply_to_id == "9" and result.posts[0].conversation_id == "9"
    assert result.retweets_skipped == 1 and result.skipped == 4
    assert any("retweet" in w for w in result.warnings)


def test_komentar_instagram_dengan_balasan_bersarang(tmp_path):
    data = [{
        "id": "17900000000000001", "text": "Promo terbaik hari ini", "timestamp": "2026-11-01T05:00:00.000Z",
        "ownerUsername": "akun_ig_fiktif", "ownerProfilePicUrl": "https://ig.example/a.jpg",
        "owner": {"id": "5550001", "username": "akun_ig_fiktif", "full_name": "Nama IG Fiktif"},
        "postUrl": "https://www.instagram.com/p/FIKTIF123/",
        "replies": [{
            "id": "17900000000000002", "text": "Setuju banget", "timestamp": "2026-11-01T05:02:00.000Z",
            "ownerUsername": "akun_ig_dua",
        }],
    }]
    result = apify_import.read(write(tmp_path, data), "instagram", 100, include_names=True)

    top, reply = result.posts
    assert top.platform == "instagram" and top.author_id == "5550001" and top.author_name == "Nama IG Fiktif (@akun_ig_fiktif)"
    assert top.posted_at == datetime(2026, 11, 1, 5, 0, tzinfo=UTC)
    assert top.conversation_id == "https://www.instagram.com/p/FIKTIF123/" and top.reply_to_id is None
    # Balasan hanya punya username: dipakai sebagai pengenal (di-HMAC oleh ingest), induknya terisi.
    assert reply.author_id == "akun_ig_dua" and reply.reply_to_id == "17900000000000001"
    assert "ig.example" not in repr(top)


def test_komentar_tiktok_dengan_waktu_epoch(tmp_path):
    data = [
        {"cid": "7300000000000000001", "text": "mantap", "createTime": 1793509200, "uid": "680001", "uniqueId": "tt_fiktif",
         "videoWebUrl": "https://www.tiktok.com/@fiktif/video/1", "avatarThumbnail": "https://tt.example/a.jpg"},
        {"cid": "7300000000000000002", "text": "betul", "createTimeISO": "2026-11-01T05:01:00.000Z", "uid": "680002",
         "repliesToId": "7300000000000000001", "videoWebUrl": "https://www.tiktok.com/@fiktif/video/1"},
    ]
    result = apify_import.read(write(tmp_path, data), "tiktok", 100)

    first, second = result.posts
    assert first.platform == "tiktok" and first.post_id == "7300000000000000001" and first.author_id == "680001"
    assert first.posted_at == datetime.fromtimestamp(1793509200, tz=UTC)
    assert second.reply_to_id == "7300000000000000001"
    assert "tt_fiktif" not in repr(first) and "tt.example" not in repr(first)


def test_format_waktu(tmp_path):
    assert apify_import.parse_time("Tue Oct 13 17:55:46 +0700 2026") == datetime(2026, 10, 13, 10, 55, 46, tzinfo=UTC)
    assert apify_import.parse_time("2026-10-13T10:55:46.000Z") == datetime(2026, 10, 13, 10, 55, 46, tzinfo=UTC)
    assert apify_import.parse_time(1793509200000) == apify_import.parse_time("1793509200")
    for bad in ("2026-10-13T10:55:46", 12, True, None, "kemarin"):
        with pytest.raises(ValueError):
            apify_import.parse_time(bad)


def test_batas_max_items_duplikat_dan_peringatan_data_sedikit(tmp_path):
    items = [tweet(str(i), str(i)) for i in range(1, 30)] + [tweet("1", "1")]
    result = apify_import.read(write(tmp_path, items), "x", 5)
    assert len(result.posts) == 5
    assert any("max_items" in w for w in result.warnings) and any("ratusan post" in w for w in result.warnings)

    everything = apify_import.read(write(tmp_path, items), "x", 1000)
    assert len(everything.posts) == 29  # id ganda hanya dihitung sekali


def test_file_tanpa_waktu_yang_bisa_dibaca_gagal_dan_menyebut_kolomnya(tmp_path):
    data = [{"komentar": "halo semua apa kabar", "pengguna": "a", "kapan": "kemarin"}] * 3
    with pytest.raises(PayloadError, match="Kolom yang ada: komentar, pengguna, kapan"):
        apify_import.read(write(tmp_path, data), "tiktok", 100)


def test_file_profil_bukan_post_ditolak_dengan_penjelasan(tmp_path):
    data = [{"username": f"akun{i}", "followersCount": 10 + i, "biography": "halo"} for i in range(5)]
    with pytest.raises(PayloadError, match="bukan profil"):
        apify_import.read(write(tmp_path, data), "instagram", 100)


def actor_baru(i, akun, teks, menit):
    """Format Actor yang namanya tidak ada di FIELDS sama sekali."""
    return {
        "commentBody": teks,
        "publishedOn": f"2026-11-10T08:{menit:02d}:00+07:00",
        "commenter": {"handle": akun, "followerCount": 3},
        "likeCount": 1700000000 + i,
        "videoLink": "https://contoh.example/v/1",
    }


def test_actor_tak_dikenal_dikenali_otomatis_dari_isi_data(tmp_path):
    data = [actor_baru(i, f"akun{i % 4}", f"komentar nomor {i} tentang tarif parkir", i) for i in range(20)]
    result = apify_import.read(write(tmp_path, data), "tiktok", 100)

    assert len(result.posts) == 20 and result.skipped == 0
    first = result.posts[0]
    assert first.text == "komentar nomor 0 tentang tarif parkir"
    assert first.author_id == "akun0"
    assert first.posted_at == datetime(2026, 11, 10, 1, 0, tzinfo=timezone.utc)  # bukan likeCount
    assert first.post_id.startswith("h:")


def test_id_buatan_konsisten_sehingga_baris_ganda_dihitung_sekali(tmp_path):
    row = actor_baru(1, "akun1", "kalimat yang sama persis di dua baris", 5)
    result = apify_import.read(write(tmp_path, [row, dict(row), actor_baru(2, "akun2", "kalimat lain lagi", 6)]), "x", 100)
    assert len(result.posts) == 2


def test_jsonl_dan_objek_pembungkus_ikut_terbaca(tmp_path):
    rows = [actor_baru(i, f"akun{i}", f"teks ke {i} untuk uji format", i) for i in range(3)]
    jsonl = tmp_path / "a.jsonl"
    jsonl.write_text("\n".join(json.dumps(r) for r in rows), encoding="utf-8")
    assert len(apify_import.read(jsonl, "x", 10).posts) == 3

    wrapped = tmp_path / "b.json"
    wrapped.write_text(json.dumps({"items": rows}), encoding="utf-8")
    assert len(apify_import.read(wrapped, "x", 10).posts) == 3


def test_waktu_pembuatan_akun_tidak_dikira_waktu_post(tmp_path):
    data = [
        {"isi": f"komentar berbeda nomor {i}", "user": {"id": f"u{i}", "created": "2015-01-01T00:00:00Z"}, "tanggal": f"2026-11-10T09:{i:02d}:00Z"}
        for i in range(5)
    ]
    result = apify_import.read(write(tmp_path, data), "x", 10)
    assert {p.posted_at.year for p in result.posts} == {2026}
    assert result.posts[0].author_id == "u0"


@pytest.mark.parametrize(
    ("content", "message"),
    [("{bukan json", "JSON yang valid"), ('{"a": 1}', "berupa daftar"), ('{"items": []}', None), ("﻿[]", None)],
)
def test_file_tidak_sesuai_format(tmp_path, content, message):
    path = tmp_path / "x.json"
    path.write_text(content, encoding="utf-8")
    if message is None:
        assert apify_import.read(path, "x", 10).posts == []  # BOM dari Windows tetap terbaca
    else:
        with pytest.raises(PayloadError, match=message):
            apify_import.read(path, "x", 10)


def test_platform_di_luar_daftar_dan_id_berkarakter_kontrol_ditolak(tmp_path):
    with pytest.raises(PayloadError, match="platform"):
        apify_import.read(write(tmp_path, []), "telegram", 10)
    result = apify_import.read(write(tmp_path, [tweet("1\n2", "2"), tweet("3", "x" * 400), tweet("4", "5")]), "x", 10)
    assert [p.post_id for p in result.posts] == ["4"] and result.skipped == 2


def test_nama_dan_username_dari_actor_tak_dikenal_ikut_terbaca_bila_diizinkan(tmp_path):
    data = [
        {
            "commentBody": f"komentar nomor {i} tentang tarif parkir",
            "publishedOn": f"2026-11-10T08:{i:02d}:00+07:00",
            "commenter": {"id": f"u{i % 3}", "handle": f"akun_{i % 3}", "displayName": f"Nama Fiktif {i % 3}"},
        }
        for i in range(9)
    ]
    path = write(tmp_path, data)

    with_names = apify_import.read(path, "tiktok", 100, include_names=True)
    assert with_names.posts[1].author_id == "u1"
    assert with_names.posts[1].author_name == "Nama Fiktif 1 (@akun_1)"

    without = apify_import.read(path, "tiktok", 100)
    assert all(p.author_name is None for p in without.posts)


def test_kolom_penulis_datar_berisi_nama_dipakai_sebagai_nama(tmp_path):
    data = [{"text_isi": f"isi komentar yang berbeda {i}", "date": f"2026-11-10T08:{i:02d}:00Z", "author": f"@pengguna{i % 2}"} for i in range(6)]
    result = apify_import.read(write(tmp_path, data), "x", 100, include_names=True)
    assert result.posts[0].author_id == "@pengguna0"
    assert result.posts[0].author_name is not None and "pengguna0" in result.posts[0].author_name


def test_format_clockworks_tiktok_scraper_nama_dan_username_terbaca(tmp_path):
    """Bentuk ekspor clockworks/tiktok-scraper (video dari satu profil); nilai fiktif."""
    data = [
        {
            "id": f"73{i}",
            "text": f"video nomor {i} #fyp",
            "createTimeISO": f"2026-01-0{i + 1}T15:24:47.000Z",
            "authorMeta": {"id": "6800000000000000001", "name": "akun_fiktif", "nickName": "Nama Fiktif", "fans": 120, "following": 8},
            "webVideoUrl": f"https://www.tiktok.example/@akun_fiktif/video/73{i}",
        }
        for i in range(3)
    ]
    result = apify_import.read(write(tmp_path, data), "tiktok", 100, include_names=True)
    post = result.posts[0]
    assert post.author_id == "6800000000000000001"
    assert post.author_name == "Nama Fiktif (@akun_fiktif)"
    assert post.followers == 120 and post.following == 8


def test_sumber_konten_x_dari_conversation_id_tanpa_username(tmp_path):
    reply = tweet("200", "u2", "balasan", conversationId="100", url="https://x.com/username_fiktif/status/200")
    result = apify_import.read(write(tmp_path, [tweet("100", "u1"), reply]), "x", 100)
    assert [p.source_ref for p in result.posts] == ["x:100", "x:100"]


def test_sumber_konten_komentar_tiktok_dan_instagram_dari_url(tmp_path):
    tiktok = [{"cid": "c1", "text": "komentar tiktok pertama", "createTimeISO": "2026-11-10T08:00:00Z", "uid": "u1",
               "videoWebUrl": "https://www.tiktok.com/@pembuat_fiktif/video/7332000000000000001"}]
    result = apify_import.read(write(tmp_path, tiktok), "tiktok", 100)
    assert result.posts[0].source_ref == "tiktok:7332000000000000001"
    assert "pembuat_fiktif" not in result.posts[0].source_ref  # conversation_id mentah di-HMAC oleh ingest

    instagram = [{"id": "c9", "text": "komentar instagram", "timestamp": "2026-11-10T08:00:00Z", "ownerUsername": "fiktif",
                  "postUrl": "https://www.instagram.com/p/C1abcDEF/"}]
    assert apify_import.read(write(tmp_path, instagram), "instagram", 100).posts[0].source_ref == "instagram:C1abcDEF"
