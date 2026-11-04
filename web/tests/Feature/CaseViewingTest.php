<?php

use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\User;

test('daftar kasus menampilkan kasus yang terlihat oleh pengguna', function () {
    $analis = User::factory()->create();
    CaseFile::factory()->create(['name' => 'Kasus demo A']);
    CaseFile::factory()->live()->create(['name' => 'Kasus nyata B']);

    $this->actingAs($analis)->get('/kasus')
        ->assertOk()
        ->assertSee('Kasus demo A')
        ->assertSee('Kasus nyata B');
});

test('akun demo tidak melihat kasus data nyata di daftar maupun detail', function () {
    $demo = User::factory()->demo()->create();
    CaseFile::factory()->create(['name' => 'Kasus demo A']);
    $live = CaseFile::factory()->live()->create(['name' => 'Kasus nyata B']);

    $this->actingAs($demo)->get('/kasus')
        ->assertOk()
        ->assertSee('Kasus demo A')
        ->assertDontSee('Kasus nyata B');

    $this->actingAs($demo)->get(route('cases.show', $live))->assertForbidden();

    // Percobaan yang ditolak tetap tercatat.
    expect(AuditLog::where('action', 'cases.show')->where('case_id', $live->id)->exists())->toBeTrue();
});

test('detail kasus menampilkan antrean dan mencatat audit', function () {
    $user = User::factory()->create();
    $case = CaseFile::factory()->live()->create(['status' => 'collecting']);
    $case->jobs()->create([
        'job_type' => JobType::Collect,
        'status' => JobStatus::Pending,
        'payload' => ['schema_version' => 1],
    ]);

    $this->actingAs($user)->get(route('cases.show', $case))
        ->assertOk()
        ->assertSee('Pengumpulan data')
        ->assertSee('Menunggu worker mengambil job.')
        ->assertSee('dQw4w9WgXcQ')
        ->assertSee('<meta http-equiv="refresh" content="10">', escape: false);

    expect(AuditLog::where('action', 'cases.show')->where('case_id', $case->id)->where('user_id', $user->id)->exists())->toBeTrue();
});

test('kasus yang selesai tidak dimuat ulang otomatis', function () {
    $case = CaseFile::factory()->create(['status' => 'done']);

    $this->actingAs(User::factory()->create())->get(route('cases.show', $case))
        ->assertOk()
        ->assertDontSee('http-equiv="refresh"', escape: false);
});

test('ID kasus yang tidak ada menghasilkan 404', function () {
    $this->actingAs(User::factory()->create())->get('/kasus/999999')->assertNotFound();
});

test('tombol kasus baru hanya tampil bila boleh mengumpulkan data', function () {
    config(['radar.mode' => 'local']);
    $this->actingAs(User::factory()->create())->get('/kasus')->assertSee('Kasus baru');
    $this->actingAs(User::factory()->demo()->create())->get('/kasus')->assertDontSee('Kasus baru');

    config(['radar.mode' => 'public']);
    $this->actingAs(User::factory()->admin()->create())->get('/kasus')->assertDontSee('Kasus baru');
});

test('teks dari data ditampilkan ter-escape', function () {
    CaseFile::factory()->create(['name' => '<script>alert(1)</script>']);

    $this->actingAs(User::factory()->create())->get('/kasus')
        ->assertDontSee('<script>alert(1)</script>', escape: false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false);
});
