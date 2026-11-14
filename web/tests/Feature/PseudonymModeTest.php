<?php

use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\User;
use App\Support\Pseudonym;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['radar.mode' => 'local', 'radar.show_account_names' => true, 'radar.presentation_mask' => false]);
});

function liveCaseWithStoredNames(): array
{
    $case = CaseFile::factory()->live()->create();
    [$k1] = seedAnalysis($case);
    DB::table('accounts')->where('case_id', $case->id)->where('display_label', 'Akun-0001')->update(['display_name' => 'Nama Kanal Asli']);
    DB::table('posts')->where('case_id', $case->id)->orderBy('id')->limit(1)
        ->update(['text' => 'Setuju @u_3fa9c2d1', 'text_raw' => 'Setuju @budi_asli']);

    return [$case, $k1];
}

test('mode pseudonim aktif secara bawaan: admin dan analis tidak melihat nama asli', function () {
    [$case, $k1] = liveCaseWithStoredNames();

    expect(Pseudonym::active())->toBeTrue();

    foreach ([User::factory()->admin()->create(), User::factory()->create()] as $user) {
        $this->actingAs($user)
            ->get(route('clusters.show', [$case, $k1]))
            ->assertOk()
            ->assertDontSee('Nama Kanal Asli')
            ->assertDontSee('@budi_asli')
            ->assertSee('@u_3fa9c2d1');
    }
    expect(AuditLog::where('action', 'clusters.names')->count())->toBe(0);
});

test('nilai pengaturan yang rusak dianggap pseudonim aktif', function () {
    DB::table('app_settings')->insert(['key' => Pseudonym::KEY, 'value' => '"rusak"', 'updated_at' => now()]);

    expect(Pseudonym::active())->toBeTrue();
});

test('admin bisa mematikan dan mengaktifkan kembali mode pseudonim, dan tercatat di log audit', function () {
    [$case, $k1] = liveCaseWithStoredNames();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('settings.pseudonym'), ['pseudonym' => 'off'])
        ->assertRedirect(route('settings.privacy'));
    expect(Pseudonym::active())->toBeFalse();

    $this->actingAs(User::factory()->create())
        ->get(route('clusters.show', [$case, $k1]))
        ->assertSee('Nama Kanal Asli')
        ->assertSee('@budi_asli');

    $this->actingAs($admin)->put(route('settings.pseudonym'), ['pseudonym' => 'on']);
    expect(Pseudonym::active())->toBeTrue();

    $this->actingAs(User::factory()->create())
        ->get(route('clusters.show', [$case, $k1]))
        ->assertDontSee('Nama Kanal Asli');

    expect(AuditLog::where('action', 'settings.pseudonym_off')->where('user_id', $admin->id)->count())->toBe(1)
        ->and(AuditLog::where('action', 'settings.pseudonym_on')->where('user_id', $admin->id)->count())->toBe(1);
});

test('hanya admin yang bisa membuka dan mengubah pengaturan privasi', function () {
    foreach ([User::factory()->create(), User::factory()->demo()->create()] as $user) {
        $this->actingAs($user)->get(route('settings.privacy'))->assertForbidden();
        $this->actingAs($user)->put(route('settings.pseudonym'), ['pseudonym' => 'off'])->assertForbidden();
        $this->actingAs($user)->delete(route('settings.purge'), ['confirm' => '1'])->assertForbidden();
    }

    expect(Pseudonym::active())->toBeTrue();
});

test('tamu diarahkan ke halaman masuk', function () {
    $this->get(route('settings.privacy'))->assertRedirect(route('login'));
});

test('nilai saklar selain on atau off ditolak', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('settings.pseudonym'), ['pseudonym' => 'mungkin'])
        ->assertSessionHasErrors('pseudonym');

    expect(Pseudonym::active())->toBeTrue();
});

test('halaman pengaturan menampilkan status dan jumlah nama tersimpan', function () {
    liveCaseWithStoredNames();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('settings.privacy'))
        ->assertOk()
        ->assertSee('Mode pseudonim')
        ->assertSee('Aktif')
        ->assertSee('Matikan mode pseudonim')
        ->assertSee('1</span> nama akun', false)
        ->assertDontSee('Nama Kanal Asli');
});

test('hapus nama tersimpan butuh konfirmasi, lalu menghapus permanen tanpa mengubah analisis', function () {
    [$case] = liveCaseWithStoredNames();
    $admin = User::factory()->admin()->create();
    $clusters = DB::table('clusters')->where('case_id', $case->id)->count();

    $this->actingAs($admin)->delete(route('settings.purge'))->assertSessionHasErrors('confirm');
    expect(DB::table('accounts')->whereNotNull('display_name')->count())->toBe(1);

    $this->actingAs($admin)->delete(route('settings.purge'), ['confirm' => '1'])->assertRedirect(route('settings.privacy'));

    expect(DB::table('accounts')->whereNotNull('display_name')->count())->toBe(0)
        ->and(DB::table('posts')->whereNotNull('text_raw')->count())->toBe(0)
        ->and(DB::table('posts')->where('text', 'Setuju @u_3fa9c2d1')->count())->toBe(1)
        ->and(DB::table('clusters')->where('case_id', $case->id)->count())->toBe($clusters)
        ->and(AuditLog::where('action', 'settings.names_purged')->count())->toBe(1);
});

test('navbar menunjukkan status pseudonim kepada admin dan analis, tidak kepada akun demo', function () {
    $this->actingAs(User::factory()->create())->get(route('cases.index'))->assertSee('Pseudonim aktif');
    $this->actingAs(User::factory()->demo()->create())->get(route('cases.index'))->assertDontSee('Pseudonim aktif');

    DB::table('app_settings')->insert(['key' => Pseudonym::KEY, 'value' => 'false', 'updated_at' => now()]);
    $this->actingAs(User::factory()->create())->get(route('cases.index'))->assertSee('Nama asli terlihat');
});

test('menu Privasi hanya muncul untuk admin', function () {
    $this->actingAs(User::factory()->admin()->create())->get(route('cases.index'))->assertSee(route('settings.privacy'), false);
    $this->actingAs(User::factory()->create())->get(route('cases.index'))->assertDontSee(route('settings.privacy'), false);
});
