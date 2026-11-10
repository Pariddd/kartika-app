<?php

use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Cluster;
use App\Models\ClusterReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

test('halaman kasus menampilkan kesimpulan dan klaster ditandai lebih dulu', function () {
    $case = CaseFile::factory()->create();
    seedAnalysis($case);

    $this->actingAs(User::factory()->demo()->create())
        ->get(route('cases.show', $case))
        ->assertOk()
        ->assertSee('21% percakapan')
        ->assertSee('berasal dari 2 klaster yang ditandai terkoordinasi')
        ->assertSeeInOrder(['K-01', 'K-02', 'Klaster tidak ditandai', 'K-03'])
        ->assertSee('Tampilkan jawaban')
        ->assertDontSee('Recall');
});

test('kunci jawaban menghitung recall dan precision sesuai kontrak', function () {
    $case = CaseFile::factory()->create();
    seedAnalysis($case);

    $this->actingAs(User::factory()->demo()->create())
        ->get(route('cases.answer', $case))
        ->assertOk()
        ->assertSee('Recall')
        ->assertSeeInOrder(['K-01', 'Tanaman A', '0,75', 'Tertangkap'])
        ->assertSeeInOrder(['K-02', 'Tidak ada', 'Salah tanda'])
        ->assertSeeInOrder(['Tanaman B', 'Terlewat'])
        ->assertSee('50%');

    expect(AuditLog::where('action', 'cases.answer')->where('case_id', $case->id)->exists())->toBeTrue();
});

test('kunci jawaban tidak ada untuk kasus data nyata', function () {
    $case = CaseFile::factory()->live()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('cases.answer', $case))
        ->assertNotFound();
});

test('halaman klaster menampilkan empat sinyal, bukti teks, dan anggota', function () {
    $case = CaseFile::factory()->create();
    [$k1] = seedAnalysis($case);

    $this->actingAs(User::factory()->demo()->create())
        ->get(route('clusters.show', [$case, $k1]))
        ->assertOk()
        ->assertSee('Ditandai · 3 dari 4 sinyal aktif')
        ->assertSeeInOrder(['Kemiripan teks', 'Ko-aktivitas waktu', 'Profil akun', 'Infrastruktur'])
        ->assertSee('90% pasangan akun di klaster ini saling menulis teks mirip; di seluruh kasus hanya 0,04%.')
        ->assertSee('Tidak tersedia: sumber data tidak menyediakan tanggal pembuatan akun.')
        ->assertSee('Pasar Lama sudah tidak layak, saatnya dipindah.')
        ->assertSee('berita-palsu.example')
        ->assertSee('Akun-0004');

    expect(AuditLog::where('action', 'clusters.show')->where('case_id', $case->id)->exists())->toBeTrue();
});

test('mode presentasi menyamarkan teks dan domain kasus data nyata', function () {
    config(['radar.presentation_mask' => true]);
    $case = CaseFile::factory()->live()->create();
    [$k1] = seedAnalysis($case);

    $this->actingAs(User::factory()->create())
        ->get(route('clusters.show', [$case, $k1]))
        ->assertOk()
        ->assertSee('[teks disamarkan · mode presentasi]')
        ->assertSee('[domain disamarkan]')
        ->assertDontSee('Pasar Lama sudah tidak layak')
        ->assertDontSee('berita-palsu.example')
        ->assertSee('90% pasangan akun');
});

test('mode presentasi tidak menyamarkan data sintetis', function () {
    config(['radar.presentation_mask' => true]);
    $case = CaseFile::factory()->create();
    [$k1] = seedAnalysis($case);

    $this->actingAs(User::factory()->create())
        ->get(route('clusters.show', [$case, $k1]))
        ->assertSee('Pasar Lama sudah tidak layak, saatnya dipindah.');
});

test('klaster milik kasus lain menghasilkan 404', function () {
    $case = CaseFile::factory()->create();
    [$k1] = seedAnalysis($case);
    $other = CaseFile::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('clusters.show', [$other, $k1]))
        ->assertNotFound();
});

test('akun demo tidak bisa membuka klaster kasus data nyata', function () {
    $case = CaseFile::factory()->live()->create();
    [$k1] = seedAnalysis($case);

    $this->actingAs(User::factory()->demo()->create())
        ->get(route('clusters.show', [$case, $k1]))
        ->assertForbidden();

    $this->actingAs(User::factory()->demo()->create())
        ->post(route('clusters.review', [$case, $k1]), ['verdict' => 'keliru'])
        ->assertForbidden();
});

test('penilaian analis tersimpan, tercatat di audit, dan tampil di daftar klaster', function () {
    $case = CaseFile::factory()->create();
    [, $k2] = seedAnalysis($case);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('clusters.review', [$case, $k2]), ['verdict' => 'keliru', 'note' => 'pengumuman resmi pedagang'])
        ->assertRedirect(route('clusters.show', [$case, $k2]));

    $review = ClusterReview::sole();
    expect($review->user_id)->toBe($user->id)->and($review->verdict->value)->toBe('keliru');
    expect(AuditLog::where('action', 'clusters.review')->where('case_id', $case->id)->exists())->toBeTrue();

    $this->actingAs($user)->get(route('cases.show', $case))
        ->assertSee('Analis menilai 1 di antaranya keliru.')
        ->assertSeeInOrder(['K-02', 'Keliru']);
});

test('penilaian divalidasi', function (array $input, string $field) {
    $case = CaseFile::factory()->create();
    [$k1] = seedAnalysis($case);

    $this->actingAs(User::factory()->create())
        ->post(route('clusters.review', [$case, $k1]), $input)
        ->assertSessionHasErrors($field);

    expect(ClusterReview::count())->toBe(0);
})->with([
    'tanpa penilaian' => [[], 'verdict'],
    'penilaian asing' => [['verdict' => 'bot'], 'verdict'],
    'catatan terlalu panjang' => [['verdict' => 'keliru', 'note' => str_repeat('a', 1001)], 'note'],
]);

test('teks post dari data ditampilkan ter-escape', function () {
    $case = CaseFile::factory()->create();
    [$k1] = seedAnalysis($case);
    DB::table('posts')->where('case_id', $case->id)->limit(1)->update(['text' => '<img src=x onerror=alert(1)>']);

    $this->actingAs(User::factory()->create())
        ->get(route('clusters.show', [$case, $k1]))
        ->assertDontSee('<img src=x onerror=alert(1)>', escape: false);
});
