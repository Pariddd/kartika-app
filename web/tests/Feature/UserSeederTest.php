<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

test('seeder menolak kata sandi kosong', function () {
    config(['radar.seed.admin.password' => null]);

    $this->seed(UserSeeder::class);
})->throws(RuntimeException::class, 'SEED_ADMIN_PASSWORD');

test('seeder menolak kata sandi kurang dari 8 karakter', function () {
    config([
        'radar.seed.admin.password' => 'pendek7',
        'radar.seed.analis.password' => 'kata-sandi-analis-1',
        'radar.seed.demo.password' => 'kata-sandi-demo-1',
    ]);

    $this->seed(UserSeeder::class);
})->throws(RuntimeException::class);

test('seeder membuat tiga akun dengan peran yang benar dan aman dijalankan ulang', function () {
    config([
        'radar.seed.admin.password' => 'kata-sandi-admin-1',
        'radar.seed.analis.password' => 'kata-sandi-analis-1',
        'radar.seed.demo.password' => 'kata-sandi-demo-1',
    ]);

    $this->seed(UserSeeder::class);
    $this->seed(UserSeeder::class);

    expect(User::count())->toBe(3)
        ->and(User::firstWhere('email', 'admin@radar.local')->role)->toBe(Role::Admin)
        ->and(User::firstWhere('email', 'demo@radar.local')->role)->toBe(Role::Demo)
        ->and(Hash::check('kata-sandi-demo-1', User::firstWhere('email', 'demo@radar.local')->password))->toBeTrue();
});
