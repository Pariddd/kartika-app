<?php

namespace App\Console\Commands;

use App\Models\CaseFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Menghapus kasus yang kedaluwarsa (unggahan CSV setelah 24 jam) beserta seluruh datanya
 * (cascade), dan file unggahan yang tertinggal. Dijadwalkan tiap jam di routes/console.php.
 */
class PurgeExpiredCases extends Command
{
    protected $signature = 'radar:purge-expired';

    protected $description = 'Hapus kasus kedaluwarsa dan file unggahan yang tertinggal';

    public function handle(): int
    {
        $expired = CaseFile::query()->whereNotNull('expires_at')->where('expires_at', '<', now())->get();

        foreach ($expired as $case) {
            // first()->payload, bukan value('payload'): value() melewati cast array.
            $fileRef = $case->jobs()->where('job_type', 'analyze_csv')->first()?->payload['params']['file_ref'] ?? null;
            if (is_string($fileRef) && str_starts_with($fileRef, 'uploads/')) {
                Storage::disk('local')->delete($fileRef);
            }
            $case->delete();
        }

        // File unggahan lebih tua dari 24 jam yang tidak lagi punya kasus (misalnya worker mati).
        $stale = collect(Storage::disk('local')->files('uploads'))
            ->filter(fn ($path) => Storage::disk('local')->lastModified($path) < now()->subDay()->getTimestamp());
        Storage::disk('local')->delete($stale->all());

        $this->info("{$expired->count()} kasus kedaluwarsa dan {$stale->count()} file tertinggal dihapus.");

        return self::SUCCESS;
    }
}
