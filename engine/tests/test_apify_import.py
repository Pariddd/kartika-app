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


def test_format_actor_yang_tidak_dikenali_gagal_dengan_pesan_jelas(tmp_path):
    data = [{"komentar": "halo", "pengguna": "a", "kapan": "kemarin"}] * 3
    with pytest.raises(PayloadError, match="belum dikenali"):
        apify_import.read(write(tmp_path, data), "tiktok", 100)


@pytest.mark.parametrize(
    ("content", "message"),
    [("{bukan json", "JSON yang valid"), ('{"items": []}', "berupa daftar"), ("﻿[]", None)],
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
