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
