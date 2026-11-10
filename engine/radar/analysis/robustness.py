"""Evaluasi sintetis dan uji ketahanan deteksi.

Menjalankan analisis berulang atas dataset sintetis dengan parameter klaster tanaman
yang dinaikkan bertahap, lalu menulis hasilnya sebagai JSON untuk halaman Evaluasi.

    python -m radar.analysis.robustness --out ../web/resources/evaluation/hasil-evaluasi.json

Catatan kejujuran metode:
- Ambang deteksi disetel memakai seed 1-3 (kalibrasi). Angka yang dilaporkan sebagai
  hasil uji hanya berasal dari seed yang tidak dipakai menyetel ambang.
- Semua data sintetis. Angka di sini bukan akurasi pada data dunia nyata.
"""

from __future__ import annotations

import argparse
import json
import sys
import time
from dataclasses import asdict
from pathlib import Path

from radar.analysis.evaluate import evaluate
from radar.analysis.model import Params
from radar.analysis.offline import to_analysis_input
from radar.analysis.pipeline import analyze
from radar.synthetic import PRESETS, PlantedSpec, Preset, generate_from

CALIBRATION_SEEDS = [1, 2, 3]
TEST_SEEDS = [4, 5, 6, 7, 8, 9, 10]
SWEEP_SEEDS = [4, 5, 6, 7, 8]

# Klaster acuan untuk uji ketahanan: sengaja tanpa domain khas dan tanpa akun baru,
# sehingga hanya sinyal teks dan waktu yang bisa menandainya (skenario tersulit).
BASE = PlantedSpec("Tanaman A", 25, 5, 0.3, 300, False, None)

SWEEPS = {
    "paraphrase": {
        "title": "Tingkat parafrase",
        "unit": "level",
        "field": "paraphrase",
        "values": [0.0, 0.25, 0.5, 0.75, 1.0],
        "note": "0 = teks disalin persis; 1 = setiap akun menulis ulang dengan kata pembuka, penutup, dan pilihan kata berbeda.",
    },
    "spread": {
        "title": "Sebaran waktu per kejadian",
        "unit": "detik",
        "field": "spread_seconds",
        "values": [60, 300, 900, 1800, 3600, 10800],
        "note": "Rentang waktu anggota klaster memposting dalam satu kejadian. Jendela ko-aktivitas sistem 300 detik.",
    },
    "size": {
        "title": "Ukuran klaster",
        "unit": "akun",
        "field": "size",
        "values": [4, 6, 8, 12, 20, 30],
        "note": "Jumlah akun dalam klaster tanaman.",
    },
    "events": {
        "title": "Jumlah kejadian serentak",
        "unit": "kejadian",
        "field": "events",
        "values": [1, 2, 3, 4, 6],
        "note": "Berapa kali klaster bergerak bersama dalam 24 jam. Sistem mensyaratkan minimal 3 ko-kejadian.",
    },
}


def _run(preset: Preset, seed: int, params: Params) -> dict:
    started = time.monotonic()
    dataset = generate_from(preset, seed)
    posts, accounts, ids = to_analysis_input(dataset)
    result = analyze(posts, accounts, params)
    # Anggota tanaman yang kebetulan tidak pernah memposting tidak ada di data, jadi tidak dihitung.
    planted = {label: {ids[a] for a in members if a in ids} for label, members in dataset.planted.items()}
    flagged = [c.members for c in result.clusters if c.flagged]
    ev = evaluate(flagged, planted)
    decoys = {ids[a] for a in dataset.decoys}
    decoy_flagged = any(len(c & decoys) / len(c) >= 0.5 for c in flagged) if decoys else False
    print(f"  seed {seed}: {len(ev.matched)}/{ev.planted_count} ditemukan, {time.monotonic() - started:.1f} dtk", file=sys.stderr, flush=True)
    return {
        "planted": ev.planted_count,
        "caught": len(ev.matched),
        "flagged": ev.flagged_count,
        "false_flags": ev.flagged_count - len(ev.matched),
        "decoy_flagged": decoy_flagged,
        "posts": len(posts),
        "accounts": len(accounts),
    }


def _aggregate(runs: list[dict]) -> dict:
    planted = sum(r["planted"] for r in runs)
    caught = sum(r["caught"] for r in runs)
    flagged = sum(r["flagged"] for r in runs)
    return {
        "datasets": len(runs),
        "planted": planted,
        "caught": caught,
        "flagged": flagged,
        "false_flags": sum(r["false_flags"] for r in runs),
        "decoys_flagged": sum(1 for r in runs if r["decoy_flagged"]),
        "recall": round(caught / planted, 3) if planted else None,
        "precision": round(caught / flagged, 3) if flagged else None,
    }


def baseline(params: Params) -> list[dict]:
    rows = []
    for name, preset in PRESETS.items():
        for group, seeds in (("kalibrasi", CALIBRATION_SEEDS), ("uji", TEST_SEEDS)):
            print(f"{name} ({group})", file=sys.stderr, flush=True)
            rows.append({"preset": name, "group": group, "seeds": seeds, **_aggregate([_run(preset, s, params) for s in seeds])})
    return rows


def sweep(key: str, params: Params) -> dict:
    spec = SWEEPS[key]
    points = []
    for value in spec["values"]:
        print(f"{key} = {value}", file=sys.stderr, flush=True)
        planted = PlantedSpec(**{**asdict(BASE), spec["field"]: value})
        preset = Preset(organic_accounts=900, planted=(planted,), decoy_size=12)
        runs = [_run(preset, seed, params) for seed in SWEEP_SEEDS]
        points.append({
            "value": value,
            "detected": sum(r["caught"] for r in runs),
            "runs": len(runs),
            "rate": round(sum(r["caught"] for r in runs) / len(runs), 3),
            "false_flags": sum(r["false_flags"] for r in runs),
        })
    return {"key": key, "title": spec["title"], "unit": spec["unit"], "note": spec["note"], "points": points}


def main() -> None:
    parser = argparse.ArgumentParser(description="Evaluasi sintetis dan uji ketahanan Radar Narasi")
    parser.add_argument("--out", type=Path, required=True)
    args = parser.parse_args()

    started = time.monotonic()
    params = Params()
    report = {
        "schema_version": 1,
        "params": asdict(params),
        "match_jaccard": 0.5,
        "calibration_seeds": CALIBRATION_SEEDS,
        "test_seeds": TEST_SEEDS,
        "sweep_seeds": SWEEP_SEEDS,
        "base_cluster": asdict(BASE),
        "baseline": baseline(params),
        "sweeps": [sweep(key, params) for key in SWEEPS],
    }
    report["duration_seconds"] = round(time.monotonic() - started)

    args.out.parent.mkdir(parents=True, exist_ok=True)
    args.out.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"hasil ditulis ke {args.out} ({report['duration_seconds']} detik)")


if __name__ == "__main__":
    main()
