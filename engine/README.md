# engine — worker Radar Narasi

Worker Python yang mengambil job dari tabel `analysis_jobs` (docs/kontrak.md bagian 3–4).
Login ke PostgreSQL sebagai `radar_engine`: hanya bisa membaca dan menulis data, tidak bisa mengubah tabel.

## Pemasangan (sekali, Git Bash di Windows)

```bash
cd engine
python -m venv .venv
source .venv/Scripts/activate
pip install -r requirements-dev.txt
cp .env.example .env      # lalu isi RADAR_DB_PASSWORD
```

## Menjalankan

```bash
source .venv/Scripts/activate
python -m radar.worker          # terus berjalan; Ctrl+C untuk berhenti setelah job selesai
python -m radar.worker --once   # proses paling banyak satu job lalu keluar
```

## Test

```bash
pytest                                   # test unit
RADAR_TEST_DSN="host=127.0.0.1 dbname=radar_narasi user=radar_web password=..." pytest
                                         # + test integrasi antrean (butuh antrean kosong)
```

## Status

- collect `synthetic`: membuat dataset demo + kunci jawaban (`php artisan radar:demo`).
- collect `youtube`: komentar + balasan lewat YouTube Data API v3 (`radar/youtube.py`), butuh `YOUTUBE_API_KEY`.
  ID kanal di-HMAC; URL dan foto profil tidak dibaca. Nama tampilan hanya disimpan bila
  `RADAR_STORE_ACCOUNT_NAMES=true` di mode lokal (lihat `docs/aturan-kerja.md`). Sekitar 1 unit kuota per 100 komentar.
- collect `x`, `instagram`, `tiktok`: impor file JSON hasil ekspor Apify (`radar/apify_import.py`). Paket gratis Apify
  umumnya tidak bisa memanggil Actor lewat API, jadi Actor dijalankan di Apify Console lalu hasilnya diunggah di web.
  Pembaca mencoba nama field yang lazim dan gagal dengan pesan jelas bila format Actor tidak dikenali.
  Sudah diuji dengan ekspor asli `apidojo/tweet-scraper`; format Instagram dan TikTok baru diuji dengan data tiruan.
  Retweet dilewati; bio, lokasi, dan foto tidak dibaca; file dihapus setelah diimpor.
- analyze: MinHash + TF-IDF karakter (teks), ko-aktivitas waktu, graf + Louvain
  (`radar/analysis/louvain.py`, implementasi sendiri yang dijamin berhenti), empat sinyal terhadap baseline.
- analyze_csv: CSV unggahan; file dihapus setelah dibaca.

## Evaluasi dan uji ketahanan

```bash
python -m radar.analysis.robustness --out ../web/resources/evaluation/hasil-evaluasi.json   # sekitar 3 menit
```

Hasilnya dibaca halaman **Evaluasi** di web. Ambang disetel dengan seed 1-3; hasil uji memakai seed 4-10.
Semua data sintetis: angka ini bukan akurasi pada data dunia nyata.
