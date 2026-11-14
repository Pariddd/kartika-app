<?php

namespace App\Http\Controllers;

use App\Support\AppMode;
use App\Support\Pseudonym;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pengaturan privasi (khusus admin): saklar mode pseudonim dan penghapusan nama tersimpan.
 */
class PrivacySettingsController extends Controller
{
    public function show(): View
    {
        return view('settings.privacy', [
            'active' => Pseudonym::active(),
            'lastChange' => Pseudonym::lastChange(),
            'stored' => Pseudonym::storedNames(),
            'isLocal' => AppMode::isLocal(),
            'envAllows' => (bool) config('radar.show_account_names'),
            'presentationMask' => (bool) config('radar.presentation_mask'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['pseudonym' => ['required', 'in:on,off']]);
        $active = $data['pseudonym'] === 'on';

        if ($active !== Pseudonym::active()) {
            Pseudonym::set($active, $request->user(), $request->ip());
        }

        return redirect()->route('settings.privacy')->with('status', $active
            ? 'Mode pseudonim aktif. Nama akun asli disembunyikan dari semua peran, dan worker tidak menyimpan nama baru.'
            : 'Mode pseudonim dimatikan. Admin dan analis kembali melihat nama akun pada kasus data nyata.');
    }

    public function purge(Request $request): RedirectResponse
    {
        $request->validate(
            ['confirm' => ['accepted']],
            ['confirm.accepted' => 'Centang konfirmasi dulu. Penghapusan nama tidak bisa dibatalkan.'],
        );

        $removed = Pseudonym::purgeNames($request->user(), $request->ip());

        return redirect()->route('settings.privacy')->with('status', sprintf(
            'Nama dihapus permanen dari %s akun dan %s post. Hasil analisis tidak berubah.',
            number_format($removed['accounts'], 0, ',', '.'),
            number_format($removed['posts'], 0, ',', '.'),
        ));
    }
}
