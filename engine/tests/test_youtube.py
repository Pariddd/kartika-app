"""Adapter YouTube diuji dengan respons tiruan: tidak ada permintaan jaringan sungguhan."""

from __future__ import annotations

import urllib.parse
from datetime import timezone

import pytest

from radar.errors import ConfigError, JobError
from radar.youtube import YouTubeClient

KEY = "AIza-kunci-uji-rahasia"


def comment(cid, author, text="komentar", at="2026-11-01T12:00:00Z", parent=None):
    snippet = {
        "authorDisplayName": "@NamaAsliJanganDisimpan",
        "authorProfileImageUrl": "https://yt3.example/foto.jpg",
        "authorChannelUrl": "http://www.youtube.com/@NamaAsliJanganDisimpan",
        "textDisplay": text,
        "publishedAt": at,
    }
    if author is not None:
        snippet["authorChannelId"] = {"value": author}
    if parent:
        snippet["parentId"] = parent
    return {"id": cid, "snippet": snippet}


def thread(tid, author, replies=(), total=None, **kw):
    return {
        "id": tid,
        "snippet": {"topLevelComment": comment(tid, author, **kw), "totalReplyCount": len(replies) if total is None else total},
        "replies": {"comments": list(replies)} if replies else {},
    }


class FakeApi:
    """Mengembalikan respons berurutan per (resource, pageToken/parentId) dan mencatat URL."""

    def __init__(self, routes):
        self.routes = routes
        self.urls = []

    def __call__(self, url):
        self.urls.append(url)
        parsed = urllib.parse.urlparse(url)
        query = dict(urllib.parse.parse_qsl(parsed.query))
        resource = parsed.path.rsplit("/", 1)[-1]
        key = (resource, query.get("videoId") or query.get("parentId"), query.get("pageToken"))
        response = self.routes[key]
        return response.pop(0) if isinstance(response, list) else response


def no_sleep(_):
    pass


def test_tanpa_api_key_gagal_sebelum_ada_permintaan():
    with pytest.raises(ConfigError, match="YOUTUBE_API_KEY"):
        YouTubeClient("")


def test_komentar_dan_balasan_dipetakan_tanpa_nama_atau_url_profil():
    api = FakeApi({
        ("commentThreads", "vid00000001", None): (200, {
            "items": [
                thread("T1", "UC_A", replies=[comment("T1.r1", "UC_B", "@NamaOrang setuju", parent="T1")]),
                thread("T2", "UC_C", text="halo https://contoh.example/x"),
            ],
        }),
    })
    result = YouTubeClient(KEY, fetch=api, sleep=no_sleep).collect(["vid00000001"], 100)

    assert [p.post_id for p in result.posts] == ["T1", "T1.r1", "T2"]
    reply = result.posts[1]
    assert reply.author_id == "UC_B"
    assert reply.reply_to_id == "T1" and reply.conversation_id == "T1"
    assert result.posts[0].reply_to_id is None
    assert reply.posted_at.tzinfo == timezone.utc
    assert all(p.platform == "youtube" and p.account_created_at is None for p in result.posts)
    assert all(p.source_ref == "youtube:vid00000001" for p in result.posts)  # balasan ikut video yang sama

    # RawPost tidak punya tempat untuk nama atau URL profil; pastikan tidak terselip di field mana pun.
    for post in result.posts:
        assert "NamaAsliJanganDisimpan" not in repr(post)
        assert "yt3.example" not in repr(post)
    assert result.requests == 1


def test_paginasi_dan_batas_max_items():
    api = FakeApi({
        ("commentThreads", "vid00000001", None): (200, {"items": [thread(f"A{i}", f"UC{i}") for i in range(3)], "nextPageToken": "p2"}),
        ("commentThreads", "vid00000001", "p2"): (200, {"items": [thread(f"B{i}", f"UC{i}") for i in range(3)], "nextPageToken": "p3"}),
    })
    result = YouTubeClient(KEY, fetch=api, sleep=no_sleep).collect(["vid00000001"], 5)
    assert len(result.posts) == 5
    assert result.requests == 2  # halaman p3 tidak diminta karena batas sudah tercapai


def test_balasan_lebih_dari_lima_diambil_lewat_comments_list():
    api = FakeApi({
        ("commentThreads", "vid00000001", None): (200, {
            "items": [thread("T1", "UC_A", replies=[comment(f"T1.r{i}", "UC_x", parent="T1") for i in range(5)], total=7)],
        }),
        ("comments", "T1", None): (200, {"items": [comment(f"T1.r{i}", f"UC_{i}", parent="T1") for i in range(4)], "nextPageToken": "q"}),
        ("comments", "T1", "q"): (200, {"items": [comment(f"T1.r{i}", f"UC_{i}", parent="T1") for i in range(4, 7)]}),
    })
    result = YouTubeClient(KEY, fetch=api, sleep=no_sleep).collect(["vid00000001"], 100)
    assert len(result.posts) == 8
    assert {p.reply_to_id for p in result.posts[1:]} == {"T1"}


def test_komentar_tanpa_id_kanal_dilewati():
    api = FakeApi({("commentThreads", "vid00000001", None): (200, {"items": [thread("T1", None), thread("T2", "UC_B")]})})
    result = YouTubeClient(KEY, fetch=api, sleep=no_sleep).collect(["vid00000001"], 100)
    assert [p.post_id for p in result.posts] == ["T2"]
    assert result.skipped_without_author == 1


def test_komentar_nonaktif_dan_video_hilang_menjadi_peringatan_bukan_kegagalan():
    api = FakeApi({
        ("commentThreads", "vid00000001", None): (403, {"error": {"errors": [{"reason": "commentsDisabled"}]}}),
        ("commentThreads", "vid00000002", None): (404, {"error": {"errors": [{"reason": "videoNotFound"}]}}),
        ("commentThreads", "vid00000003", None): (200, {"items": [thread("T1", "UC_A")]}),
    })
    result = YouTubeClient(KEY, fetch=api, sleep=no_sleep).collect(["vid00000001", "vid00000002", "vid00000003"], 100)
    assert len(result.posts) == 1
    assert len(result.warnings) == 2


@pytest.mark.parametrize(
    ("status", "reason", "error", "pesan"),
    [
        (403, "quotaExceeded", JobError, "kuota"),
        (400, "badRequest", ConfigError, "ditolak"),
        (403, "accessNotConfigured", ConfigError, "aktifkan"),
    ],
)
def test_error_api_menjadi_pesan_jelas_tanpa_membocorkan_kunci(status, reason, error, pesan):
    api = FakeApi({("commentThreads", "vid00000001", None): (status, {"error": {"errors": [{"reason": reason}]}})})
    with pytest.raises(error, match=pesan) as caught:
        YouTubeClient(KEY, fetch=api, sleep=no_sleep).collect(["vid00000001"], 100)
    assert KEY not in str(caught.value)


def test_server_error_dicoba_ulang_lalu_gagal_tanpa_kunci_di_pesan():
    api = FakeApi({("commentThreads", "vid00000001", None): [(503, {}), (500, {}), (502, {})]})
    with pytest.raises(JobError, match="tidak bisa dihubungi") as caught:
        YouTubeClient(KEY, fetch=api, sleep=no_sleep).collect(["vid00000001"], 100)
    assert KEY not in str(caught.value)
    assert len(api.urls) == 3


def test_server_error_sementara_lalu_berhasil():
    api = FakeApi({("commentThreads", "vid00000001", None): [(503, {}), (200, {"items": [thread("T1", "UC_A")]})]})
    result = YouTubeClient(KEY, fetch=api, sleep=no_sleep).collect(["vid00000001"], 100)
    assert len(result.posts) == 1


def test_kunci_hanya_dikirim_sebagai_parameter_dan_hanya_ke_googleapis():
    api = FakeApi({("commentThreads", "vid00000001", None): (200, {"items": []})})
    YouTubeClient(KEY, fetch=api, sleep=no_sleep).collect(["vid00000001"], 100)
    parsed = urllib.parse.urlparse(api.urls[0])
    assert parsed.scheme == "https" and parsed.netloc == "www.googleapis.com"
    assert dict(urllib.parse.parse_qsl(parsed.query))["key"] == KEY


def test_settings_tidak_menampilkan_kunci_di_repr():
    from radar.config import Settings

    s = Settings("local", "", 0, "", "", "", 1, "w", youtube_api_key=KEY)
    assert KEY not in repr(s)


def test_nama_tampilan_hanya_dibaca_bila_diminta_dan_tidak_pernah_url_profil():
    def api():
        return FakeApi({("commentThreads", "vid00000001", None): (200, {"items": [thread("T1", "UC_A")]})})

    default = YouTubeClient(KEY, fetch=api(), sleep=no_sleep).collect(["vid00000001"], 10)
    assert default.posts[0].author_name is None

    named = YouTubeClient(KEY, fetch=api(), sleep=no_sleep, include_names=True).collect(["vid00000001"], 10)
    assert named.posts[0].author_name == "@NamaAsliJanganDisimpan"
    assert "yt3.example" not in repr(named.posts[0])
    assert "youtube.com/@" not in repr(named.posts[0])


def test_nama_hanya_aktif_di_mode_lokal():
    from radar.config import Settings

    assert Settings("local", "", 0, "", "", "", 1, "w", store_account_names=True).names_enabled
    assert not Settings("public", "", 0, "", "", "", 1, "w", store_account_names=True).names_enabled
    assert not Settings("local", "", 0, "", "", "", 1, "w").names_enabled


def test_nama_dibersihkan_dari_karakter_kontrol_dan_dibatasi():
    from radar.ingest import clean_name

    assert clean_name("  Budi‮\x00 S  ") == "Budi S"
    assert clean_name("​") is None
    assert clean_name(None) is None
    assert len(clean_name("a" * 300)) == 100
