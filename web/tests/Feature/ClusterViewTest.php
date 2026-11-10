<?php

use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\ClusterReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
