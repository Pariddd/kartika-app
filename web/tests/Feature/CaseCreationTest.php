<?php

use App\Enums\CaseMode;
use App\Enums\CaseStatus;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Models\AnalysisJob;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\User;
use App\Support\UploadLimit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['radar.mode' => 'local', 'radar.sources.import_enabled' => false]);
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
    'max_items melebihi batas' => [['max_items' => 20001], 'max_items'],
    'max_items nol' => [['max_items' => 0], 'max_items'],
    'tanpa video' => [['video_urls' => ''], 'video_urls'],
    'hanya baris kosong' => [['video_urls' => "\n  \n"], 'video_urls'],
    'link bukan YouTube' => [['video_urls' => 'https://evil.example/watch?v=dQw4w9WgXcQ'], 'video_urls'],
    'lebih dari 10 video' => [['video_urls' => implode("\n", array_map(fn ($i) => 'https://youtu.be/'.str_pad((string) $i, 11, 'a'), range(1, 11)))], 'video_urls'],
    'platform tidak dikenal' => [['platform' => 'telegram'], 'platform'],
]);

function apifyExport(?array $items = null): UploadedFile
{
    $items ??= [
        ['type' => 'tweet', 'noResults' => true],
        ['type' => 'tweet', 'id' => '2109961287283384573', 'text' => 'isi', 'createdAt' => 'Tue Oct 13 10:55:46 +0000 2026', 'author' => ['id' => '1', 'userName' => 'fiktif']],
    ];

    return UploadedFile::fake()->createWithContent('dataset_tweet-scraper.json', json_encode($items));
}

test('X ditolak selama belum diaktifkan', function () {
    $this->actingAs(User::factory()->create())
        ->post('/kasus', ['name' => 'Tagar', 'platform' => 'x', 'import_file' => apifyExport(), 'max_items' => 100])
        ->assertSessionHasErrors('platform');
});

test('impor X menyimpan file dengan nama buatan server dan payload sesuai kontrak', function () {
    config(['radar.sources.import_enabled' => true]);
    Storage::fake('local');

    $this->actingAs(User::factory()->create())->post('/kasus', [
        'name' => 'Tagar',
        'platform' => 'x',
        'import_file' => apifyExport(),
        'query' => '  #ContohTagar ',
        'max_items' => 500,
    ])->assertSessionHasNoErrors();

    $params = AnalysisJob::sole()->payload['params'];
    expect($params)->toHaveKeys(['platform', 'file_ref', 'max_items', 'query'])
        ->and($params['platform'])->toBe('x')
        ->and($params['query'])->toBe('#ContohTagar')
        ->and($params['max_items'])->toBe(500)
        ->and($params['file_ref'])->toMatch('#^uploads/[0-9a-f-]{36}\.json$#');
    Storage::disk('local')->assertExists($params['file_ref']);
});

test('validasi impor X', function (array $input, string $field) {
    config(['radar.sources.import_enabled' => true]);
    Storage::fake('local');

    $this->actingAs(User::factory()->create())
        ->post('/kasus', array_merge(['name' => 'Tagar', 'platform' => 'x', 'import_file' => apifyExport(), 'max_items' => 100], $input))
        ->assertSessionHasErrors($field);

    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'tanpa file' => [['import_file' => null], 'import_file'],
    'bukan JSON' => [['import_file' => UploadedFile::fake()->createWithContent('data.csv', "akun,teks\n1,a")], 'import_file'],
    'bukan JSON' => [['import_file' => UploadedFile::fake()->createWithContent('data.json', 'id,teks,waktu')], 'import_file'],
    'max_items melebihi batas' => [['max_items' => 20001], 'max_items'],
    'catatan terlalu panjang' => [['query' => str_repeat('a', 201)], 'query'],
]);

test('impor Instagram dan TikTok memakai alur yang sama dengan X', function (string $platform) {
    config(['radar.sources.import_enabled' => true]);
    Storage::fake('local');

    $this->actingAs(User::factory()->create())->post('/kasus', [
        'name' => 'Komentar promo',
        'platform' => $platform,
        'import_file' => UploadedFile::fake()->createWithContent('dataset.json', '[{"id":"1","text":"a","timestamp":"2026-11-01T05:00:00Z","ownerUsername":"x"}]'),
    ])->assertSessionHasNoErrors();

    $job = AnalysisJob::sole();
    expect($job->payload['params']['platform'])->toBe($platform)
        ->and($job->payload['params']['file_ref'])->toMatch('#^uploads/[0-9a-f-]{36}\.json$#')
        ->and($job->case->sources)->toBe([$platform]);
})->with(['instagram', 'tiktok']);

test('jumlah maksimal post yang dikosongkan berarti batas atas per kasus', function () {
    $this->actingAs(User::factory()->create())->post('/kasus', [
        'name' => 'Tanpa batas',
        'platform' => 'youtube',
        'video_urls' => 'https://youtu.be/dQw4w9WgXcQ',
        'max_items' => '',
    ])->assertSessionHasNoErrors();

    expect(AnalysisJob::sole()->payload['params']['max_items'])->toBe(20000);
});

test('form hanya menawarkan impor Apify bila diaktifkan', function () {
    $analis = User::factory()->create();

    config(['radar.sources.import_enabled' => false]);
    $this->actingAs($analis)->get('/kasus/baru')->assertOk()
        ->assertSee('Belum diaktifkan (RADAR_APIFY_ENABLED)')
        ->assertDontSee('File JSON hasil ekspor');

    config(['radar.sources.import_enabled' => true]);
    $this->actingAs($analis)->get('/kasus/baru')->assertOk()
        ->assertSee('Instagram')->assertSee('TikTok')
        ->assertSee('File JSON hasil ekspor')
        ->assertSee('data-source="x instagram tiktok"', false);
});

test('batas unggahan mengikuti nilai terkecil antara konfigurasi dan PHP', function () {
    expect(UploadLimit::bytes('8M'))->toBe(8 * 1024 * 1024)
        ->and(UploadLimit::bytes('512K'))->toBe(512 * 1024)
        ->and(UploadLimit::bytes('1G'))->toBe(1024 ** 3)
        ->and(UploadLimit::bytes('0'))->toBe(0)
        ->and(UploadLimit::bytes(''))->toBe(0);

    config(['radar.collect.import_file_max_kb' => 1]);
    expect(UploadLimit::importKilobytes())->toBe(1)->and(UploadLimit::label())->toBe('1 KB');
});

test('file JSON Lines dan objek pembungkus dari Actor lain diterima; kolomnya dikenali worker', function () {
    Storage::fake('local');
    config(['radar.mode' => 'local', 'radar.sources.import_enabled' => true]);
    $user = User::factory()->create();

    foreach ([
        UploadedFile::fake()->createWithContent('dataset.jsonl', '{"commentBody":"a","publishedOn":"2026-11-01T05:00:00Z","commenter":{"handle":"x"}}'."\n"),
        UploadedFile::fake()->createWithContent('dataset.json', '{"items":[{"commentBody":"a"}]}'),
    ] as $file) {
        $this->actingAs($user)
            ->post(route('cases.store'), ['name' => 'Actor lain', 'platform' => 'tiktok', 'import_file' => $file])
            ->assertSessionHasNoErrors();
    }
});
