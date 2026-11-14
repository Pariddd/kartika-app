<?php

namespace App\Support;

use App\Enums\CaseMode;
use App\Enums\Role;
use App\Models\CaseFile;
use App\Models\User;

/**
 * Kapan nama tampilan akun boleh diperlihatkan. Semua syarat harus terpenuhi:
 * mode lokal, fitur dinyalakan di .env, mode pseudonim dimatikan admin, mode presentasi
 * mati, kasus data nyata, dan pengguna adalah admin atau analis.
 * Server publik tidak pernah menampilkan nama.
 */
final class AccountNames
{
    public static function visibleTo(?User $user, CaseFile $case): bool
    {
        return AppMode::isLocal()
            && (bool) config('radar.show_account_names')
            && ! config('radar.presentation_mask')
            && ! Pseudonym::active()
            && $case->mode === CaseMode::Live
            && $user !== null
            && $user->hasRole(Role::Admin, Role::Analis);
    }
}
