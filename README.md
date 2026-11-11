# Radar Narasi

Deteksi dini kampanye terkoordinasi di media sosial dengan machine learning (NLP), analisis graf, dan OSINT konten.
Karya untuk **Kartika Cyber Hackathon 2026** (PUSSIBERAD × CODEPOLITAN), Kategori Siber Sosial.

Radar Narasi menjawab satu pertanyaan: *apakah sebuah isu ramai karena banyak orang, atau karena sedikit orang yang bergerak bersama?*
Yang dideteksi adalah **koordinasi**: klaster akun yang berulang kali menulis hal serupa pada waktu yang sama.
Sistem ini bukan pendeteksi bot, bukan pendeteksi hoaks, dan tidak menilai opini. Sistem menyajikan bukti; analis yang memutuskan.

## Fitur

| Fitur | Keterangan |
| --- | --- |
| Kasus pemantauan | Komentar YouTube (YouTube Data API v3); X, Instagram, dan TikTok lewat impor file ekspor Apify; data sintetis untuk demo; atau CSV unggahan |
| Empat sinyal | Kemiripan teks, ko-aktivitas waktu, profil akun, infrastruktur (domain). Klaster ditandai bila minimal 2 sinyal aktif terhadap baseline kasus |
| Bukti yang bisa diperiksa | Pasangan teks mirip, kejadian serentak, domain khas, anggota klaster |
| Lini waktu | Post per rentang waktu, dipisah antara klaster ditandai dan post lain |
| Penilaian analis | Terkoordinasi, keliru, atau belum pasti; tidak ada tindakan otomatis |
| Ringkasan BLUF | Kesimpulan di kalimat pertama, lalu bukti, saran, dan keterbatasan; siap cetak |
| Kunci jawaban | Kasus sintetis menyimpan klaster tanaman; juri bisa membandingkan temuan sistem dengan jawabannya |
| Coba Sendiri | Unggah CSV (`akun, teks, waktu`); file dihapus setelah dibaca, kasus dihapus setelah 24 jam |
| Evaluasi | Hasil uji sintetis dan kurva ketahanan, termasuk titik saat deteksi gagal |
| Log audit | Semua akses tercatat, termasuk yang ditolak; halaman khusus admin |

## Arsitektur

```
web/     Laravel 13 (PHP 8.4): login, kasus, bukti, ringkasan, log audit. Pemilik skema database.
engine/  Python 3.10: adapter data, pseudonimisasi, analisis, worker antrean.
docs/    kontrak.md (skema dan format antrean), aturan-kerja.md, setup-database.sql
```

Laravel dan Python hanya berkomunikasi lewat PostgreSQL. Laravel membuat job di tabel `analysis_jobs`;
worker Python mengambilnya dengan `SELECT ... FOR UPDATE SKIP LOCKED`. Laravel tidak pernah menjalankan perintah shell.

Dua user database: `radar_web` (pemilik skema) dan `radar_engine` (hanya baca/tulis data, tanpa akses ke tabel pengguna dan log audit).

## Menjalankan (pengembangan)

Butuh PHP 8.3+, Composer, Node 20+, Python 3.10+, PostgreSQL 16+.

```bash
# 1. Database: jalankan docs/setup-database.sql Bagian A sebagai superuser (database wajib UTF-8)

# 2. Web
cd web
composer install && npm install
cp .env.example .env && php artisan key:generate    # lalu isi DB_PASSWORD dan SEED_*_PASSWORD
php artisan migrate --seed
npm run build
# jalankan docs/setup-database.sql Bagian B, lalu:
php artisan serve

# 3. Engine
cd ../engine
python -m venv .venv && source .venv/bin/activate   # Windows Git Bash: source .venv/Scripts/activate
pip install -r requirements-dev.txt
cp .env.example .env                                # isi RADAR_DB_PASSWORD dan PSEUDO_KEY (openssl rand -hex 32)
python -m radar.worker

# 4. Kasus demo
cd ../web && php artisan radar:demo standar
```

Test: `php artisan test` di `web/`, `pytest` di `engine/`.

## Mode aplikasi

| `APP_MODE` | Dipakai di | Perilaku |
| --- | --- | --- |
| `local` | Laptop analis | Pengumpulan data nyata aktif |
| `public` | Server demo | Hanya data sintetis dan CSV unggahan. Pengumpulan data ditolak di middleware Laravel **dan** di worker. Nilai kosong atau salah dianggap `public` |

## Privasi dan keamanan

- ID akun, ID post, dan ID percakapan di-HMAC-SHA256 dengan kunci rahasia sebelum disimpan. ID asli, URL profil, dan foto tidak pernah disimpan.
- Mention di dalam teks diganti pseudonim. Label akun (`Akun-0001`) hanya berlaku di dalam satu kasus.
- Nama tampilan akun tidak disimpan secara bawaan. Opsi untuk analis berwenang di mode lokal diatur di `docs/aturan-kerja.md` dan setiap aksesnya tercatat di log audit.
- OSINT berpusat pada konten (teks, domain, waktu), bukan pada orang.
- Kata kasar dan hinaan SARA dari daftar penyamaran tampil sebagai `[konten disamarkan]` di mode publik.
- Query berparameter, Blade selalu di-escape, Content-Security-Policy tanpa sumber luar, rate limit pada login dan unggahan, rahasia hanya di `.env`.

## Evaluasi

Klaster buatan ditanam ke percakapan sintetis, lalu diperiksa apakah sistem menemukannya (cocok bila Jaccard ≥ 0,5).
Pada 21 dataset uji (seed yang tidak dipakai menyetel ambang): 35 dari 35 klaster tanaman ditemukan, 0 klaster keliru ditandai.

Batas yang diketahui dari uji ketahanan: klaster yang menyebar postingannya lebih dari jendela 5 menit, atau bergerak bersama kurang dari 3 kali, lolos bila tidak ada sinyal lain (domain khas atau akun seumur).
Semua angka berasal dari data sintetis dan **bukan** akurasi pada data dunia nyata. Rincian ada di halaman Evaluasi; hasil bisa diulang dengan `python -m radar.analysis.robustness`.

## Keterbatasan

- Koordinasi tidak selalu berarti manipulasi; komunitas dan pengumuman resmi juga bergerak bersama.
- Hasil hanya berlaku untuk sampel yang dikumpulkan.
- Sinyal profil tidak tersedia untuk YouTube (API tidak memberi tanggal pembuatan kanal).
- Kemiripan teks memakai MinHash dan TF-IDF karakter; parafrase bebas oleh model bahasa belum diuji.
- X, Instagram, dan TikTok masuk lewat impor file ekspor Apify, bukan pengambilan langsung. Format Instagram dan TikTok baru diuji dengan data tiruan.
- Satu kasus dibatasi 20.000 post karena kebutuhan memori analisis.

## Pustaka dan layanan pihak ketiga

Laravel, Pest, Tailwind CSS, Vite, IBM Plex (via Fontsource), psycopg, scikit-learn, NumPy, NetworkX, datasketch, python-dotenv, PostgreSQL, YouTube Data API v3, Apify (Actor pihak ketiga, dijalankan terpisah oleh analis).
Semuanya open source atau dipakai sesuai ketentuan layanannya. Tidak ada layanan berbayar.

## Deklarasi

Seluruh kode ditulis selama periode program, tanpa karya pre-existing. Pengembangan dibantu asisten AI untuk penulisan kode dan pengujian;
rancangan, keputusan, dan pemeriksaan hasil dilakukan oleh tim.
