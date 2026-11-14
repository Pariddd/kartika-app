<?php

use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function postText(CaseFile $case, string $label): string
{
    $account = DB::table('accounts')->where('case_id', $case->id)->where('display_label', $label)->value('id');

    return (string) DB::table('posts')->where('account_id', $account)->value('text');
}

test('halaman komentar menampilkan semua post kasus beserta label klasternya', function () {
    $case = CaseFile::factory()->create();
    [$k1] = seedAnalysis($case);
    $total = DB::table('posts')->where('case_id', $case->id)->count();

    $this->actingAs(User::factory()->demo()->create())
        ->get(route('cases.posts', $case))
        ->assertOk()
        ->assertSee('Semua '.$total.' post')
        ->assertSee('Klaster '.$k1->display_label.' · ditandai')
        ->assertSee(e(postText($case, 'Akun-0001')), false);
});

test('filter klaster hanya menampilkan post anggota klaster itu', function () {
    $case = CaseFile::factory()->create();
    [$k1] = seedAnalysis($case);
    $members = DB::table('cluster_members')->where('cluster_id', $k1->id)->pluck('account_id');
    $expected = DB::table('posts')->whereIn('account_id', $members)->count();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('cases.posts', [$case, 'klaster' => $k1->display_label]))
        ->assertOk()
        ->assertSee($expected.' post cocok');

    $outsider = DB::table('accounts')->where('case_id', $case->id)->whereNotIn('id', $members)->value('display_label');
    if ($outsider) {
        $response->assertDontSee('>'.$outsider.'<', false);
    }
});

test('pencarian memperlakukan % dan _ sebagai teks biasa', function () {
    $case = CaseFile::factory()->create();
    seedAnalysis($case);
    DB::table('posts')->where('case_id', $case->id)->orderBy('id')->limit(1)->update(['text' => 'Diskon 100% hari ini']);

    $this->actingAs(User::factory()->create())
        ->get(route('cases.posts', [$case, 'q' => '100%']))
        ->assertSee('1 post cocok');

    $this->actingAs(User::factory()->create())
        ->get(route('cases.posts', [$case, 'q' => '%']))
        ->assertSee('1 post cocok');
});

test('akun demo tidak bisa membaca komentar kasus data nyata', function () {
    $case = CaseFile::factory()->live()->create();
    seedAnalysis($case);

    $this->actingAs(User::factory()->demo()->create())
        ->get(route('cases.posts', $case))
        ->assertForbidden();
});

test('nama asli di daftar komentar hanya saat pseudonim dimatikan, dan tercatat di log audit', function () {
    config(['radar.mode' => 'local', 'radar.show_account_names' => true, 'radar.presentation_mask' => false]);
    $case = CaseFile::factory()->live()->create();
    seedAnalysis($case);
    DB::table('accounts')->where('case_id', $case->id)->update(['display_name' => 'Nama Asli Uji']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('cases.posts', $case))->assertOk()->assertDontSee('Nama Asli Uji');
    expect(AuditLog::where('action', 'posts.names')->count())->toBe(0);

    DB::table('app_settings')->insert(['key' => 'pseudonym_mode', 'value' => 'false', 'updated_at' => now()]);
    $this->actingAs($admin)->get(route('cases.posts', $case))->assertSee('Nama Asli Uji');
    expect(AuditLog::where('action', 'posts.names')->count())->toBe(1);
});

test('nilai filter yang tidak dikenal diabaikan, dan yang terlalu panjang ditolak', function () {
    $case = CaseFile::factory()->create();
    seedAnalysis($case);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('cases.posts', [$case, 'klaster' => 'K-99']))->assertOk()->assertSee('Semua post');
    $this->actingAs($user)->get(route('cases.posts', [$case, 'q' => str_repeat('a', 101)]))->assertSessionHasErrors('q');
});

test('halaman kasus dan klaster menautkan ke daftar komentar', function () {
    $case = CaseFile::factory()->create();
    [$k1] = seedAnalysis($case);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('cases.show', $case))->assertSee(route('cases.posts', $case), false);
    $this->actingAs($user)->get(route('clusters.show', [$case, $k1]))
        ->assertSee(e(route('cases.posts', [$case, 'klaster' => $k1->display_label])), false);
});
