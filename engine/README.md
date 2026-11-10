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
- collect `youtube`/`x`: stub sampai adapter dibuat.
- analyze: MinHash + TF-IDF karakter (teks), ko-aktivitas waktu, graf + Louvain,
  empat sinyal terhadap baseline (`radar/analysis/`). Embedding multibahasa menyusul.

Kalibrasi awal (10 seed per preset sintetis): semua klaster tanaman tertangkap, tanpa salah
tanda, pengecoh sah tidak ditandai. Ini hasil pada data sintetis buatan tim, bukan akurasi dunia nyata.
