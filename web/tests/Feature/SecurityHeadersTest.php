<?php

use App\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Test tidak boleh bergantung pada "npm run dev" yang sedang menyala di laptop (file public/hot).
    config(['radar.vite_hot_file' => storage_path('framework/testing/tidak-ada-hot')]);
});

test('saat server pengembangan Vite menyala CSP tidak dikirim, header lain tetap ada', function () {
    $hot = storage_path('framework/testing/hot-uji');
    @mkdir(dirname($hot), 0777, true);
    file_put_contents($hot, 'http://localhost:5173');
    config(['radar.vite_hot_file' => $hot]);

    try {
        $this->get(route('login'))
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertHeader('X-Frame-Options', 'DENY');
    } finally {
        @unlink($hot);
    }
});

test('setiap halaman membawa header keamanan dan CSP tanpa sumber luar', function () {
    $response = $this->get(route('login'))->assertOk();

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("default-src 'self'")
        ->toContain("script-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("form-action 'self'")
        ->not->toContain('http')
        ->not->toContain("script-src 'self' 'unsafe-inline'");
});

test('tidak ada atribut onclick atau skrip inline di halaman, supaya CSP tidak perlu dilonggarkan', function () {
    $views = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($file) => preg_match('/\son[a-z]+=|<script(?![^>]*src)/i', $file->getContents()));

    expect($views->map->getRelativePathname()->values()->all())->toBe([]);
});

test('HSTS hanya dikirim lewat HTTPS', function () {
    $user = User::factory()->demo()->create();

    $this->actingAs($user)->get('http://localhost/kasus')->assertHeaderMissing('Strict-Transport-Security');
    $this->actingAs($user)->get('https://localhost/kasus')->assertHeader('Strict-Transport-Security');
});
