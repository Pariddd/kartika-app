<?php

use App\Models\AnalysisJob;
use App\Models\CaseFile;
use App\Models\User;
use App\Support\CaseNetwork;
use Illuminate\Support\Facades\DB;

function caseWithGraph(): CaseFile
{
    $case = CaseFile::factory()->create();
    seedAnalysis($case);
    $accounts = DB::table('accounts')->where('case_id', $case->id)->orderBy('id')->pluck('id')->values();
    $job = AnalysisJob::where('case_id', $case->id)->first()
        ?? $case->jobs()->create(['job_type' => 'analyze', 'status' => 'done', 'payload' => ['schema_version' => 1]]);

    DB::table('case_graphs')->insert([
        'case_id' => $case->id,
        'job_id' => $job->id,
        'computed_at' => now(),
        'graph' => json_encode([
            'nodes' => [
                ['id' => $accounts[0], 'x' => 100, 'y' => 100, 'cluster' => 'K-01', 'flagged' => true, 'posts' => 6, 'links' => 1],
                ['id' => $accounts[1], 'x' => 200, 'y' => 150, 'cluster' => 'K-01', 'flagged' => true, 'posts' => 5, 'links' => 2],
                ['id' => $accounts[9], 'x' => 800, 'y' => 700, 'cluster' => null, 'flagged' => false, 'posts' => 1, 'links' => 1],
            ],
            'edges' => [
                ['a' => $accounts[0], 'b' => $accounts[1], 'text' => true, 'time' => true, 'w' => 2.0],
                ['a' => $accounts[1], 'b' => $accounts[9], 'text' => false, 'time' => true, 'w' => 0.5],
            ],
            'total_nodes' => 3, 'total_edges' => 2, 'truncated' => false,
        ]),
    ]);

    return $case;
}

test('data graf untuk browser memakai label akun, bukan kunci atau nama', function () {
    $case = caseWithGraph();
    DB::table('accounts')->where('case_id', $case->id)->update(['display_name' => 'Nama Rahasia']);

    $network = CaseNetwork::build($case);
    $json = json_encode($network);

    expect($network['shown_nodes'])->toBe(3)
        ->and($network['flagged_nodes'])->toBe(2)
        ->and($network['data']['nodes'][0]['label'])->toBe('Akun-0001')
        ->and($network['data']['clusterUrls']['K-01'])->toContain('/klaster/')
        ->and($json)->not->toContain('Nama Rahasia')
        ->and($json)->not->toContain('account_key');
});

test('halaman kasus menampilkan graf koordinasi dengan tabel pendamping', function () {
    $this->actingAs(User::factory()->demo()->create())
        ->get(route('cases.show', caseWithGraph()))
        ->assertOk()
        ->assertSee('Graf koordinasi')
        ->assertSee('data-graph=', false)
        ->assertSee('Akun-0001')
        ->assertSee('bukan bukti bahwa akun saling mengenal');
});

test('kasus tanpa data graf tidak menampilkan bagian graf', function () {
    $case = CaseFile::factory()->create();
    seedAnalysis($case);

    expect(CaseNetwork::build($case))->toBeNull();
    $this->actingAs(User::factory()->demo()->create())->get(route('cases.show', $case))->assertOk()->assertDontSee('Graf koordinasi');
});

test('data graf ikut terhapus bersama kasusnya', function () {
    $case = caseWithGraph();
    $this->actingAs(User::factory()->admin()->create())->delete(route('cases.destroy', $case), ['confirm' => '1']);

    expect(DB::table('case_graphs')->count())->toBe(0);
});
