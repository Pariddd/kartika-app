<?php

namespace App\Console\Commands;

use App\Enums\CaseMode;
use App\Enums\CaseStatus;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\Role;
use App\Models\CaseFile;
use App\Models\User;
use App\Support\JobPayload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Membuat kasus demo berisi data sintetis. Datanya dibuat oleh worker Python
 * (job collect, platform "synthetic"), lengkap dengan kunci jawaban.
 * Boleh dijalankan di mode publik karena tidak menyentuh data nyata.
 */
class CreateDemoCase extends Command
{
    public const PRESETS = [
        'standar' => 'Penutupan Pasar Lama Kota Sentosa',
        'kontrol' => 'Kontrol organik: Pasar Lama Kota Sentosa',
        'parafrase' => 'Uji ketahanan: parafrase tinggi',
    ];

    protected $signature = 'radar:demo
        {preset=standar : standar, kontrol, atau parafrase}
        {--seed=42 : seed acak; seed sama menghasilkan data identik}
        {--user= : email pembuat kasus (bawaan: admin pertama)}';

    protected $description = 'Buat kasus demo dengan data sintetis dan kunci jawaban';

    public function handle(): int
    {
        $preset = (string) $this->argument('preset');
        $seed = filter_var($this->option('seed'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);

        if (! array_key_exists($preset, self::PRESETS)) {
            $this->error('Preset harus salah satu dari: '.implode(', ', array_keys(self::PRESETS)).'.');

            return self::FAILURE;
        }

        if ($seed === false) {
            $this->error('Seed harus bilangan bulat 0 sampai 2147483647.');

            return self::FAILURE;
        }

        $user = $this->option('user')
            ? User::firstWhere('email', $this->option('user'))
            : User::where('role', Role::Admin)->orderBy('id')->first();

        if ($user === null) {
            $this->error('Pengguna tidak ditemukan. Jalankan "php artisan db:seed" lebih dulu atau isi --user.');

            return self::FAILURE;
        }

        $params = ['platform' => 'synthetic', 'preset' => $preset, 'seed' => $seed];

        $case = DB::transaction(function () use ($preset, $params, $user): CaseFile {
            $case = CaseFile::create([
                'name' => self::PRESETS[$preset],
                'mode' => CaseMode::Demo,
                'sources' => ['synthetic'],
                'query' => $params,
                'status' => CaseStatus::Collecting,
                'created_by' => $user->getKey(),
            ]);

            $case->jobs()->create([
                'job_type' => JobType::Collect,
                'status' => JobStatus::Pending,
                'payload' => JobPayload::make(JobType::Collect, $case, $user, $params),
            ]);

            return $case;
        });

        $this->info("Kasus demo #{$case->id} dibuat ({$preset}, seed {$seed}). Jalankan worker untuk membuat datanya.");

        return self::SUCCESS;
    }
}
