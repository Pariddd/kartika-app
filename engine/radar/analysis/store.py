"""Membaca data kasus dan menyimpan hasil analisis (docs/kontrak.md tabel clusters, cluster_members,
cluster_signals, issue_scores). Hasil analisis sebelumnya untuk kasus yang sama diganti."""

from __future__ import annotations

import psycopg
from psycopg.types.json import Jsonb

from radar.analysis.model import Account, AnalysisResult, Post


def load_case(conn: psycopg.Connection, case_id: int) -> tuple[list[Post], dict[int, Account]]:
    accounts = {
        row["id"]: Account(row["id"], row["account_created_at"])
        for row in conn.execute("SELECT id, account_created_at FROM accounts WHERE case_id = %s", (case_id,))
    }
    posts = [
        Post(row["id"], row["account_id"], row["text"], row["posted_at"], tuple(row["urls"] or ()))
        for row in conn.execute(
            "SELECT id, account_id, text, posted_at, urls FROM posts WHERE case_id = %s ORDER BY posted_at, id",
            (case_id,),
        )
    ]
    return posts, accounts


def save(conn: psycopg.Connection, case_id: int, job_id: int, result: AnalysisResult) -> None:
    """Dipanggil di dalam transaksi pemanggil."""
    # Penilaian analis atas klaster lama ikut terhapus (cascade); analisis ulang menghasilkan klaster baru.
    conn.execute("DELETE FROM clusters WHERE case_id = %s", (case_id,))
    conn.execute("DELETE FROM issue_scores WHERE case_id = %s", (case_id,))
    conn.execute("DELETE FROM case_graphs WHERE case_id = %s", (case_id,))

    for cluster in result.clusters:
        cluster_id = conn.execute(
            """
            INSERT INTO clusters (case_id, job_id, display_label, size, signals_active, coordination_score, is_flagged)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
            RETURNING id
            """,
            (case_id, job_id, cluster.label, len(cluster.members), cluster.signals_active, cluster.score, cluster.flagged),
        ).fetchone()["id"]

        with conn.cursor() as cur:
            cur.executemany(
                "INSERT INTO cluster_members (cluster_id, account_id) VALUES (%s, %s)",
                [(cluster_id, account_id) for account_id in sorted(cluster.members)],
            )
            cur.executemany(
                """
                INSERT INTO cluster_signals (cluster_id, signal, active, strength, evidence)
                VALUES (%s, %s, %s, %s, %s)
                """,
                [(cluster_id, s.signal, s.active, s.strength, Jsonb(s.evidence)) for s in cluster.signals],
            )

    conn.execute(
        """
        INSERT INTO issue_scores (case_id, job_id, organic_score, total_posts, coordinated_posts, computed_at)
        VALUES (%s, %s, %s, %s, %s, now())
        """,
        (case_id, job_id, round(result.organic_score, 3), result.total_posts, result.coordinated_posts),
    )

    if result.graph and result.graph["nodes"]:
        conn.execute(
            "INSERT INTO case_graphs (case_id, job_id, graph, computed_at) VALUES (%s, %s, %s, now())",
            (case_id, job_id, Jsonb(result.graph)),
        )
