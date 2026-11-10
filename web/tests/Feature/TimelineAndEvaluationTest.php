<?php

use App\Models\CaseFile;
use App\Models\User;
use App\Support\CaseTimeline;
use Illuminate\Support\Facades\DB;

function timelineCase(): CaseFile
{
    $case = CaseFile::factory()->create();
    seedAnalysis($case); // akun 1-4 anggota klaster ditandai K-01, 5-7 K-02
    DB::table('posts')->where('case_id', $case->id)->delete();
    $accounts = DB::table('accounts')->where('case_id', $case->id)->orderBy('id')->pluck('id')->values();

    $post = fn (int $account, string $at) => DB::table('posts')->insert([
        'case_id' => $case->id, 'account_id' => $accounts[$account - 1], 'platform' => 'synthetic',
        'post_key' => hash('sha256', $account.$at), 'text' => 'x', 'posted_at' => $at, 'urls' => '[]', 'media_phash' => '[]',
    ]);
    $post(1, '2026-11-01 01:05:00+00');   // 08.05 WIB, klaster ditandai
    $post(2, '2026-11-01 01:20:00+00');   // 08.20 WIB, klaster ditandai
    $post(10, '2026-11-01 01:40:00+00');  // 08.40 WIB, akun lain
    $post(9, '2026-11-01 05:10:00+00');   // 12.10 WIB, klaster tidak ditandai

    return $case;
}

test('lini waktu mengelompokkan post per jam WIB dan memisahkan post klaster ditandai', function () {
    $timeline = CaseTimeline::build(timelineCase());

    expect($timeline['bucket_seconds'])->toBe(600);
    $byLabel = collect($timeline['buckets'])->keyBy(fn ($b) => $b['from']->format('H.i'));
    expect($byLabel['08.00']['total'])->toBe(1)->and($byLabel['08.00']['coordinated'])->toBe(1)
        ->and($byLabel['08.20']['coordinated'])->toBe(1)
        ->and($byLabel['08.40']['coordinated'])->toBe(0)->and($byLabel['08.40']['total'])->toBe(1)
        ->and($byLabel['12.10']['coordinated'])->toBe(0)
        ->and(collect($timeline['buckets'])->sum('total'))->toBe(4)
        ->and($timeline['has_coordinated'])->toBeTrue();
});

test('ukuran rentang menyesuaikan lama percakapan dan batang tidak lebih dari 48', function (int $span, int $expected) {
    expect(CaseTimeline::bucketSize($span))->toBe($expected);
})->with([
    'dua jam' => [7200, 600],
    'satu hari' => [86400, 3600],
    'dua hari' => [2 * 86400, 10800],
    'sebulan' => [30 * 86400, 86400],
    'setahun' => [365 * 86400, 8 * 86400],
]);

test('kasus tanpa post tidak punya lini waktu dan halamannya tetap terbuka', function () {
    $case = CaseFile::factory()->create();
    expect(CaseTimeline::build($case))->toBeNull();

    $this->actingAs(User::factory()->demo()->create())->get(route('cases.show', $case))->assertOk()->assertDontSee('Lini waktu percakapan');
});

test('halaman kasus menampilkan lini waktu dengan tabel pendamping', function () {
    $this->actingAs(User::factory()->demo()->create())
        ->get(route('cases.show', timelineCase()))
        ->assertOk()
        ->assertSee('Lini waktu percakapan')
        ->assertSee('Post dari klaster ditandai')
        ->assertSee('Lihat sebagai tabel');
});

test('halaman evaluasi terbuka untuk semua peran dan menyatakan datanya sintetis', function () {
    $this->actingAs(User::factory()->demo()->create())
        ->get(route('evaluation.show'))
        ->assertOk()
        ->assertSee('Semua data sintetis')
        ->assertSee('Uji ketahanan')
        ->assertSee('Keterbatasan evaluasi')
        ->assertDontSee('akurasi 9');
});

test('berkas hasil evaluasi konsisten dengan yang ditampilkan', function () {
    $report = json_decode(file_get_contents(resource_path('evaluation/hasil-evaluasi.json')), true);

    expect($report['schema_version'])->toBe(1)
        ->and($report['sweeps'])->toHaveCount(4)
        ->and(array_intersect($report['calibration_seeds'], $report['test_seeds']))->toBe([]);
    foreach ($report['sweeps'] as $sweep) {
        foreach ($sweep['points'] as $point) {
            expect($point['rate'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
        }
    }
});

test('tamu tidak bisa membuka halaman evaluasi', function () {
    $this->get(route('evaluation.show'))->assertRedirect(route('login'));
});
