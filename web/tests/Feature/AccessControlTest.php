<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'mode.local'])
        ->get('/_uji/kumpulkan', fn () => 'ok');

    Route::middleware(['web', 'auth', 'audit'])
        ->get('/_uji/kasus/{case}', fn (string $case) => 'ok')
        ->name('uji.kasus');
});

test('mode publik menolak rute pengumpulan data di sisi server', function () {
    config(['radar.mode' => 'public']);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/_uji/kumpulkan')
        ->assertForbidden();
});

test('mode lokal mengizinkan rute pengumpulan data', function () {
    config(['radar.mode' => 'local']);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/_uji/kumpulkan')
        ->assertOk();
});

test('izin collect-data bergantung pada peran dan mode', function (Role $role, string $mode, bool $allowed) {
    config(['radar.mode' => $mode]);

    $user = User::factory()->create(['role' => $role]);

    expect(Gate::forUser($user)->allows('collect-data'))->toBe($allowed);
})->with([
    'admin, lokal' => [Role::Admin, 'local', true],
    'analis, lokal' => [Role::Analis, 'local', true],
    'demo, lokal' => [Role::Demo, 'local', false],
    'admin, publik' => [Role::Admin, 'public', false],
    'analis, publik' => [Role::Analis, 'public', false],
]);

test('hanya admin yang boleh melihat log audit', function (Role $role, bool $allowed) {
    $user = User::factory()->create(['role' => $role]);

    expect(Gate::forUser($user)->allows('view-audit-log'))->toBe($allowed);
})->with([
    [Role::Admin, true],
    [Role::Analis, false],
    [Role::Demo, false],
]);

test('akses rute beraudit tercatat dengan pengguna dan IP', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/_uji/kasus/123')->assertOk();

    $log = AuditLog::sole();
    expect($log->action)->toBe('uji.kasus')
        ->and($log->user_id)->toBe($user->id)
        ->and($log->case_id)->toBeNull()
        ->and($log->ip)->toBe('127.0.0.1');
});

test('peran tidak bisa diisi lewat mass assignment', function () {
    expect(fn () => User::create([
        'name' => 'Penyusup',
        'email' => 'penyusup@radar.local',
        'password' => 'kata-sandi-panjang',
        'role' => 'admin',
    ]))->toThrow(MassAssignmentException::class);
});
