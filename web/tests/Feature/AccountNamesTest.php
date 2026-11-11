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
    'fitur dimatikan' => [['radar.show_account_names' => false]],
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

test('teks dengan username asli tampil untuk analis; akun demo tidak bisa membuka kasus data nyata', function () {
    config(['radar.mode' => 'local', 'radar.show_account_names' => true, 'radar.presentation_mask' => false]);
    [$case, $k1] = liveCaseWithNames();
    DB::table('posts')->where('case_id', $case->id)->orderBy('id')->limit(1)
        ->update(['text' => 'Setuju dengan @u_3fa9c2d1 soal pasar.', 'text_raw' => 'Setuju dengan @budi_asli soal pasar.']);

    $this->actingAs(User::factory()->create())
        ->get(route('clusters.show', [$case, $k1]))
        ->assertSee('@budi_asli')
        ->assertDontSee('@u_3fa9c2d1');

    $this->actingAs(User::factory()->demo()->create())
        ->get(route('clusters.show', [$case, $k1]))
        ->assertForbidden()
        ->assertDontSee('@budi_asli')
        ->assertDontSee('Nama Kanal Satu');
});

test('nama akun di graf hanya dikirim untuk admin dan analis, dan tercatat di log audit', function () {
    config(['radar.mode' => 'local', 'radar.show_account_names' => true, 'radar.presentation_mask' => false]);
    [$case] = liveCaseWithNames();
    $accounts = DB::table('accounts')->where('case_id', $case->id)->orderBy('id')->pluck('id')->values();
    DB::table('case_graphs')->insert([
        'case_id' => $case->id, 'job_id' => $case->jobs()->first()->id, 'computed_at' => now(),
        'graph' => json_encode([
            'nodes' => [
                ['id' => $accounts[0], 'x' => 100, 'y' => 100, 'cluster' => 'K-01', 'flagged' => true, 'posts' => 6, 'links' => 1],
                ['id' => $accounts[1], 'x' => 200, 'y' => 150, 'cluster' => 'K-01', 'flagged' => true, 'posts' => 5, 'links' => 1],
            ],
            'edges' => [['a' => $accounts[0], 'b' => $accounts[1], 'text' => true, 'time' => true, 'w' => 2.0]],
            'total_nodes' => 2, 'total_edges' => 1, 'truncated' => false,
        ]),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('cases.show', $case))
        ->assertOk()
        ->assertSee('Nama Kanal Satu');
    expect(AuditLog::where('action', 'cases.names')->where('case_id', $case->id)->count())->toBe(1);

    $this->actingAs(User::factory()->demo()->create())
        ->get(route('cases.show', $case))
        ->assertForbidden()
        ->assertDontSee('Nama Kanal Satu');
    expect(AuditLog::where('action', 'cases.names')->count())->toBe(1);
});

test('teks asli tidak ikut saat post diubah menjadi array atau JSON', function () {
    [$case] = liveCaseWithNames();
    DB::table('posts')->where('case_id', $case->id)->update(['text_raw' => 'rahasia @budi_asli']);

    expect(App\Models\Post::where('case_id', $case->id)->first()->toArray())->not->toHaveKey('text_raw');
});
