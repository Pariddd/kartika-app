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


class _FakeConn:
    def __init__(self, encoding):
        self.encoding = encoding

    def execute(self, sql):
        assert sql == "SHOW server_encoding"
        enc = self.encoding

        class _Cur:
            def fetchone(self_inner):
                return {"server_encoding": enc}

        return _Cur()


def test_database_utf8_diterima():
    from radar.db import check_encoding

    check_encoding(_FakeConn("UTF8"))


def test_database_win1252_ditolak_dengan_petunjuk():
    import pytest

    from radar.db import check_encoding
    from radar.errors import ConfigError

    with pytest.raises(ConfigError, match="WIN1252.*UTF8"):
        check_encoding(_FakeConn("WIN1252"))
