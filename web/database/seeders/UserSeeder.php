<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Membuat atau memperbarui tiga akun awal. Aman dijalankan berulang.
 * Kata sandi wajib dari .env; tidak ada kata sandi bawaan di kode.
 */
class UserSeeder extends Seeder
{
    public const MIN_PASSWORD_LENGTH = 8;

    public function run(): void
    {
        foreach ([Role::Admin, Role::Analis, Role::Demo] as $role) {
            $email = config("radar.seed.{$role->value}.email");
            $password = (string) config("radar.seed.{$role->value}.password");

            if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
                $variable = 'SEED_'.strtoupper($role->value).'_PASSWORD';

                throw new RuntimeException(
                    "Isi {$variable} di .env dengan minimal ".self::MIN_PASSWORD_LENGTH.' karakter.'
                );
            }

            User::firstOrNew(['email' => $email])
                ->forceFill([
                    'name' => $role->label().' Radar',
                    'password' => $password,
                    'role' => $role,
                    'email_verified_at' => now(),
                ])
                ->save();
        }
    }
}
