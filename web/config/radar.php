<?php

use App\Support\AppMode;

return [

    /*
    | APP_MODE: "local" atau "public". Selalu baca lewat config('radar.mode')
    | atau App\Support\AppMode, jangan env() langsung: setelah config:cache,
    | env() di luar folder config mengembalikan null.
    */
    'mode' => AppMode::normalize(env('APP_MODE')),

    /*
    | Sumber data. Impor ekspor Apify (X, Instagram, TikTok) dinyalakan dengan RADAR_APIFY_ENABLED.
    | RADAR_X_ENABLED adalah nama lama dan tetap dibaca.
    */
    'sources' => [
        'import_enabled' => (bool) env('RADAR_APIFY_ENABLED', env('RADAR_X_ENABLED', false)),
    ],

    /*
    | Batas job pengumpulan (docs/kontrak.md bagian 4). Python memeriksa batas yang sama.
    */
    'collect' => [
        'max_video_ids' => 10,
        // Batas per kasus untuk semua sumber data nyata. Memori analisis tumbuh kira-kira kuadratik
        // (10.000 post sekitar 1,6 GB; 20.000 post sekitar 3 GB), jadi tidak bisa tanpa batas.
        // Ubah lewat RADAR_MAX_ITEMS (isi nilai yang sama di engine/.env).
        'max_items' => max(1, min(200000, (int) env('RADAR_MAX_ITEMS', 20000))),
        'import_file_max_kb' => 204800,
        'query_max_length' => 200,
    ],

    /*
    | Zona waktu tampilan. Database selalu menyimpan UTC.
    */
    'display_timezone' => 'Asia/Jakarta',

    /*
    | Mode presentasi: teks post dan domain dari kasus data nyata disamarkan di layar
    | (untuk demo di depan juri). Angka dan bukti struktural tetap tampil.
    */
    'presentation_mask' => (bool) env('RADAR_PRESENTATION_MASK', false),

    /*
    | Tampilkan nama akun dan @username asli pada kasus data nyata. Hanya berlaku di mode lokal,
    | untuk admin dan analis, dan selalu kalah oleh mode presentasi. Akun demo selalu melihat
    | pseudonim. Isi RADAR_SHOW_ACCOUNT_NAMES=false untuk mematikan. Pasangannya di engine/.env
    | adalah RADAR_STORE_ACCOUNT_NAMES (tanpa itu nama tidak pernah disimpan).
    */
    'show_account_names' => (bool) env('RADAR_SHOW_ACCOUNT_NAMES', true),

    /*
    | Penanda server pengembangan Vite (npm run dev). Selama file ini ada, CSP tidak dikirim
    | karena aset dimuat dari origin lain. Di server hasil build file ini tidak pernah ada.
    */
    'vite_hot_file' => public_path('hot'),

    /*
    | Akun awal untuk seeder. Kata sandi wajib diisi di .env (minimal 8 karakter);
    | tidak ada kata sandi bawaan.
    */
    'seed' => [
        'admin' => [
            'email' => env('SEED_ADMIN_EMAIL', 'admin@radar.local'),
            'password' => env('SEED_ADMIN_PASSWORD'),
        ],
        'analis' => [
            'email' => env('SEED_ANALIS_EMAIL', 'analis@radar.local'),
            'password' => env('SEED_ANALIS_PASSWORD'),
        ],
        'demo' => [
            'email' => env('SEED_DEMO_EMAIL', 'demo@radar.local'),
            'password' => env('SEED_DEMO_PASSWORD'),
        ],
    ],

];
