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
    | Sumber data. X via Apify hanya diaktifkan setelah ada izin tertulis panitia.
    */
    'sources' => [
        'x_enabled' => (bool) env('RADAR_X_ENABLED', false),
    ],

    /*
    | Batas job pengumpulan (docs/kontrak.md bagian 4). Python memeriksa batas yang sama.
    */
    'collect' => [
        'max_video_ids' => 10,
        'youtube_max_items' => 5000,
        'x_max_items' => 1000,
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
    | Tampilkan nama tampilan akun pada kasus data nyata. Hanya berlaku di mode lokal,
    | untuk admin dan analis, dan selalu kalah oleh mode presentasi. Pasangannya di
    | engine/.env adalah RADAR_STORE_ACCOUNT_NAMES (tanpa itu nama tidak pernah disimpan).
    */
    'show_account_names' => (bool) env('RADAR_SHOW_ACCOUNT_NAMES', false),

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
