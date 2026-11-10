<?php

use App\Models\CaseFile;
use App\Models\User;
use App\Support\ContentMask;
use Illuminate\Support\Facades\DB;

test('kata dari daftar dikenali dalam berbagai bentuk penulisan', function (string $text) {
    expect(ContentMask::contains($text))->toBeTrue();
})->with([
    'huruf kecil' => 'dasar bangsat semua',
    'huruf besar' => 'BANGSAT!',
    'huruf berulang' => 'anjiiiing lu',
    'angka mirip huruf' => 'g0bl0k banget',
    'akhiran -nya' => 'kampretnya datang lagi',
    'tanda baca menempel' => '(tolol).',
    'hinaan kelompok' => 'dasar kadrun',
]);

test('teks netral dan kata yang hanya memuat potongan kata kasar tidak disamarkan', function (string $text) {
    expect(ContentMask::contains($text))->toBeFalse();
})->with([
    'kalimat biasa' => 'Pasar Lama sudah tidak layak, saatnya dipindah.',
    'potongan di dalam kata' => 'Babinsa dan bhabinkamtibmas hadir',
    'pengecualian disengaja' => 'harga daging babi naik',
    'kosong' => '',
]);

test('penyamaran aktif di mode publik dan nonaktif di mode lokal tanpa mode presentasi', function () {
    config(['radar.mode' => 'public', 'radar.presentation_mask' => false]);
    expect(ContentMask::display('dasar bangsat'))->toBe(ContentMask::PLACEHOLDER);

    config(['radar.mode' => 'local']);
    expect(ContentMask::display('dasar bangsat'))->toBe('dasar bangsat');

    config(['radar.presentation_mask' => true]);
    expect(ContentMask::display('dasar bangsat'))->toBe(ContentMask::PLACEHOLDER);
});

test('halaman klaster di mode publik menyamarkan post bermasalah dan tetap menampilkan skornya', function () {
    config(['radar.mode' => 'public']);
    $case = CaseFile::factory()->create();
    [$k1] = seedAnalysis($case);
    DB::table('posts')->where('case_id', $case->id)->where('text', 'like', '%lagi%')->update(['text' => 'Pasar Lama dikelola orang tolol, pindahkan!']);

    $this->actingAs(User::factory()->demo()->create())
        ->get(route('clusters.show', [$case, $k1]))
        ->assertOk()
        ->assertSee('Pasar Lama sudah tidak layak, saatnya dipindah.')
        ->assertSee(ContentMask::PLACEHOLDER)
        ->assertDontSee('orang tolol')
        ->assertSee('0,93');
});

test('nama kasus unggahan yang memuat kata kasar disamarkan di daftar dan halaman kasus', function () {
    config(['radar.mode' => 'public']);
    $case = CaseFile::factory()->create(['name' => 'Uji goblok']);
    $user = User::factory()->demo()->create();

    $this->actingAs($user)->get(route('cases.index'))->assertOk()->assertDontSee('Uji goblok')->assertSee(ContentMask::PLACEHOLDER);
    $this->actingAs($user)->get(route('cases.show', $case))->assertOk()->assertDontSee('Uji goblok');
});

test('banner mode publik menjelaskan penyamaran apa adanya', function () {
    config(['radar.mode' => 'public']);

    $this->actingAs(User::factory()->demo()->create())
        ->get(route('cases.index'))
        ->assertSee('dari daftar penyamaran tampil sebagai [konten disamarkan]');
});
