import pytest

from radar.errors import ConfigError
from radar.pseudo import Pseudonymizer

KEY = "k" * 32


def test_kunci_konsisten_dan_64_hex():
    pseudo = Pseudonymizer(KEY)
    first = pseudo.key_for("youtube", "UCabc123")
    assert first == pseudo.key_for("youtube", "UCabc123")
    assert len(first) == 64 and int(first, 16) >= 0


def test_platform_berbeda_menghasilkan_kunci_berbeda():
    pseudo = Pseudonymizer(KEY)
    assert pseudo.key_for("youtube", "123") != pseudo.key_for("x", "123")


def test_kunci_rahasia_berbeda_menghasilkan_pseudonim_berbeda():
    # Inilah alasan memakai HMAC: tanpa kunci, ID publik tidak bisa dicocokkan.
    assert Pseudonymizer(KEY).key_for("x", "123") != Pseudonymizer("z" * 32).key_for("x", "123")


@pytest.mark.parametrize("key", ["", "pendek", "a" * 31])
def test_kunci_kosong_atau_pendek_ditolak(key):
    with pytest.raises(ConfigError, match="PSEUDO_KEY"):
        Pseudonymizer(key)


def test_mention_disamarkan_konsisten_tanpa_menyentuh_email():
    pseudo = Pseudonymizer(KEY)
    masked = pseudo.mask_mentions("x", "Halo @BudiSantoso dan @budisantoso, kirim ke budi@mail.example ya @ani_99.")

    assert "BudiSantoso" not in masked and "budisantoso" not in masked and "ani_99" not in masked
    assert "budi@mail.example" in masked
    handles = [word.strip(",.") for word in masked.split() if word.startswith("@u_")]
    assert len(handles) == 3
    assert handles[0] == handles[1]  # huruf besar/kecil tidak membedakan akun
    assert masked.endswith(".")
