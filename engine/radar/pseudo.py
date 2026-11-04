"""Pseudonimisasi identitas akun dan post (satu-satunya implementasi; lihat docs/aturan-kerja.md).

HMAC-SHA256 dengan kunci rahasia, bukan SHA-256 biasa: tanpa kunci, siapa pun bisa
meng-hash daftar ID publik lalu mencocokkannya dengan isi database.
Ini pseudonimisasi, bukan anonimisasi: selama kunci ada, data masih bisa dikaitkan
kembali, sehingga kunci tidak pernah keluar dari lingkungan tim.
"""

from __future__ import annotations

import hashlib
import hmac
import re

from radar.errors import ConfigError

MIN_KEY_LENGTH = 32

# @handle yang tidak didahului huruf/angka (agar alamat email tidak ikut terganti).
MENTION = re.compile(r"(?<![\w@])@([A-Za-z0-9_.]{2,64})")


class Pseudonymizer:
    def __init__(self, key: str) -> None:
        if len(key or "") < MIN_KEY_LENGTH:
            raise ConfigError(
                f"PSEUDO_KEY di engine/.env wajib diisi, minimal {MIN_KEY_LENGTH} karakter "
                "(buat dengan: openssl rand -hex 32)"
            )
        self._key = key.encode("utf-8")

    def key_for(self, platform: str, raw_id: str) -> str:
        """Kunci 64 karakter hex untuk ID akun atau ID post. Platform ikut di-hash
        supaya ID yang sama di dua platform tidak menghasilkan kunci yang sama."""
        message = f"{platform}:{raw_id}".encode("utf-8")
        return hmac.new(self._key, message, hashlib.sha256).hexdigest()

    def optional_key(self, platform: str, raw_id: str | None) -> str | None:
        return None if raw_id is None else self.key_for(platform, raw_id)

    def mask_mentions(self, platform: str, text: str) -> str:
        """@username di dalam teks diganti pseudonim pendek yang konsisten, misalnya @u_3fa9c2d1."""

        def replace(match: re.Match[str]) -> str:
            raw = match.group(1)
            handle = raw.rstrip(".")
            trailing = raw[len(handle):]  # titik penutup kalimat tidak ikut di-hash dan tetap dipertahankan
            return "@u_" + self.key_for(platform, f"mention:{handle.lower()}")[:8] + trailing

        return MENTION.sub(replace, text)
