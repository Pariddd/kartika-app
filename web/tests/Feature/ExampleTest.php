<?php

use App\Models\User;

test('tamu melihat halaman depan yang menjelaskan proyek, tanpa data kasus', function () {
    App\Models\CaseFile::factory()->create(['name' => 'Kasus Rahasia Tim']);

    $this->get('/')
        ->assertOk()
        ->assertSee('SyncTrace')
        ->assertSee('bergerak serempak')
        ->assertSee('bukan bot dan bukan opini')
        ->assertSee(route('login'), false)
        ->assertSee('data sintetis')
        ->assertDontSee('Kasus Rahasia Tim');
});

test('angka evaluasi di halaman depan berasal dari berkas hasil uji', function () {
    $summary = App\Http\Controllers\EvaluationController::summary();

    $this->get('/')->assertSee($summary['caught'] . '<span class="text-2xl text-[#8d9a93]"> dari ' . $summary['planted'] . '</span>', false);
});

test('pengguna yang sudah masuk diarahkan dari halaman depan ke daftar kasus', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('cases.index'));
});

test('halaman depan memakai header keamanan dan tidak memuat skrip sebaris', function () {
    // CSP sengaja tidak dikirim saat `npm run dev` menyala (ada public/hot); test tidak boleh bergantung pada itu.
    config(['radar.vite_hot_file' => storage_path('framework/testing/tidak-ada-hot')]);
    $response = $this->get('/');

    expect($response->headers->get('Content-Security-Policy'))->toContain("script-src 'self'");
    expect($response->getContent())->not->toContain('<script>')->not->toContain('onclick=');
});

test('ilustrasi halaman depan deterministik: empat akun serempak tiga kali', function () {
    $strip = App\Support\SyncStrip::build();

    expect($strip)->toEqual(App\Support\SyncStrip::build())
        ->and(collect($strip['dots'])->where('sync', true)->count())->toBe(12)
        ->and($strip['events'])->toHaveCount(3)
        ->and(collect($strip['rows'])->where('coordinated', true)->count())->toBe(4);
});
