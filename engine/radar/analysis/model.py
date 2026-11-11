"""Struktur data analisis, terpisah dari database agar bisa diuji tanpa PostgreSQL."""

from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime
from typing import Any

from radar.errors import PayloadError

# Rentang yang diterima untuk setiap parameter analyze: (tipe, minimum, maksimum).
PARAM_RANGES: dict[str, tuple[type, float, float]] = {
    "min_signals": (int, 1, 4),
    "min_cluster_size": (int, 2, 1000),
    "time_window_seconds": (int, 10, 86400),
    "min_co_events": (int, 1, 100),
    "minhash_threshold": (float, 0.1, 1.0),
    "tfidf_threshold": (float, 0.1, 1.0),
    "min_shared_texts": (int, 1, 100),
    "seed": (int, 0, 2**31 - 1),
}


@dataclass(frozen=True)
class Post:
    id: int
    account_id: int
    text: str
    posted_at: datetime
    urls: tuple[str, ...] = ()


@dataclass(frozen=True)
class Account:
    id: int
    created_at: datetime | None = None


@dataclass(frozen=True)
class Params:
    """Parameter job analyze (docs/kontrak.md bagian 4). Nilai awal dikalibrasi dengan data sintetis."""

    min_signals: int = 2
    min_cluster_size: int = 3
    time_window_seconds: int = 300
    min_co_events: int = 3
    minhash_threshold: float = 0.8
    tfidf_threshold: float = 0.6
    min_shared_texts: int = 2
    seed: int = 42

    @classmethod
    def from_payload(cls, params: dict[str, Any]) -> "Params":
        """Parameter tak dikenal diabaikan (misalnya embedding_threshold dari versi lama);
        parameter dikenal wajib bertipe dan berada dalam rentang yang benar."""
        values: dict[str, Any] = {}
        for name, (kind, low, high) in PARAM_RANGES.items():
            if name not in params:
                continue
            value = params[name]
            is_number = isinstance(value, (int, float)) and not isinstance(value, bool)
            if kind is int and not (is_number and float(value).is_integer()):
                raise PayloadError(f"{name} harus bilangan bulat")
            if kind is float and not is_number:
                raise PayloadError(f"{name} harus angka")
            if not low <= value <= high:
                raise PayloadError(f"{name} harus di antara {low} dan {high}")
            values[name] = kind(value)
        return cls(**values)


@dataclass
class SignalResult:
    signal: str  # text | time | profile | infra
    active: bool
    strength: float
    evidence: dict[str, Any] = field(default_factory=dict)


@dataclass
class ClusterResult:
    members: set[int]
    signals: list[SignalResult]
    flagged: bool
    score: float
    label: str = ""

    @property
    def signals_active(self) -> int:
        return sum(1 for s in self.signals if s.active)


@dataclass
class AnalysisResult:
    clusters: list[ClusterResult]
    total_posts: int
    coordinated_posts: int
    total_accounts: int
    warnings: list[str] = field(default_factory=list)
    # Data graf untuk ditampilkan (radar.analysis.network); None bila tidak ada post.
    graph: dict[str, Any] | None = None

    @property
    def organic_score(self) -> float:
        if self.total_posts == 0:
            return 1.0
        return 1 - self.coordinated_posts / self.total_posts

    @property
    def flagged(self) -> list[ClusterResult]:
        return [c for c in self.clusters if c.flagged]
