<?php

use App\Models\CaseFile;
use App\Models\ClusterSignal;
use App\Models\User;
use App\Support\ContentMask;
use Illuminate\Support\Facades\DB;

/**
 * Menambahkan narasi utama ke bukti teks klaster pertama, merujuk post pertama kasus.
 */
function addNarrative(CaseFile $case, $cluster, string $text): int
{
    $postId = DB::table('posts')->where('case_id', $case->id)->orderBy('id')->value('id');
    DB::table('posts')->where('id', $postId)->update(['text' => $text]);

    $signal = ClusterSignal::where('cluster_id', $cluster->id)->where('signal', 'text')->firstOrFail();
    $signal->evidence = array_merge($signal->evidence, ['narratives' => [['post' => $postId, 'posts' => 9, 'accounts' => 3, 'share' => 0.75]]]);
    $signal->save();

    return $postId;
}

test('halaman klaster menampilkan narasi utama dari post asli beserta jumlah akunnya', function () {
    $case = CaseFile::factory()->create();
    [$k1] = seedAnalysis($case);
    addNarrative($case, $k1, 'Pasar Lama harus segera dipindah ke lokasi baru.');

    $this->actingAs(User::factory()->demo()->create())
        ->get(route('clusters.show', [$case, $k1]))
        ->assertOk()
        ->assertSeeInOrder(['Narasi utama', 'Pasar Lama harus segera dipindah ke lokasi baru.', '3 dari', '75%', '9 post']);
});

test('klaster tanpa narasi tidak menampilkan bagian narasi utama', function () {
    $case = CaseFile::factory()->create();
    [$k1] = seedAnalysis($case);

    $this->actingAs(User::factory()->demo()->create())
        ->get(route('clusters.show', [$case, $k1]))
        ->assertOk()
        ->assertDontSee('Narasi utama');
});

test('narasi utama mengikuti penyamaran konten di mode publik', function () {
    config(['radar.mode' => 'public']);
    $case = CaseFile::factory()->create();
    [$k1] = seedAnalysis($case);
    addNarrative($case, $k1, 'Pengelola pasar itu goblok semua.');
    $user = User::factory()->demo()->create();

    $this->actingAs($user)->get(route('clusters.show', [$case, $k1]))
        ->assertSee('Narasi utama')->assertSee(ContentMask::PLACEHOLDER)->assertDontSee('goblok');
    $this->actingAs($user)->get(route('cases.report', $case))
        ->assertSee(ContentMask::PLACEHOLDER)->assertDontSee('goblok');
});

test('ringkasan BLUF memuat narasi utama tiap klaster ditandai', function () {
    $case = CaseFile::factory()->create();
    [$k1] = seedAnalysis($case);
    addNarrative($case, $k1, 'Pasar Lama harus segera dipindah ke lokasi baru.');

    $this->actingAs(User::factory()->demo()->create())
        ->get(route('cases.report', $case))
        ->assertOk()
        ->assertSee('Narasi utama')
        ->assertSee('ditulis dalam bentuk mirip oleh 3 dari')
        ->assertSee('Pasar Lama harus segera dipindah ke lokasi baru.');
});

test('mode presentasi menyembunyikan narasi kasus data nyata di halaman klaster dan ringkasan', function () {
    config(['radar.mode' => 'local', 'radar.presentation_mask' => true]);
    $case = CaseFile::factory()->live()->create();
    [$k1] = seedAnalysis($case);
    addNarrative($case, $k1, 'Kalimat komentar asli dari YouTube.');
    $analis = User::factory()->create();

    $this->actingAs($analis)->get(route('clusters.show', [$case, $k1]))->assertOk()->assertDontSee('Kalimat komentar asli');
    $this->actingAs($analis)->get(route('cases.report', $case))->assertOk()->assertDontSee('Kalimat komentar asli');
});
