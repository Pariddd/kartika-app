<?php

use App\Enums\CaseMode;
use App\Enums\CaseStatus;
use App\Models\AnalysisJob;
use App\Models\CaseFile;
use App\Models\User;

test('radar:demo membuat kasus demo dan job collect sintetis sesuai kontrak', function () {
    $admin = User::factory()->admin()->create();

    $this->artisan('radar:demo', ['preset' => 'standar', '--seed' => 7])->assertSuccessful();

    $case = CaseFile::sole();
    expect($case->mode)->toBe(CaseMode::Demo)
        ->and($case->status)->toBe(CaseStatus::Collecting)
        ->and($case->sources)->toBe(['synthetic'])
        ->and($case->created_by)->toBe($admin->id)
        ->and(AnalysisJob::sole()->payload)->toEqual([
            'schema_version' => 1,
            'job_type' => 'collect',
            'case_id' => $case->id,
            'requested_by' => $admin->id,
            'params' => ['platform' => 'synthetic', 'preset' => 'standar', 'seed' => 7],
        ]);
});

test('radar:demo boleh dijalankan di mode publik', function () {
    config(['radar.mode' => 'public']);
    User::factory()->admin()->create();

    $this->artisan('radar:demo', ['preset' => 'kontrol'])->assertSuccessful();

    expect(CaseFile::sole()->name)->toBe('Kontrol organik: Pasar Lama Kota Sentosa');
});

test('radar:demo menolak input yang salah tanpa membuat apa pun', function (array $arguments) {
    User::factory()->admin()->create();

    $this->artisan('radar:demo', $arguments)->assertFailed();

    expect(CaseFile::count())->toBe(0)->and(AnalysisJob::count())->toBe(0);
})->with([
    'preset tidak dikenal' => [['preset' => 'rahasia']],
    'seed negatif' => [['--seed' => -1]],
    'seed bukan angka' => [['--seed' => 'abc']],
    'pengguna tidak ada' => [['--user' => 'tidak-ada@radar.local']],
]);

test('radar:demo gagal bila belum ada admin', function () {
    $this->artisan('radar:demo')->assertFailed();
});

test('akun demo bisa membuka kasus demo', function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->artisan('radar:demo')->assertSuccessful();

    $this->actingAs(User::factory()->demo()->create())
        ->get(route('cases.show', CaseFile::sole()))
        ->assertOk()
        ->assertSee('Sintetis');
});
