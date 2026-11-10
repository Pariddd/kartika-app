"""Kemiripan teks antar-post dari akun berbeda.

- MinHash LSH atas 4-gram karakter: salin-tempel dan teks nyaris identik.
- TF-IDF 3-5-gram karakter + cosine (k tetangga terdekat): parafrase ringan
  (kata diganti, struktur sama). Parafrase semantik penuh butuh embedding.
"""

from __future__ import annotations

import re
from dataclasses import dataclass

from datasketch import MinHash, MinHashLSH
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.neighbors import NearestNeighbors

from radar.analysis.model import Params, Post

MIN_TOKENS = 5  # teks sangat pendek ("Setuju.", "Mantap") wajar sama dan tidak dihitung
NUM_PERM = 64
NEIGHBORS = 15

_URL = re.compile(r"https?://\S+")
_MENTION = re.compile(r"@u_[0-9a-f]{8}")
_NON_WORD = re.compile(r"[^\w]+", re.UNICODE)


def normalize(text: str) -> str:
    text = _URL.sub(" ", text)
    text = _MENTION.sub(" ", text)
    text = _NON_WORD.sub(" ", text.lower())
    return " ".join(text.split())


@dataclass(frozen=True)
class SimilarPair:
    post_a: int
    post_b: int
    method: str  # minhash | tfidf
    score: float


def _shingles(text: str, size: int = 4) -> set[bytes]:
    if len(text) <= size:
        return {text.encode("utf-8")}
    return {text[i : i + size].encode("utf-8") for i in range(len(text) - size + 1)}


def similar_pairs(posts: list[Post], params: Params) -> tuple[list[SimilarPair], set[int]]:
    """Pasangan post mirip dari akun berbeda, dan himpunan post yang memenuhi syarat dibandingkan."""
    eligible = [(p, normalize(p.text)) for p in posts]
    eligible = [(p, t) for p, t in eligible if len(t.split()) >= MIN_TOKENS]
    eligible_ids = {p.id for p, _ in eligible}
    if len(eligible) < 2:
        return [], eligible_ids

    found: dict[tuple[int, int], SimilarPair] = {}

    def keep(a: Post, b: Post, method: str, score: float) -> None:
        if a.account_id == b.account_id:
            return
        key = (min(a.id, b.id), max(a.id, b.id))
        if key not in found or found[key].score < score:
            found[key] = SimilarPair(key[0], key[1], method, round(float(score), 3))

    # 1) MinHash LSH
    lsh = MinHashLSH(threshold=params.minhash_threshold, num_perm=NUM_PERM)
    hashes: dict[int, MinHash] = {}
    by_id = {p.id: p for p, _ in eligible}
    for post, text in eligible:
        mh = MinHash(num_perm=NUM_PERM, seed=1)
        mh.update_batch(_shingles(text))
        hashes[post.id] = mh
        lsh.insert(post.id, mh)
    for post, _ in eligible:
        for other_id in lsh.query(hashes[post.id]):
            if other_id <= post.id:
                continue
            score = hashes[post.id].jaccard(hashes[other_id])
            if score >= params.minhash_threshold:
                keep(post, by_id[other_id], "minhash", score)

    # 2) TF-IDF karakter + cosine
    texts = [t for _, t in eligible]
    matrix = TfidfVectorizer(analyzer="char_wb", ngram_range=(3, 5), min_df=2, sublinear_tf=True).fit_transform(texts)
    k = min(NEIGHBORS + 1, len(eligible))
    distances, indices = NearestNeighbors(n_neighbors=k, metric="cosine").fit(matrix).kneighbors(matrix)
    for row, (dist_row, idx_row) in enumerate(zip(distances, indices)):
        a = eligible[row][0]
        for dist, col in zip(dist_row, idx_row):
            if col == row:
                continue
            score = 1 - dist
            if score < params.tfidf_threshold:
                break  # tetangga terurut dari yang paling dekat
            b = eligible[col][0]
            key = (min(a.id, b.id), max(a.id, b.id))
            if key in found and found[key].method == "minhash":
                continue  # sudah tertangkap sebagai nyaris identik
            keep(a, b, "tfidf", score)

    return list(found.values()), eligible_ids
