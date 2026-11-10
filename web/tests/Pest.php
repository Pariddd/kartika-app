<?php

use App\Models\CaseFile;
use App\Models\Cluster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Menulis hasil analisis seperti yang ditulis worker Python.
 * Klaster K-01 (akun 1-4) ditandai dan cocok dengan "Tanaman A";
 * K-02 (akun 5-7) ditandai tetapi salah tanda; "Tanaman B" (akun 8-10) terlewat.
 */
function seedAnalysis(CaseFile $case): array
{
    $now = now();
    $accounts = [];
    foreach (range(1, 10) as $i) {
        $accounts[$i] = DB::table('accounts')->insertGetId([
            'case_id' => $case->id, 'platform' => 'synthetic', 'account_key' => str_pad((string) $i, 64, 'a'),
            'display_label' => sprintf('Akun-%04d', $i), 'account_created_at' => $now,
        ]);
    }
    $post = fn (int $account, string $text) => DB::table('posts')->insertGetId([
        'case_id' => $case->id, 'account_id' => $accounts[$account], 'platform' => 'synthetic',
        'post_key' => hash('sha256', $text.$account), 'text' => $text, 'posted_at' => $now, 'urls' => '[]', 'media_phash' => '[]',
    ]);
    $p1 = $post(1, 'Pasar Lama sudah tidak layak, saatnya dipindah.');
    $p2 = $post(2, 'Pasar Lama sudah tidak layak lagi, saatnya dipindah!');

    $job = $case->jobs()->create(['job_type' => 'analyze', 'status' => 'done', 'payload' => ['schema_version' => 1]]);

    $cluster = function (string $label, array $members, bool $flagged, array $active) use ($case, $job, $accounts, $p1, $p2) {
        $id = DB::table('clusters')->insertGetId([
            'case_id' => $case->id, 'job_id' => $job->id, 'display_label' => $label, 'size' => count($members),
            'signals_active' => count(array_filter($active)), 'coordination_score' => 0.81, 'is_flagged' => $flagged,
        ]);
        foreach ($members as $m) {
            DB::table('cluster_members')->insert(['cluster_id' => $id, 'account_id' => $accounts[$m]]);
        }
        $evidence = [
            'text' => ['pair_ratio' => 0.9, 'baseline_ratio' => 0.0004, 'similar_post_pairs' => 12,
                'top_pairs' => [['post_a' => $p1, 'post_b' => $p2, 'method' => 'minhash', 'score' => 0.93]]],
            'time' => ['window_seconds' => 300, 'pair_ratio' => 0.8, 'baseline_ratio' => 0.0001, 'accounts_in_min_events' => 4,
                'events' => [['start' => '2026-11-01T05:00:00Z', 'accounts' => 4]]],
            'profile' => ['available' => false, 'coverage' => 0.0],
            'infra' => ['domains' => [['domain' => 'berita-palsu.example', 'accounts' => 3, 'member_share' => 0.75,
                'other_share' => 0.0, 'distinctive' => true, 'age_days_at_first_post' => null]]],
        ];
        foreach ($evidence as $signal => $data) {
            DB::table('cluster_signals')->insert([
                'cluster_id' => $id, 'signal' => $signal, 'active' => $active[$signal] ?? false,
                'strength' => 0.8, 'evidence' => json_encode($data),
            ]);
        }

        return Cluster::find($id);
    };

    $k1 = $cluster('K-01', [1, 2, 3, 4], true, ['text' => true, 'time' => true, 'infra' => true]);
    $k2 = $cluster('K-02', [5, 6, 7], true, ['text' => true, 'time' => true]);
    $k3 = $cluster('K-03', [8, 9], false, ['text' => true]);

    foreach ([1, 2, 3] as $m) {
        DB::table('demo_ground_truth')->insert(['case_id' => $case->id, 'planted_label' => 'Tanaman A', 'account_id' => $accounts[$m]]);
    }
    foreach ([8, 9, 10] as $m) {
        DB::table('demo_ground_truth')->insert(['case_id' => $case->id, 'planted_label' => 'Tanaman B', 'account_id' => $accounts[$m]]);
    }
    DB::table('issue_scores')->insert([
        'case_id' => $case->id, 'job_id' => $job->id, 'organic_score' => 0.79,
        'total_posts' => 2127, 'coordinated_posts' => 454, 'computed_at' => $now,
    ]);

    return [$k1, $k2, $k3];
}
