<?php

namespace App\Policies;

use App\Enums\CaseMode;
use App\Enums\Role;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CaseFilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Kasus data nyata hanya untuk admin dan analis; demo (juri) tidak pernah melihatnya.
     */
    public function view(User $user, CaseFile $case): bool
    {
        if ($case->mode === CaseMode::Live) {
            return $user->hasRole(Role::Admin, Role::Analis);
        }

        return true;
    }

    /**
     * Admin boleh menghapus kasus apa pun; pengguna lain hanya kasus buatannya sendiri
     * (misalnya unggahan CSV milik akun demo), selama ia memang boleh melihatnya.
     */
    public function delete(User $user, CaseFile $case): bool
    {
        if ($user->hasRole(Role::Admin)) {
            return true;
        }

        return $case->created_by === $user->getKey() && $this->view($user, $case);
    }

    public function create(User $user): bool
    {
        return Gate::forUser($user)->allows('collect-data');
    }
}
