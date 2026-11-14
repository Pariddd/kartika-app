<?php

use App\Models\CaseFile;
use App\Models\User;
use App\Support\ContentSource;
use Illuminate\Support\Facades\DB;

test('tautan sumber dibentuk ulang dari ID saja, dan bentuk lain ditolak', function () {
    expect(ContentSource::url('youtube:dQw4w9WgXcQ'))->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->and(ContentSource::url('x:1850000000000000000'))->toBe('https://x.com/i/status/1850000000000000000')
        ->and(ContentSource::url('tiktok:7332000000000000001'))->toBe('https://www.tiktok.com/@/video/7332000000000000001')
        ->and(ContentSource::url('instagram:C1abcDEF_-'))->toBe('https://www.instagram.com/p/C1abcDEF_-/')
        ->and(ContentSource::label('tiktok:7332000000000000001'))->toBe('Video TikTok …0000001');

    foreach (['javascript:alert(1)', 'https://evil.example', 'youtube:a b', 'facebook:1', 'x:', null] as $bad) {
        expect(ContentSource::url($bad))->toBeNull();
    }
});

function seedSources(CaseFile $case): array
{
    [$k1, $k2] = seedAnalysis($case);
    $members = DB::table('cluster_members')->where('cluster_id', $k1->id)->pluck('account_id');
    DB::table('posts')->where('case_id', $case->id)->whereIn('account_id', $members)->update(['source_ref' => 'tiktok:7001']);
    DB::table('posts')->where('case_id', $case->id)->whereNotIn('account_id', $members)->update(['source_ref' => 'tiktok:7002']);
    DB::table('posts')->where('case_id', $case->id)->orderBy('id')->limit(1)->update(['source_ref' => 'tiktok:7002']);

    return [$k1, $k2];
}

test('halaman komentar menampilkan sumber tiap komentar, ringkasan per konten, dan filter sumber', function () {
    $case = CaseFile::factory()->create();
    [$k1] = seedSources($case);
    $user = User::factory()->create();
    $onFirst = DB::table('posts')->where('case_id', $case->id)->where('source_ref', 'tiktok:7001')->count();

    $this->actingAs($user)->get(route('cases.posts', $case))
        ->assertOk()
        ->assertSee('Konten sumber (2)')
        ->assertSee('https://www.tiktok.com/@/video/7001', false)
        ->assertSee('rel="noopener noreferrer nofollow"', false);

    $this->actingAs($user)->get(route('cases.posts', [$case, 'sumber' => 'tiktok:7001']))
        ->assertSee($onFirst.' post cocok');

    // Nilai sumber yang tidak ada di kasus ini diabaikan, bukan dipakai mentah di query.
    $this->actingAs($user)->get(route('cases.posts', [$case, 'sumber' => 'tiktok:9999']))
        ->assertSee(DB::table('posts')->where('case_id', $case->id)->count().' post cocok');
});

test('halaman klaster menyebut di berapa konten sumber anggotanya berkomentar', function () {
    $case = CaseFile::factory()->create();
    [$k1] = seedSources($case);

    $this->actingAs(User::factory()->create())
        ->get(route('clusters.show', [$case, $k1]))
        ->assertOk()
        ->assertSee('konten sumber</strong>', false)
        ->assertSee('Video TikTok 7001');
});

test('kasus tanpa data sumber tidak menampilkan bagian sumber', function () {
    $case = CaseFile::factory()->create();
    seedAnalysis($case);

    $this->actingAs(User::factory()->create())->get(route('cases.posts', $case))
        ->assertOk()
        ->assertDontSee('Konten sumber');
});
