# Kontrak antar-anggota — Radar Narasi

Versi 1 · dikunci 4 November 2026

Dokumen ini mengikat seluruh kode `web/` dan `engine/`. Perubahan hanya lewat migration Laravel baru, dicatat di dokumen ini, dan `schema_version` dinaikkan bila format JSON berubah.

## 1. Kepemilikan

- Skema dimiliki migration Laravel di `web/database/migrations/`. Python tidak menjalankan DDL.
- Kolom bertanda `[L]` hanya ditulis Laravel; `[P]` hanya ditulis Python.
- Waktu selalu `timestamptz` dan disimpan dalam UTC. Tampilan dikonversi ke WIB. Di migration Laravel: `timestampTz()` dan `timestampsTz()`, tidak pernah `timestamp()` atau `timestamps()`.
- Setiap koneksi ke PostgreSQL wajib memakai sesi `TimeZone = UTC` (Laravel: `'timezone' => 'UTC'` di `config/database.php`; Python: `options='-c TimeZone=UTC'` saat connect) dan Python hanya menulis `datetime` yang punya zona (`tzinfo`). Tanpa ini, waktu bergeser sebesar zona server, misalnya 7 jam di Windows dengan zona WIB.
- Isi kolom `jsonb` tidak mempertahankan urutan kunci. Kode tidak boleh bergantung pada urutan kunci JSON.
- Aturan hapus foreign key:
  - Semua FK ke `cases`, `clusters`, `accounts`, dan `analysis_jobs`: `ON DELETE CASCADE`, agar menghapus kasus menghapus seluruh datanya.
  - `audit_logs.case_id` dan `audit_logs.user_id`: `ON DELETE SET NULL`, agar jejak audit tetap ada.
  - `cluster_reviews.user_id` dan `cases.created_by`: `ON DELETE RESTRICT`.
- Kolom enum dibuat dengan `enum()` Laravel (di PostgreSQL menjadi varchar + CHECK).
- Setiap kolom foreign key diberi indeks. PostgreSQL tidak membuatnya otomatis, padahal penghapusan berantai (cascade) dan join memerlukannya.
- Constraint tambahan di luar tabel bagian 2:
  - `accounts`: unique (case_id, display_label), agar label `Akun-0001` tidak pernah kembar dalam satu kasus.
  - `demo_ground_truth`: primary key (case_id, account_id), satu akun hanya masuk satu klaster tanaman per kasus.

## 2. Tabel

### `users` [L]
Bawaan Laravel, ditambah `role` enum: `admin`, `analis`, `demo`.

### `cases` [L]
| Kolom | Tipe | Catatan |
| --- | --- | --- |
| id | bigint PK | |
| name | varchar(200) | |
| mode | enum `live`, `demo`, `csv` | `live` hanya bisa dibuat saat `APP_MODE=local` |
| sources | jsonb | contoh `["youtube"]` |
| query | jsonb | parameter pengumpulan, sama dengan `params` job `collect` |
| status | enum `draft`, `collecting`, `analyzing`, `done`, `failed` | Pengecualian: kolom ini juga ditulis Python saat job berpindah tahap (`collecting` → `analyzing` → `done` atau `failed`). Kolom `cases` lain hanya ditulis Laravel |
| created_by | FK users | |
| expires_at | timestamptz null | kasus CSV: dibuat + 24 jam |
| timestamps | | |

### `analysis_jobs` [L membuat, P memperbarui status]
| Kolom | Tipe | Catatan |
| --- | --- | --- |
| id | bigint PK | |
| case_id | FK cases | |
| job_type | enum `collect`, `analyze`, `analyze_csv`, `evaluate` | |
| status | enum `pending`, `running`, `done`, `failed` | |
| payload | jsonb | lihat bagian 4 |
| attempts | smallint default 0 | |
| locked_at | timestamptz null | heartbeat worker |
| worker_id | varchar(64) null | |
| error | text null | pesan singkat, tanpa data pribadi |
| result_summary | jsonb null | lihat bagian 5 |
| timestamps | | indeks pada (status, created_at) |

### `accounts` [P]
| Kolom | Tipe | Catatan |
| --- | --- | --- |
| id | bigint PK | |
| case_id | FK cases, on delete cascade | |
| platform | enum `x`, `youtube`, `csv`, `synthetic` | |
| account_key | char(64) | HMAC-SHA256 dari `platform:author_id` |
| display_label | varchar(16) | `Akun-0001`, berurutan per kasus menurut waktu post pertama akun (bukan urutan data masuk), agar label tidak membocorkan pengelompokan |
| account_created_at | timestamptz null | hanya X atau CSV `tanggal_akun` |
| followers | integer null | |
| following | integer null | |
| | | unique (case_id, platform, account_key) |

### `posts` [P]
| Kolom | Tipe | Catatan |
| --- | --- | --- |
| id | bigint PK | |
| case_id | FK cases, on delete cascade | |
| account_id | FK accounts | |
| platform | enum sama dengan `accounts` | |
| post_key | char(64) | HMAC dari `platform:post_id` |
| text | text | mention `@nama` diganti `@u_` + 8 hex HMAC (konsisten, tidak peka huruf besar-kecil); maksimal 5.000 karakter |
| posted_at | timestamptz | |
| reply_to_key | char(64) null | |
| conversation_key | char(64) null | |
| urls | jsonb | daftar URL di teks, default `[]` |
| media_phash | jsonb | perceptual hash gambar, bukan URL media; default `[]` |
| | | unique (case_id, platform, post_key); indeks (case_id, posted_at) |

### `domains` [P]
id, case_id, domain varchar(253), registered_at timestamptz null, registrar varchar(200) null, nameservers jsonb, checked_at timestamptz. Unique (case_id, domain). Tidak ada kolom data pemilik.

### `clusters` [P]
id, case_id, job_id (FK analysis_jobs), display_label varchar(16) (`K-01`), size integer, signals_active smallint, coordination_score numeric(4,3), is_flagged boolean.

### `cluster_members` [P]
cluster_id, account_id. PK gabungan.

### `cluster_signals` [P]
| Kolom | Tipe | Catatan |
| --- | --- | --- |
| id | bigint PK | |
| cluster_id | FK clusters | |
| signal | enum `text`, `time`, `profile`, `infra` | |
| active | boolean | |
| strength | numeric(4,3) | 0 sampai 1 |
| evidence | jsonb | lihat bagian 6 |
| | | unique (cluster_id, signal) |

### `issue_scores` [P]
id, case_id, job_id, organic_score numeric(4,3), total_posts integer, coordinated_posts integer, computed_at timestamptz.

### `cluster_reviews` [L]
id, cluster_id, user_id, verdict enum `terkoordinasi`, `keliru`, `belum_pasti`, note text null, created_at.

### `demo_ground_truth` [P]
case_id, planted_label varchar(16), account_id. Hanya dibaca oleh endpoint "Tampilkan jawaban".

### `audit_logs` [L]
id, user_id null, action varchar(64), case_id null, ip varchar(45), status smallint null (kode HTTP; ≥ 400 = akses ditolak; null untuk login/logout), created_at.

## 3. Siklus job

1. Laravel membuat baris `status = pending`.
2. Worker mengambil satu job: `SELECT ... WHERE status = 'pending' ORDER BY created_at FOR UPDATE SKIP LOCKED LIMIT 1`, lalu mengubah ke `running`, mengisi `locked_at` dan `worker_id`.
3. Worker memperbarui `locked_at` minimal setiap 60 detik.
4. Selesai: `done` + `result_summary`. Gagal: `failed` + `error`.
5. Job `running` dengan `locked_at` lebih dari 10 menit dianggap macet: dikembalikan ke `pending` selama `attempts` < 3, setelah itu `failed`.
6. Job `collect` yang selesai membuat job `analyze` untuk kasus yang sama.
7. Saat `APP_MODE=public`, worker menolak job `collect` dengan `failed` dan error `collect_disabled_in_public_mode`.

## 4. Payload job

Semua payload punya `schema_version`, `job_type`, `case_id`, `requested_by`, `params`.

### `collect` (mode lokal saja)

YouTube:
```json
{
  "schema_version": 1,
  "job_type": "collect",
  "case_id": 12,
  "requested_by": 3,
  "params": {
    "platform": "youtube",
    "video_ids": ["dQw4w9WgXcQ"],
    "max_items": 1000
  }
}
```

X via Apify (aktif hanya setelah izin panitia):
```json
{
  "schema_version": 1,
  "job_type": "collect",
  "case_id": 13,
  "requested_by": 3,
  "params": {
    "platform": "x",
    "query": "#ContohTagar",
    "max_items": 500,
    "since": "2026-11-01",
    "until": "2026-11-02"
  }
}
```

Sintetis (data demo dengan kunci jawaban; **boleh** di mode publik karena bukan data nyata). Dibuat lewat `php artisan radar:demo {preset} --seed=N`:
```json
{
  "schema_version": 1,
  "job_type": "collect",
  "case_id": 14,
  "requested_by": 1,
  "params": {
    "platform": "synthetic",
    "preset": "standar",
    "seed": 42
  }
}
```
`preset`: `standar` (3 klaster tanaman + pengecoh sah), `kontrol` (hanya organik + pengecoh), `parafrase` (2 klaster berparafrase tinggi). `seed`: 0–2147483647; seed sama menghasilkan data identik.

Batas, dicek di Laravel **dan** Python:
- `max_items`: 1–1000 untuk X, 1–5000 untuk YouTube.
- `video_ids`: 1–10 ID, masing-masing cocok dengan `^[A-Za-z0-9_-]{11}$`. Laravel mengekstrak ID dari link YouTube; yang dikirim ke antrean hanya ID.
- `query`: 1–200 karakter.
- `since` dan `until` (X): opsional, format `YYYY-MM-DD` atau `null`; `until` tidak boleh sebelum `since`.
- Kasus yang dibuat lewat formulir selalu `mode = live` dan langsung berstatus `collecting`, bersama satu job `collect` dalam satu transaksi.
- Pemetaan `params` ke input Actor Apify dilakukan di adapter Python, karena nama field input berbeda per Actor.

### `analyze`
```json
{
  "schema_version": 1,
  "job_type": "analyze",
  "case_id": 12,
  "requested_by": 3,
  "params": {
    "min_signals": 2,
    "min_cluster_size": 3,
    "time_window_seconds": 300,
    "min_co_events": 3,
    "minhash_threshold": 0.8,
    "tfidf_threshold": 0.6,
    "min_shared_texts": 2,
    "seed": 42
  }
}
```
Nilai ambang di atas adalah titik awal dan diganti hasil kalibrasi sintetis.

### `analyze_csv`
```json
{
  "schema_version": 1,
  "job_type": "analyze_csv",
  "case_id": 31,
  "requested_by": 7,
  "params": {
    "file_ref": "uploads/7f3c2a9e-5b1d-4c8e-9a20-1e6f3b7d8c41.csv",
    "delete_after_ingest": true,
    "use_embedding": true
  }
}
```
- `file_ref` dibuat server: `uploads/<uuid>.csv`, relatif terhadap disk `local` Laravel (`web/storage/app/private`). Worker membacanya lewat `RADAR_UPLOAD_DIR` dan **hanya** menerima pola UUID tersebut di dalam folder itu (mencegah path traversal).
- Boleh di mode publik (data milik pengunggah). File dihapus setelah dibaca, berhasil atau gagal; kasus `mode = csv` kedaluwarsa 24 jam (`expires_at`) dan dihapus `php artisan radar:purge-expired` (terjadwal tiap jam).
- `use_embedding`: `false` sampai modul embedding tersedia.
- Format CSV (diperiksa Laravel saat unggah **dan** worker): header wajib `akun,teks,waktu`; opsional `url,reply_to,tanggal_akun`; kolom lain ditolak; maks. 2.000 baris dan 2 MB; `teks` ≤ 1.000 karakter; `waktu` ISO 8601 (tanpa zona dianggap WIB); `tanggal_akun` `YYYY-MM-DD`. ID post dibentuk dari hash `akun|teks|waktu`, sehingga baris identik dihitung sekali.

### `evaluate`
```json
{
  "schema_version": 1,
  "job_type": "evaluate",
  "case_id": 40,
  "requested_by": 1,
  "params": {
    "paraphrase_levels": [0, 0.25, 0.5, 0.75, 1],
    "time_spreads_seconds": [60, 600, 3600, 21600],
    "cluster_sizes": [5, 10, 20],
    "calibration_seeds": [1, 2, 3],
    "test_seeds": [4, 5],
    "match_jaccard": 0.5
  }
}
```

## 5. `result_summary`

```json
{
  "schema_version": 1,
  "posts": 1840,
  "accounts": 912,
  "clusters": 14,
  "flagged": 3,
  "organic_score": 0.78,
  "duration_ms": 41230,
  "warnings": ["sinyal profil tidak tersedia untuk data YouTube"]
}
```
Untuk `collect`: `posts`, `accounts`, `duplicates_skipped`, `duration_ms`, `warnings`; collect YouTube menambah `api_requests` (jumlah permintaan ke API, kira-kira unit kuota terpakai). Angka di contoh hanya ilustrasi format.

## 6. `cluster_signals.evidence`

ID di bukti adalah `posts.id` atau `accounts.id` internal, tidak pernah ID asli platform. Setiap baris `cluster_signals` hanya memuat objek untuk sinyalnya sendiri.

**Kepadatan pasangan** (`pair_ratio`) = proporsi pasangan akun dalam klaster yang punya bukti. `baseline_ratio` = proporsi yang sama untuk seluruh pasangan akun di kasus. Sinyal teks dan waktu aktif bila kepadatan ≥ 0,3 **dan** minimal 5 kali baseline.

`text`:
```json
{"pair_ratio": 0.9, "baseline_ratio": 0.0004, "similar_post_pairs": 812,
 "top_pairs": [{"post_a": 101, "post_b": 205, "method": "minhash", "score": 0.93}]}
```
`method`: `minhash` (nyaris identik) atau `tfidf` (parafrase ringan).

`time` (aktif juga mensyaratkan minimal `min_co_events` kejadian):
```json
{"window_seconds": 300, "pair_ratio": 0.87, "baseline_ratio": 0.0001,
 "events": [{"start": "2026-11-01T05:00:00Z", "accounts": 29}], "accounts_in_min_events": 38}
```

`profile` (aktif bila ≥ 5 akun dan ≥ 50% anggota dibuat dalam jendela 30 hari yang sama, dan minimal 5 kali baseline):
```json
{"available": true, "window_days": 30, "window_start": "2026-10-10T03:12:00Z",
 "accounts_in_window": 38, "member_share": 0.95, "baseline_share": 0.06}
```
Bila tanggal pembuatan akun tidak ada (misalnya YouTube): `{"available": false, "coverage": 0.0}`.

`infra` (domain khas: dibagikan ≥ 3 akun dan ≥ 30% anggota, tetapi ≤ 2% akun lain):
```json
{"domains": [{"domain": "contoh.example", "accounts": 27, "member_share": 0.68,
  "other_share": 0.0, "distinctive": true, "age_days_at_first_post": null}]}
```
`age_days_at_first_post` diisi lewat RDAP (tier Bonus).

**Skor koordinasi** klaster = rata-rata `strength` keempat sinyal (0–1). **Skor keorganikan** kasus = 1 − (post milik anggota klaster yang ditandai ÷ seluruh post). Analisis ulang mengganti seluruh klaster dan skor kasus tersebut; penilaian analis atas klaster lama ikut terhapus.

## 7. Aturan pencocokan evaluasi

Klaster temuan cocok dengan klaster tanaman jika Jaccard anggotanya minimal 0,5.
- Recall = klaster tanaman yang tercocok ÷ total klaster tanaman.
- Precision = klaster ditandai yang cocok ÷ total klaster ditandai.
