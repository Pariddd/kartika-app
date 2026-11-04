<?php

use App\Enums\CaseMode;
use App\Enums\CaseStatus;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Models\AnalysisJob;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\User;

beforeEach(function () {
    config(['radar.mode' => 'local', 'radar.sources.x_enabled' => false]);
});

function validYouTubeCase(array $overrides = []): array
{
    return array_merge([
        'name' => 'Isu pasar gelombang 1',
        'platform' => 'youtube',
        'max_items' => 1000,
        'video_urls' => "https://www.youtube.com/watch?v=dQw4w9WgXcQ\nhttps://youtu.be/9bZkp7q19f0\ndQw4w9WgXcQ",
    ], $overrides);
}

test('analis bisa membuat kasus YouTube dan job collect sesuai kontrak', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/kasus', validYouTubeCase());

    $case = CaseFile::sole();
    $response->assertRedirect(route('cases.show', $case));

    expect($case->mode)->toBe(CaseMode::Live)
        ->and($case->status)->toBe(CaseStatus::Collecting)
        ->and($case->sources)->toBe(['youtube'])
        ->and($case->created_by)->toBe($user->id);

    $job = AnalysisJob::sole();
    expect($job->job_type)->toBe(JobType::Collect)
        ->and($job->status)->toBe(JobStatus::Pending)
        ->and($job->case_id)->toBe($case->id)
        // toEqual, bukan toBe: jsonb di PostgreSQL tidak mempertahankan urutan kunci.
        ->and($job->payload)->toEqual([
            'schema_version' => 1,
            'job_type' => 'collect',
            'case_id' => $case->id,
            'requested_by' => $user->id,
            'params' => [
                'platform' => 'youtube',
                'video_ids' => ['dQw4w9WgXcQ', '9bZkp7q19f0'],
                'max_items' => 1000,
            ],
        ]);

    expect($case->query)->toEqual($job->payload['params']);
    expect(AuditLog::where('action', 'cases.store')->where('case_id', $case->id)->exists())->toBeTrue();
});

test('akun demo tidak bisa membuka form atau membuat kasus', function () {
    $demo = User::factory()->demo()->create();

    $this->actingAs($demo)->get('/kasus/baru')->assertForbidden();
    $this->actingAs($demo)->post('/kasus', validYouTubeCase())->assertForbidden();

    expect(CaseFile::count())->toBe(0);
});

test('mode publik menolak pembuatan kasus, bahkan untuk admin', function () {
    config(['radar.mode' => 'public']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/kasus/baru')->assertForbidden();
    $this->actingAs($admin)->post('/kasus', validYouTubeCase())->assertForbidden();

    expect(CaseFile::count())->toBe(0)->and(AnalysisJob::count())->toBe(0);
});

test('validasi kasus YouTube', function (array $overrides, string $field) {
    $this->actingAs(User::factory()->create())
        ->post('/kasus', validYouTubeCase($overrides))
        ->assertSessionHasErrors($field);

    expect(CaseFile::count())->toBe(0)->and(AnalysisJob::count())->toBe(0);
})->with([
    'nama kosong' => [['name' => ''], 'name'],
    'max_items melebihi batas YouTube' => [['max_items' => 5001], 'max_items'],
    'max_items nol' => [['max_items' => 0], 'max_items'],
    'tanpa video' => [['video_urls' => ''], 'video_urls'],
    'hanya baris kosong' => [['video_urls' => "\n  \n"], 'video_urls'],
    'link bukan YouTube' => [['video_urls' => 'https://evil.example/watch?v=dQw4w9WgXcQ'], 'video_urls'],
    'lebih dari 10 video' => [['video_urls' => implode("\n", array_map(fn ($i) => 'https://youtu.be/'.str_pad((string) $i, 11, 'a'), range(1, 11)))], 'video_urls'],
    'platform tidak dikenal' => [['platform' => 'telegram'], 'platform'],
]);

test('X ditolak selama belum diaktifkan', function () {
    $this->actingAs(User::factory()->create())
        ->post('/kasus', ['name' => 'Tagar', 'platform' => 'x', 'query' => '#ContohTagar', 'max_items' => 100])
        ->assertSessionHasErrors('platform');
});

test('X yang sudah diaktifkan menghasilkan payload sesuai kontrak', function () {
    config(['radar.sources.x_enabled' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)->post('/kasus', [
        'name' => 'Tagar',
        'platform' => 'x',
        'query' => '  #ContohTagar ',
        'max_items' => 500,
        'since' => '2026-11-01',
        'until' => '2026-11-02',
    ])->assertSessionHasNoErrors();

    expect(AnalysisJob::sole()->payload['params'])->toEqual([
        'platform' => 'x',
        'query' => '#ContohTagar',
        'max_items' => 500,
        'since' => '2026-11-01',
        'until' => '2026-11-02',
    ]);
});

test('validasi kasus X', function (array $input, string $field) {
    config(['radar.sources.x_enabled' => true]);

    $this->actingAs(User::factory()->create())
        ->post('/kasus', array_merge(['name' => 'Tagar', 'platform' => 'x', 'query' => '#Tagar', 'max_items' => 100], $input))
        ->assertSessionHasErrors($field);
})->with([
    'max_items melebihi batas X' => [['max_items' => 1001], 'max_items'],
    'tanpa kata kunci' => [['query' => ''], 'query'],
    'kata kunci terlalu panjang' => [['query' => str_repeat('a', 201)], 'query'],
    'tanggal akhir sebelum mulai' => [['since' => '2026-11-02', 'until' => '2026-11-01'], 'until'],
    'format tanggal salah' => [['since' => '02/11/2026'], 'since'],
]);
