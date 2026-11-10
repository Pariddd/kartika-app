"""Membuat file contoh untuk "Coba Sendiri" dari generator sintetis.

    python -m radar.sample_csv ../web/resources/samples/contoh-radar-narasi.csv

Isinya fiktif: sebagian percakapan organik, satu klaster tanaman (Tanaman A),
dan kelompok pengecoh sah, dengan nama akun buatan seperti warga_0001.
"""

from __future__ import annotations

import csv
import random
import sys
from pathlib import Path

from radar import synthetic

SEED = 7
ORGANIC_ACCOUNTS = 250


def build(path: Path) -> int:
    data = synthetic.generate("standar", SEED)
    rng = random.Random(SEED)

    planted = set(data.planted["Tanaman A"])
    decoys = set(data.decoys)
    others = sorted({p.author_id for p in data.posts} - planted - decoys - {a for m in data.planted.values() for a in m})
    organic = set(rng.sample(others, ORGANIC_ACCOUNTS))
    keep = planted | decoys | organic

    names: dict[str, str] = {}
    for author in sorted(keep, key=lambda _: rng.random()):
        names[author] = f"warga_{len(names) + 1:04d}"

    rows = [p for p in sorted(data.posts, key=lambda p: p.posted_at) if p.author_id in keep]
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.writer(handle)
        writer.writerow(["akun", "teks", "waktu", "url", "tanggal_akun"])
        for post in rows:
            writer.writerow([
                names[post.author_id],
                post.text,
                post.posted_at.astimezone(synthetic.WIB).isoformat(timespec="seconds"),
                post.urls[0] if post.urls else "",
                post.account_created_at.date().isoformat() if post.account_created_at else "",
            ])
    return len(rows)


if __name__ == "__main__":
    target = Path(sys.argv[1] if len(sys.argv) > 1 else "contoh-radar-narasi.csv")
    print(f"{build(target)} baris ditulis ke {target}")
