<?php

use App\Models\AuditLog;
use App\Models\User;

test('halaman login tampil', function () {
    $this->get('/login')->assertOk()->assertSee('Masuk');
});

test('tamu tidak bisa membuka halaman kasus', function () {
    $this->get('/kasus')->assertRedirect('/login');
});

test('tidak ada halaman pendaftaran publik', function () {
    $this->get('/register')->assertNotFound();
});

test('login dengan kredensial benar berhasil dan tercatat di audit', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('cases.index'));

    $this->assertAuthenticatedAs($user);
    expect(AuditLog::where('action', 'login')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('login dengan kata sandi salah ditolak dan tercatat di audit', function () {
    $user = User::factory()->create();

    $this->from('/login')
        ->post('/login', ['email' => $user->email, 'password' => 'salah-total'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);

    $this->assertGuest();
    expect(AuditLog::where('action', 'login_gagal')->whereNull('user_id')->count())->toBe(1);
});

test('email tidak terdaftar mendapat pesan yang sama dengan kata sandi salah', function () {
    $this->post('/login', ['email' => 'tidak-ada@radar.local', 'password' => 'apa-saja'])
        ->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);
});

test('login dikunci setelah 5 percobaan gagal, walau kata sandi berikutnya benar', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'salah-total']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(session('errors')->first('email'))->toContain('Terlalu banyak percobaan');
});

test('logout mengakhiri sesi dan tercatat di audit', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

    $this->assertGuest();
    expect(AuditLog::where('action', 'logout')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('pengguna yang sudah login diarahkan dari halaman login ke daftar kasus', function () {
    $this->actingAs(User::factory()->create())
        ->get('/login')
        ->assertRedirect(route('cases.index'));
});
