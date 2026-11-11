"""Membaca CSV "Coba Sendiri" (docs/kontrak.md bagian 4, analyze_csv).

Aturan sama dengan web/app/Support/CsvFormat.php dan diperiksa ulang di sini,
karena file bisa saja berubah atau payload antrean diubah dari luar Laravel.
"""

from __future__ import annotations

import csv
import hashlib
import re
from datetime import datetime, timedelta, timezone
from pathlib import Path

from radar.errors import ConfigError, PayloadError
from radar.ingest import RawPost

PLATFORM = "csv"
REQUIRED = ("akun", "teks", "waktu")
OPTIONAL = ("url", "reply_to", "tanggal_akun")
MAX_ROWS = 2000
MAX_BYTES = 2048 * 1024
MAX_TEXT = 1000
MAX_ACCOUNT = 100
WIB = timezone(timedelta(hours=7))

UUID = r"[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"
FILE_REF = {ext: re.compile(rf"^uploads/{UUID}\.{ext}$") for ext in ("csv", "json")}
TIME = re.compile(r"^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:?\d{2})?$")
DATE = re.compile(r"^\d{4}-\d{2}-\d{2}$")


def resolve(upload_dir: str, file_ref: object, ext: str = "csv") -> Path:
    """Path file unggahan. Hanya nama UUID buatan server di dalam folder unggahan yang diterima
    (mencegah path traversal, CWE-22)."""
    if not upload_dir:
        raise ConfigError("RADAR_UPLOAD_DIR di engine/.env wajib diisi (folder web/storage/app/private)")
    if not isinstance(file_ref, str) or not FILE_REF[ext].fullmatch(file_ref):
        raise PayloadError("file_ref tidak valid")
    base = Path(upload_dir).resolve()
    path = (base / file_ref).resolve()
    if base not in path.parents:
        raise PayloadError("file_ref di luar folder unggahan")
    return path


def parse_time(value: str) -> datetime:
    if not TIME.fullmatch(value):
        raise ValueError("format waktu")
    value = value.replace("Z", "+00:00")
    if re.search(r"[+-]\d{4}$", value):  # +0700 -> +07:00 (Python 3.10)
        value = value[:-2] + ":" + value[-2:]
    parsed = datetime.fromisoformat(value)
    return parsed if parsed.tzinfo else parsed.replace(tzinfo=WIB)


def read(path: Path) -> list[RawPost]:
    if not path.is_file():
        raise PayloadError("file unggahan tidak ditemukan (mungkin sudah dihapus)")
    if path.stat().st_size > MAX_BYTES:
        raise PayloadError("ukuran file melebihi 2 MB")

    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.reader(handle)
        try:
            header = [h.strip().lower() for h in next(reader)]
        except StopIteration as exc:
            raise PayloadError("file kosong") from exc

        missing = [c for c in REQUIRED if c not in header]
        if missing:
            raise PayloadError("kolom wajib tidak ada: " + ", ".join(missing))
        unknown = [c for c in header if c not in REQUIRED + OPTIONAL]
        if unknown:
            raise PayloadError("kolom tidak dikenal: " + ", ".join(unknown))
        index = {name: i for i, name in enumerate(header)}

        posts: list[RawPost] = []
        for line, row in enumerate(reader, start=2):
            if not row or all(not cell.strip() for cell in row):
                continue
            if len(posts) >= MAX_ROWS:
                raise PayloadError(f"maksimal {MAX_ROWS} baris data")

            def get(column: str) -> str:
                i = index.get(column)
                return row[i].strip() if i is not None and i < len(row) else ""

            account, text, when = get("akun"), get("teks"), get("waktu")
            if not account or len(account) > MAX_ACCOUNT:
                raise PayloadError(f"baris {line}: kolom akun kosong atau terlalu panjang")
            if not text or len(text) > MAX_TEXT:
                raise PayloadError(f"baris {line}: kolom teks kosong atau terlalu panjang")
            try:
                posted_at = parse_time(when)
            except ValueError as exc:
                raise PayloadError(f"baris {line}: format waktu tidak valid") from exc

            created = get("tanggal_akun")
            if created and not DATE.fullmatch(created):
                raise PayloadError(f"baris {line}: tanggal_akun harus YYYY-MM-DD")

            url = get("url")
            reply_to = get("reply_to")
            # CSV tidak punya ID post: ID dibentuk dari isi baris, sehingga baris yang persis sama
            # dihitung sekali (deduplikasi), sedangkan baris berbeda selalu punya ID berbeda.
            post_id = hashlib.sha256(f"{account}\x1f{text}\x1f{when}".encode("utf-8")).hexdigest()

            posts.append(
                RawPost(
                    platform=PLATFORM,
                    author_id=account,
                    post_id=post_id,
                    text=text,
                    posted_at=posted_at,
                    account_created_at=datetime.fromisoformat(created).replace(tzinfo=timezone.utc) if created else None,
                    reply_to_id=reply_to or None,
                    urls=(url,) if url.lower().startswith(("http://", "https://")) else (),
                )
            )

    if not posts:
        raise PayloadError("tidak ada baris data di bawah header")
    return posts
