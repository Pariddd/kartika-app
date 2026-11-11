<?php

use App\Models\CaseFile;
use App\Models\User;
use App\Support\CaseTimeline;
use Illuminate\Support\Facades\DB;

test('lonjakan ditandai hanya bila jauh di atas nilai tengah', function () {
    $datar = [10, 11, 9, 10, 12, 10, 11, 9, 10, 11];
    expect(CaseTimeline::spikes($datar)['indexes'])->toBe([]);

    $lonjak = [10, 11, 9, 10, 80, 10, 11, 9, 10, 11];
    $hasil = CaseTimeline::spikes($lonjak);
    expect(array_keys($hasil['indexes']))->toBe([4])
        ->and($hasil['median'])->toBe(10.0);
});

test('deret pendek atau sepi tidak pernah menghasilkan lonjakan', function () {
    expect(CaseTimeline::spikes([1, 50, 1])['indexes'])->toBe([])
        ->and(CaseTimeline::spikes([0, 0, 0, 1, 0, 0, 4, 0, 0, 0])['indexes'])->toBe([]);
});

test('lini waktu kasus menampilkan lonjakan beserta porsi klaster ditandai', function () {
    $case = CaseFile::factory()->create();
    seedAnalysis($case);
    $account = DB::table('accounts')->where('case_id', $case->id)->where('display_label', 'Akun-0001')->value('id');
    $start = now()->subDay()->startOfHour();
    $rows = [];
    foreach (range(0, 11) as $hour) {
        foreach (range(1, $hour === 6 ? 40 : 2) as $n) {
            $rows[] = [
                'case_id' => $case->id, 'account_id' => $account, 'platform' => 'synthetic',
                'post_key' => hash('sha256', "spike-$hour-$n"), 'text' => 'uji', 'urls' => '[]', 'media_phash' => '[]',
                'posted_at' => $start->copy()->addHours($hour)->addSeconds($n),
            ];
        }
    }
    DB::table('posts')->where('case_id', $case->id)->delete();
    DB::table('posts')->insert($rows);

    $timeline = CaseTimeline::build($case);
    expect($timeline['spike_count'])->toBe(1)
        ->and($timeline['spikes'][0]['total'])->toBe(40)
        ->and($timeline['spikes'][0]['coordinated_share'])->toEqual(1);

    $this->actingAs(User::factory()->create())
        ->get(route('cases.show', $case))
        ->assertOk()
        ->assertSee('1 lonjakan terdeteksi')
        ->assertSee('100% dari klaster ditandai');
});

test('halaman kasus menampilkan ringkasan: jumlah kasus, klaster menunggu penilaian, koordinasi tertinggi', function () {
    $case = CaseFile::factory()->create(['name' => 'Kasus Ringkasan']);
    seedAnalysis($case);

    $this->actingAs(User::factory()->create())
        ->get(route('cases.index'))
        ->assertOk()
        ->assertSee('Menunggu penilaian analis')
        ->assertSee('Koordinasi tertinggi')
        ->assertSee('21% post dari klaster ditandai')
        ->assertDontSee('Mulai di sini');
});

test('ringkasan untuk akun demo tidak menghitung kasus data nyata', function () {
    seedAnalysis(CaseFile::factory()->live()->create());

    expect(App\Support\CaseOverview::for(User::factory()->demo()->create()))
        ->cases->toBe(0)->flagged->toBe(0)->top->toBeNull();
    expect(App\Support\CaseOverview::for(User::factory()->create()))
        ->cases->toBe(1)->flagged->toBe(2)->pending->toBe(2);
});

test('akun demo melihat panduan Mulai di sini yang menunjuk kasus sintetis', function () {
    $case = CaseFile::factory()->create();
    [$k1] = seedAnalysis($case);

    $this->actingAs(User::factory()->demo()->create())
        ->get(route('cases.index'))
        ->assertOk()
        ->assertSee('Mulai di sini')
        ->assertSee(route('clusters.show', [$case, $k1]), false)
        ->assertSee(route('cases.answer', $case), false);
});
