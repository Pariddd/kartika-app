<?php

use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\ClusterReview;
use App\Models\User;
use App\Support\CaseReport;

test('ringkasan membuka dengan kesimpulan, lalu temuan, saran, dan keterbatasan', function () {
    $case = CaseFile::factory()->create(['name' => 'Isu Pasar Lama']);
    [$k1, $k2] = seedAnalysis($case);

    $this->actingAs(User::factory()->demo()->create())
        ->get(route('cases.report', $case))
        ->assertOk()
        ->assertSeeInOrder(['KESIMPULAN', 'Koordinasi signifikan', '21% percakapan (454 dari 2.127 post) berasal dari 2 klaster', 'Temuan dan bukti utama', 'Saran tindak lanjut', 'Keterbatasan', 'Metode'])
        ->assertSee('Penilaian analis: 0 dari 2 klaster sudah dinilai')
        ->assertSee('Periksa bukti dan beri penilaian untuk klaster K-01, K-02')
        ->assertSee('Telusuri isi dan umur domain yang dibagikan klaster K-01')
        ->assertSee('Kasus ini memakai data sintetis untuk demonstrasi.')
        ->assertDontSee('K-03')                       // klaster tidak ditandai tidak masuk ringkasan
        ->assertDontSee('Pasar Lama sudah tidak layak') // teks post tidak dimuat
        ->assertDontSee('berita-palsu.example');      // domain tidak dimuat

    expect(AuditLog::where('action', 'cases.report')->where('case_id', $case->id)->where('status', 200)->exists())->toBeTrue();
});

test('penilaian analis mengubah saran tindak lanjut', function () {
    $case = CaseFile::factory()->create();
    [$k1, $k2] = seedAnalysis($case);
    $analis = User::factory()->create();
    ClusterReview::create(['cluster_id' => $k1->id, 'user_id' => $analis->id, 'verdict' => 'terkoordinasi']);
    ClusterReview::create(['cluster_id' => $k2->id, 'user_id' => $analis->id, 'verdict' => 'keliru']);

    $this->actingAs($analis)
        ->get(route('cases.report', $case))
        ->assertOk()
        ->assertSee('2 dari 2 klaster sudah dinilai (1 terkoordinasi, 1 keliru, 0 belum pasti)')
        ->assertSee('Klaster K-02 dinilai keliru oleh analis')
        ->assertSee('Pantau pola jam serentak klaster K-01 untuk')
        ->assertDontSee('Periksa bukti dan beri penilaian');
});

test('kasus tanpa klaster ditandai menyatakan tidak ada koordinasi', function () {
    $flagged = collect();
    expect(CaseReport::headline(1.0, 0, 500, $flagged))->toStartWith('Tidak ditemukan koordinasi');
    expect(CaseReport::headline(1.0, 0, 0, $flagged))->toBe('Tidak ada post untuk dianalisis pada kasus ini.');
});

test('pita kesimpulan mengikuti porsi post terkoordinasi', function (float $share, string $label) {
    expect(CaseReport::band($share))->toBe($label);
})->with([
    [0.05, 'Koordinasi terbatas'],
    [0.10, 'Koordinasi signifikan'],
    [0.29, 'Koordinasi signifikan'],
    [0.30, 'Koordinasi dominan'],
]);

test('ringkasan kasus yang belum dianalisis menampilkan pesan, bukan error', function () {
    $case = CaseFile::factory()->create();

    $this->actingAs(User::factory()->demo()->create())
        ->get(route('cases.report', $case))
        ->assertOk()
        ->assertSee('Analisis kasus ini belum selesai');
});

test('akun demo tidak bisa membuka ringkasan kasus data nyata', function () {
    $case = CaseFile::factory()->live()->create();
    seedAnalysis($case);

    $this->actingAs(User::factory()->demo()->create())
        ->get(route('cases.report', $case))
        ->assertForbidden();
});

test('tombol ringkasan muncul di halaman kasus setelah analisis selesai', function () {
    $case = CaseFile::factory()->create();
    $user = User::factory()->demo()->create();
    $this->actingAs($user)->get(route('cases.show', $case))->assertDontSee('Ringkasan BLUF');

    seedAnalysis($case);
    $this->actingAs($user)->get(route('cases.show', $case))->assertSee('Ringkasan BLUF');
});
