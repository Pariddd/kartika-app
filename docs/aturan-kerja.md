# Radar Narasi — aturan kerja tim

Radar Narasi adalah aplikasi web untuk Kartika Cyber Hackathon 2026 (Kategori Siber Sosial). Sistem mendeteksi **koordinasi** di media sosial: klaster akun yang bergerak bersama secara tidak wajar. Sistem menyajikan bukti; analis yang memutuskan. Tidak ada tindakan otomatis.

Kontrak data dan antrean ada di `docs/kontrak.md`. File itu sumber kebenaran: jangan mengubah skema, nama kolom, enum, atau format JSON tanpa persetujuan ketiga anggota tim.

## Istilah wajib

- Yang dideteksi adalah **koordinasi**, bukan bot dan bukan opini.
- Radar Narasi **bukan** pendeteksi hoaks. Ia menilai cara pesan disebarkan, bukan benar-salahnya isi.
- Tulis "machine learning (NLP) dan analisis graf", bukan "berbasis AI". Tim tidak melatih model klasifikasi; hanya memakai model embedding pra-latih dan algoritma unsupervised.

## Struktur repo

```
kartika-app/
├── docs/
│   ├── aturan-kerja.md    file ini
│   ├── kontrak.md         skema tabel, enum, format JSON antrean
│   └── setup-database.sql
├── web/                   Laravel 13 + Tailwind 4: login, kasus, dashboard, bukti, laporan, log audit, migration
├── engine/                Python: adapter data, pseudonimisasi, analisis, worker
├── docker-compose.yml     (dibuat saat persiapan deploy)
└── .gitignore
```

Versi: PHP 8.4, Laravel 13, PostgreSQL 18 (lokal lewat DBngin; server memakai `postgres:18`). Test Laravel memakai Pest.

## Pembagian Laravel dan Python

| Laravel (`web/`) | Python (`engine/`) |
| --- | --- |
| Menampilkan, tidak memproses data | Memproses, tidak menampilkan |
| Pemilik skema: semua migration ada di sini | Tidak pernah menjalankan DDL |
| Membuat job `pending` di `analysis_jobs` | Mengambil job, memperbarui status, menulis hasil |
| Menulis `cases`, `cluster_reviews`, `audit_logs`, `users` | Menulis `accounts`, `posts`, `domains`, `clusters`, `cluster_members`, `cluster_signals`, `issue_scores`, `demo_ground_truth` |

Keduanya hanya berkomunikasi lewat PostgreSQL. Laravel **tidak pernah** memanggil Python lewat `shell_exec`, `exec`, `system`, `proc_open`, atau `Process`.

## Mode aplikasi

`APP_MODE` di `.env`: `local` atau `public`.

- `local` (laptop tim): data nyata, job `collect` aktif.
- `public` (server juri): hanya data sintetis dan CSV unggahan. Rute dan job `collect` **ditolak di sisi server** lewat middleware Laravel dan pengecekan di worker Python, bukan hanya disembunyikan di tampilan.

## Aturan keras

### Privasi (pelanggaran = diskualifikasi tanpa pemberitahuan)

- ID akun, ID post, ID balasan, dan ID percakapan di-HMAC-SHA256 dengan `PSEUDO_KEY` **sebelum** ditulis ke database. Simpan hash penuh 64 hex.
- Hanya ada **satu** implementasi pseudonimisasi: `engine/radar/pseudo.py`. Semua adapter dan worker CSV memakainya. Tanpa `PSEUDO_KEY`, proses berhenti dengan error (fail closed).
- Username, ID asli, URL profil, foto profil, bio, dan lokasi **tidak disimpan sama sekali**.
- Nama tampilan akun **tidak disimpan secara bawaan**. Pengecualian (keputusan ketua tim 11 November 2026, berdasarkan konfirmasi panitia): nama tampilan boleh disimpan dan diperlihatkan untuk kasus data nyata bila **semua** syarat ini terpenuhi:
  - `APP_MODE=local` (laptop tim), tidak pernah di server publik;
  - `RADAR_STORE_ACCOUNT_NAMES=true` di `engine/.env` dan `RADAR_SHOW_ACCOUNT_NAMES=true` di `web/.env`;
  - `RADAR_PRESENTATION_MASK` mati;
  - pengguna berperan admin atau analis. Setiap kali nama diperlihatkan, log audit mencatat `clusters.names`.
  - Hanya nama tampilan; ID kanal tetap di-HMAC, URL profil dan foto tetap tidak dibaca.
  - Nama tidak boleh muncul di link publik, screenshot, video, atau slide submission. Bukti konfirmasi panitia disimpan tim.
- Mention `@username` di dalam teks diganti pseudonim saat normalisasi.
- Label tampilan akun dibuat berurutan per kasus (`Akun-0001`, `Akun-0002`, ...). Jangan memakai potongan hash sebagai label karena rawan bertabrakan.
- OSINT berpusat pada konten: domain, link, gambar, metadata teknis RDAP. **Dilarang**: menelusuri username di platform lain, mencocokkan akun dengan nama asli, foto, lokasi, kontak, atau data pemilik domain.
- Konten SARA dan kata kasar tampil sebagai `[konten disamarkan]` di mode publik.
- Data akun disimpan per kasus, tidak pernah dihubungkan lintas kasus.

### Keamanan kode

- Query hanya lewat Eloquent, Query Builder dengan binding, atau parameter psycopg. Tidak ada SQL yang dirangkai dari string input.
- Blade selalu `{{ }}`. Dilarang `{!! !!}` untuk teks yang berasal dari data (XSS, CWE-79).
- Rahasia (`PSEUDO_KEY`, `APIFY_TOKEN`, `YOUTUBE_API_KEY`, password DB) hanya di `.env`. Jangan pernah di-commit, di-log, atau dikirim ke front-end. Token API sumber data hanya dibutuhkan container `engine`, bukan `web`.
- Unggahan CSV: validasi lewat Form Request (tipe, ukuran, header, jumlah baris), simpan di storage privat dengan nama UUID buatan server. Path file tidak pernah berasal dari input pengguna.
- Ekspor CSV: escape sel yang diawali `=`, `+`, `-`, `@` (CWE-1236).
- Ekspansi link hanya di mode lokal: timeout, batas redirect, tolak IP privat dan loopback di setiap lompatan (SSRF, CWE-918).
- Rate limit pada login, unggahan, dan pembuatan kasus.
- Batas `max_items` untuk job `collect` dicek di Laravel **dan** di Python (lihat `docs/kontrak.md`).

### Biaya dan cakupan

- Biaya pembangunan Rp0: hanya paket gratis. Jangan menambah layanan berbayar atau dependensi yang butuh kunci berbayar.
- Fitur dibekukan **11 November 2026**. Setelah itu hanya perbaikan bug, deploy, dan dokumentasi.
- Di luar cakupan: Telegram dan platform lain, streaming real-time, ringkasan LLM, aplikasi mobile, melatih model sendiri, OSINT berpusat pada orang.
- Aset front-end (termasuk library graf) dibundel lewat Vite, bukan dari CDN, agar aplikasi jalan offline.

## Konvensi

- Antarmuka berbahasa Indonesia.
- Laravel: validasi di Form Request, otorisasi peran (`admin`, `analis`, `demo`) di Policy atau Gate, log audit lewat middleware.
- Python: type hints, `pytest`, satu paket `engine/radar/`, ambang analisis dibaca dari `params` job, bukan di-hardcode.
- Analisis harus bisa diulang: seed tetap untuk Louvain dan generator data sintetis.
- Klaim akurasi di UI atau dokumentasi hanya boleh berasal dari hasil evaluasi sintetis, dengan parameter yang disebut.

## Sebelum menyatakan tugas selesai

1. Test yang relevan dijalankan dan lulus.
2. Tidak ada ID asli, username, atau rahasia di database, log, atau output.
3. Tidak ada perubahan pada `docs/kontrak.md` atau migration yang belum disepakati tim.
4. Rute baru yang menyentuh data sudah melewati middleware mode publik dan log audit.

Jika ragu apakah sebuah perubahan melanggar aturan di atas, berhenti dan tanyakan.
