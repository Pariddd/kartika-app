<?php

use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\User;

test('hanya admin yang bisa membuka log audit, dan penolakan ikut tercatat dengan status 403', function () {
    $analis = User::factory()->create();
    $demo = User::factory()->demo()->create();

    $this->actingAs($analis)->get(route('audit.index'))->assertForbidden();
    $this->actingAs($demo)->get(route('audit.index'))->assertForbidden();

    $denied = AuditLog::where('action', 'audit.index')->get();
    expect($denied)->toHaveCount(2)
        ->and($denied->pluck('status')->unique()->all())->toBe([403]);

    $this->actingAs(User::factory()->admin()->create())->get(route('audit.index'))->assertOk();
});

test('tamu diarahkan ke halaman login', function () {
    $this->get(route('audit.index'))->assertRedirect(route('login'));
});

test('akses kasus yang ditolak tampil sebagai ditolak di log audit', function () {
    $case = CaseFile::factory()->live()->create(['name' => 'Kasus data nyata']);
    $demo = User::factory()->demo()->create(['name' => 'Juri Satu']);
    $this->actingAs($demo)->get(route('cases.show', $case))->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('audit.index', ['denied' => 1]))
        ->assertOk()
        ->assertSee('Juri Satu')
        ->assertSee('Membuka kasus')
        ->assertSee('Ditolak · 403')
        ->assertSee('Kasus data nyata');
});

test('filter pengguna dan aksi membatasi baris', function () {
    $admin = User::factory()->admin()->create(['name' => 'Admin Utama']);
    $analis = User::factory()->create(['name' => 'Analis Dua']);
    AuditLog::record('cases.index', $analis->id, null, '10.0.0.2', 200);
    AuditLog::record('login', $admin->id, null, '10.0.0.1');

    $this->actingAs($admin)
        ->get(route('audit.index', ['user' => $analis->id, 'action' => 'cases.index']))
        ->assertOk()
        ->assertSee('10.0.0.2')
        ->assertDontSee('10.0.0.1');
});

test('nilai filter yang tidak dikenal ditolak validasi', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('audit.index', ['action' => "x' OR 1=1"]))
        ->assertSessionHasErrors('action');
});

test('menu log audit hanya tampil untuk admin', function () {
    $this->actingAs(User::factory()->create())->get(route('cases.index'))->assertDontSee('Log audit');
    $this->actingAs(User::factory()->admin()->create())->get(route('cases.index'))->assertSee('Log audit');
});
