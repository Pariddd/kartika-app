import pytest

from radar.config import normalize_mode


@pytest.mark.parametrize(
    ("value", "expected"),
    [
        (None, "public"),
        ("", "public"),
        ("local", "local"),
        (" LOCAL ", "local"),
        ("public", "public"),
        ("lokal", "public"),
    ],
)
def test_mode_dinormalisasi_fail_closed(value, expected):
    assert normalize_mode(value) == expected
