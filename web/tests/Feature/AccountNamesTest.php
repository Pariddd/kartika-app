<?php

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function liveCaseWithNames(): array
{
    $case = CaseFile::factory()->live()->create();
    [$k1] = seedAnalysis($case);
    DB::table('accounts')->where('case_id', $case->id)->where('display_label', 'Akun-0001')->update(['display_name' => 'Nama Kanal Satu']);

    return [$case, $k1];
}

test('nama akun tampil untuk analis di mode lokal bila fitur dinyalakan, dan tercatat di log audit', function () {
    config(['radar.mode' => 'local', 'radar.show_account_names' => true, 'radar.presentation_mask' => false]);
    [$case, $k1] = liveCaseWithNames();

    $this->actingAs(User::factory()->create())
        ->get(route('clusters.show', [$case, $k1]))
        ->assertOk()
        ->assertSee('Nama Kanal Satu')
        ->assertSee('Akun-0001');

    expect(AuditLog::where('action', 'clusters.names')->where('case_id', $case->id)->count())->toBe(1);
});

test('nama akun tidak tampil bila salah satu syarat tidak terpenuhi', function (array $config) {
    config(array_merge(['radar.mode' => 'local', 'radar.show_account_names' => true, 'radar.presentation_mask' => false], $config));
    [$case, $k1] = liveCaseWithNames();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('clusters.show', [$case, $k1]))
        ->assertOk()
        ->assertDontSee('Nama Kanal Satu')
        ->assertSee('Akun-0001');

    expect(AuditLog::where('action', 'clusters.names')->exists())->toBeFalse();
})->with([
    'fitur mati (bawaan)' => [['radar.show_account_names' => false]],
    'mode publik' => [['radar.mode' => 'public']],
    'mode presentasi' => [['radar.presentation_mask' => true]],
]);

test('nama tidak pernah tampil pada kasus sintetis atau unggahan', function () {
    config(['radar.mode' => 'local', 'radar.show_account_names' => true]);
    $case = CaseFile::factory()->create();
    [$k1] = seedAnalysis($case);
    DB::table('accounts')->where('case_id', $case->id)->update(['display_name' => 'Nama Kanal Satu']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('clusters.show', [$case, $k1]))
        ->assertDontSee('Nama Kanal Satu');
});

test('nama akun tidak ikut saat model diubah menjadi array atau JSON', function () {
    [$case] = liveCaseWithNames();
    $account = Account::where('case_id', $case->id)->where('display_label', 'Akun-0001')->first();

    expect($account->toArray())->not->toHaveKey('display_name')->not->toHaveKey('account_key');
});
