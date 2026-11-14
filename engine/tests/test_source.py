import pytest

from radar import source


@pytest.mark.parametrize(
    ("platform", "value", "expected"),
    [
        ("youtube", "dQw4w9WgXcQ", "youtube:dQw4w9WgXcQ"),
        ("youtube", "bukan-id", None),
        ("x", "1850000000000000000", "x:1850000000000000000"),
        ("x", "https://x.com/akun/status/185?s=20", "x:185"),
        ("x", "https://twitter.com/i/web/status/186", "x:186"),
        ("x", "@akun", None),
        ("tiktok", "https://www.tiktok.com/@akun/video/7332000000000000001?lang=id", "tiktok:7332000000000000001"),
        ("tiktok", "7332", "tiktok:7332"),
        ("tiktok", "https://evil.example/?q=tiktok.com/video/1", None),
        ("tiktok", "https://tiktok.com.evil.example/video/1", None),
        ("instagram", "https://www.instagram.com/p/C1abcDEF_-/", "instagram:C1abcDEF_-"),
        ("instagram", "https://www.instagram.com/akun/reel/XyZ/", "instagram:XyZ"),
        ("instagram", "C1abc", None),
        ("csv", "apa saja", None),
        ("x", True, None),
    ],
)
def test_build_hanya_mengambil_id_konten_tanpa_username(platform, value, expected):
    ref = source.build(platform, value)
    assert ref == expected
    if ref:
        assert "akun" not in ref and source.valid(ref) == ref


def test_valid_menolak_bentuk_lain():
    assert source.valid("youtube:abc") == "youtube:abc"
    for bad in ("youtube:", "facebook:1", "x:1 2", "x:" + "1" * 65, None):
        assert source.valid(bad) is None
