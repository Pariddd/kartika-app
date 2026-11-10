<?php

use App\Enums\CaseMode;
use App\Enums\CaseStatus;
use App\Models\AnalysisJob;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function csvFile(string $content, string $name = 'data warga budi.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

const VALID_CSV = "akun,teks,waktu,url,tanggal_akun\n"
    ."budi,\"Pasar Lama harus dipindah, setuju?\",2026-11-01T19:01:00+07:00,https://a.example/x,2026-10-01\n"
    ."ani,Pasar Lama harus dipindah!,2026-11-01 19:02,,\n";

test('halaman coba sendiri tampil untuk semua peran', function () {
    $this->actingAs(User::factory()->demo()->create())
        ->get('/coba')
        ->assertOk()
        ->assertSee('Unduh file contoh');
});

test('unggahan valid membuat kasus CSV dan job analyze_csv sesuai kontrak', function () {
    $user = User::factory()->demo()->create();

    $response = $this->actingAs($user)->post('/coba', ['file' => csvFile(VALID_CSV), 'consent' => '1']);

    $case = CaseFile::sole();
    $response->assertRedirect(route('cases.show', $case));

    expect($case->mode)->toBe(CaseMode::Csv)
        ->and($case->status)->toBe(CaseStatus::Analyzing)
        ->and($case->name)->not->toContain('budi')  // nama file asli tidak dipakai
        ->and($case->expires_at->diffInHours(now()->addDay(), true))->toBeLessThan(1);

    $payload = AnalysisJob::sole()->payload;
    expect($payload['job_type'])->toBe('analyze_csv')
        ->and($payload['case_id'])->toBe($case->id)
        ->and($payload['params']['delete_after_ingest'])->toBeTrue()
        ->and($payload['params']['file_ref'])->toMatch('/^uploads\/[0-9a-f-]{36}\.csv$/');

    Storage::disk('local')->assertExists($payload['params']['file_ref']);
    expect(Storage::disk('local')->get($payload['params']['file_ref']))->toBe(VALID_CSV);
    expect(AuditLog::where('action', 'upload.store')->where('case_id', $case->id)->exists())->toBeTrue();
});

test('unggahan diizinkan di mode publik', function () {
    config(['radar.mode' => 'public']);

    $this->actingAs(User::factory()->demo()->create())
        ->post('/coba', ['file' => csvFile(VALID_CSV), 'consent' => '1'])
        ->assertSessionHasNoErrors();

    expect(CaseFile::count())->toBe(1);
});

test('nama kasus dari pengguna dipakai bila diisi', function () {
    $this->actingAs(User::factory()->create())
        ->post('/coba', ['file' => csvFile(VALID_CSV), 'consent' => '1', 'name' => 'Uji juri']);

    expect(CaseFile::sole()->name)->toBe('Uji juri');
});

test('unggahan tidak valid ditolak tanpa menyimpan apa pun', function (array $input, string $expected) {
    $this->actingAs(User::factory()->create())
        ->post('/coba', $input)
        ->assertSessionHasErrors();

    expect(session('errors')->all())->toContain($expected)
        ->and(CaseFile::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'tanpa file' => [['consent' => '1'], 'Pilih file CSV.'],
    'tanpa persetujuan' => [['file' => csvFile(VALID_CSV)], 'Centang pernyataan bahwa Anda berhak memakai data ini.'],
    'bukan CSV' => [['file' => UploadedFile::fake()->create('foto.png', 10, 'image/png'), 'consent' => '1'], 'File harus berformat CSV.'],
    'kolom wajib hilang' => [['file' => csvFile("akun,teks\nx,y\n"), 'consent' => '1'], 'Kolom wajib tidak ada: waktu. Baris pertama harus berisi nama kolom.'],
    'kolom asing' => [['file' => csvFile("akun,teks,waktu,password\nx,y,2026-11-01T10:00:00+07:00,z\n"), 'consent' => '1'], 'Kolom tidak dikenal: password.'],
    'waktu salah' => [['file' => csvFile("akun,teks,waktu\nx,y,kemarin\n"), 'consent' => '1'], 'Baris 2: format waktu harus ISO 8601, misalnya 2026-11-01T19:02:00+07:00.'],
    'tanpa baris data' => [['file' => csvFile("akun,teks,waktu\n"), 'consent' => '1'], 'Tidak ada baris data di bawah header.'],
]);

test('lebih dari 2000 baris ditolak', function () {
    $rows = collect(range(1, 2001))->map(fn ($i) => "a{$i},teks {$i},2026-11-01T10:00:00+07:00")->implode("\n");

    $this->actingAs(User::factory()->create())
        ->post('/coba', ['file' => csvFile("akun,teks,waktu\n{$rows}\n"), 'consent' => '1'])
        ->assertSessionHasErrors('file');

    expect(CaseFile::count())->toBe(0);
});

test('file contoh bisa diunduh', function () {
    $response = $this->actingAs(User::factory()->demo()->create())->get('/coba/contoh.csv');

    $response->assertOk()->assertDownload('contoh-radar-narasi.csv');
    expect(file_get_contents($response->baseResponse->getFile()->getPathname()))->toStartWith('akun,teks,waktu,url,tanggal_akun');
});

test('file contoh lolos validasi unggahan', function () {
    $this->actingAs(User::factory()->create())
        ->post('/coba', ['file' => new UploadedFile(resource_path('samples/contoh-radar-narasi.csv'), 'contoh.csv', 'text/csv', null, true), 'consent' => '1'])
        ->assertSessionHasNoErrors();
});

test('tamu tidak bisa mengunggah', function () {
    $this->post('/coba', ['file' => csvFile(VALID_CSV), 'consent' => '1'])->assertRedirect('/login');
});

test('radar:purge-expired menghapus kasus kedaluwarsa beserta filenya, kasus lain tetap', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/coba', ['file' => csvFile(VALID_CSV), 'consent' => '1']);
    $expired = CaseFile::sole();
    $fileRef = AnalysisJob::sole()->payload['params']['file_ref'];
    $expired->update(['expires_at' => now()->subMinute()]);
    $kept = CaseFile::factory()->create();

    $this->artisan('radar:purge-expired')->assertSuccessful();

    expect(CaseFile::find($expired->id))->toBeNull()
        ->and(CaseFile::find($kept->id))->not->toBeNull()
        ->and(AnalysisJob::count())->toBe(0);
    Storage::disk('local')->assertMissing($fileRef);
});
