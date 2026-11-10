<?php

use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

test('admin menghapus kasus beserta seluruh datanya dan tercatat di log audit', function () {
    $case = CaseFile::factory()->create(['name' => 'Kasus lama']);
    seedAnalysis($case);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('cases.destroy', $case), ['confirm' => '1'])
        ->assertRedirect(route('cases.index'))
        ->assertSessionHas('status');

    expect(CaseFile::find($case->id))->toBeNull();
    foreach (['posts', 'accounts', 'clusters', 'issue_scores', 'analysis_jobs', 'demo_ground_truth'] as $table) {
        expect(DB::table($table)->where('case_id', $case->id)->count())->toBe(0, $table);
    }
    expect(DB::table('cluster_members')->count())->toBe(0)
        ->and(DB::table('cluster_signals')->count())->toBe(0)
        ->and(AuditLog::where('action', 'cases.destroy')->where('user_id', $admin->id)->exists())->toBeTrue();
});

test('tanpa centang konfirmasi kasus tidak dihapus', function () {
    $case = CaseFile::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('cases.show', $case))
        ->delete(route('cases.destroy', $case))
        ->assertSessionHasErrors('confirm');

    expect(CaseFile::find($case->id))->not->toBeNull();
});

test('pengguna hanya bisa menghapus kasus buatannya sendiri', function () {
    $owner = User::factory()->demo()->create();
    $other = User::factory()->demo()->create();
    $analis = User::factory()->create();
    $case = CaseFile::factory()->create(['created_by' => $owner->id]);

    $this->actingAs($other)->delete(route('cases.destroy', $case), ['confirm' => '1'])->assertForbidden();
    $this->actingAs($analis)->delete(route('cases.destroy', $case), ['confirm' => '1'])->assertForbidden();
    expect(CaseFile::find($case->id))->not->toBeNull();

    $this->actingAs($owner)->delete(route('cases.destroy', $case), ['confirm' => '1'])->assertRedirect(route('cases.index'));
    expect(CaseFile::find($case->id))->toBeNull();
});

test('kasus yang sedang diproses worker tidak bisa dihapus', function () {
    $case = CaseFile::factory()->create();
    $case->jobs()->create(['job_type' => 'analyze', 'status' => 'running', 'payload' => ['schema_version' => 1]]);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('cases.show', $case))
        ->delete(route('cases.destroy', $case), ['confirm' => '1'])
        ->assertSessionHasErrors('confirm');

    expect(CaseFile::find($case->id))->not->toBeNull();
});

test('file unggahan yang belum dibaca ikut dihapus bersama kasusnya', function () {
    Storage::fake('local');
    $ref = 'uploads/7f3c2a9e-5b1d-4c8e-9a20-1e6f3b7d8c41.csv';
    Storage::disk('local')->put($ref, 'akun,teks,waktu');
    $case = CaseFile::factory()->create();
    $case->jobs()->create(['job_type' => 'analyze_csv', 'status' => 'pending', 'payload' => ['schema_version' => 1, 'params' => ['file_ref' => $ref]]]);

    $this->actingAs(User::factory()->admin()->create())->delete(route('cases.destroy', $case), ['confirm' => '1']);

    Storage::disk('local')->assertMissing($ref);
});

test('tombol hapus hanya tampil bagi yang berhak', function () {
    $case = CaseFile::factory()->create();

    $this->actingAs(User::factory()->demo()->create())->get(route('cases.show', $case))->assertDontSee('Hapus permanen');
    $this->actingAs(User::factory()->admin()->create())->get(route('cases.show', $case))->assertSee('Hapus permanen');
});
