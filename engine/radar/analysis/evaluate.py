"""Evaluasi terhadap kunci jawaban sintetis (docs/kontrak.md bagian 7).

Klaster temuan cocok dengan klaster tanaman bila Jaccard anggotanya >= 0,5.
"""

from __future__ import annotations

from dataclasses import dataclass

MATCH_JACCARD = 0.5


def jaccard(a: set[int], b: set[int]) -> float:
    return len(a & b) / len(a | b) if a or b else 0.0


@dataclass
class Evaluation:
    recall: float
    precision: float
    matched: dict[str, tuple[int, float]]  # label tanaman -> (indeks klaster, jaccard)
    flagged_count: int
    planted_count: int


def evaluate(flagged: list[set[int]], planted: dict[str, set[int]], threshold: float = MATCH_JACCARD) -> Evaluation:
    matched: dict[str, tuple[int, float]] = {}
    used: set[int] = set()
    # Pencocokan serakah, pasangan dengan Jaccard tertinggi lebih dulu; satu klaster hanya untuk satu tanaman.
    candidates = sorted(
        ((jaccard(cluster, members), label, index) for label, members in planted.items() for index, cluster in enumerate(flagged)),
        reverse=True,
    )
    for score, label, index in candidates:
        if score < threshold or label in matched or index in used:
            continue
        matched[label] = (index, round(score, 3))
        used.add(index)

    recall = len(matched) / len(planted) if planted else 1.0
    precision = len(used) / len(flagged) if flagged else (1.0 if not planted else 0.0)
    return Evaluation(round(recall, 3), round(precision, 3), matched, len(flagged), len(planted))
