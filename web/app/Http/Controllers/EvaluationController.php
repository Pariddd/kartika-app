<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Halaman Evaluasi: menampilkan hasil uji sintetis yang dihitung mesin Python
 * (python -m radar.analysis.robustness). Laravel hanya membaca berkas hasilnya.
 */
class EvaluationController extends Controller
{
    public const FILE = 'evaluation/hasil-evaluasi.json';

    /**
     * @return array<string, mixed>|null null bila berkas hasil belum ada atau versinya tidak dikenal
     */
    public static function report(): ?array
    {
        $path = resource_path(self::FILE);
        $report = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($report) && ($report['schema_version'] ?? null) === 1 ? $report : null;
    }

    /**
     * Ringkasan dataset uji (seed yang tidak dipakai menyetel ambang). Dipakai juga halaman depan.
     *
     * @return array<string, int>|null
     */
    public static function summary(): ?array
    {
        $report = self::report();
        if ($report === null) {
            return null;
        }

        $test = collect($report['baseline'])->where('group', 'uji');
        $withPlanted = $test->where('planted', '>', 0);

        return [
            'datasets' => $test->sum('datasets'),
            'planted' => $withPlanted->sum('planted'),
            'caught' => $withPlanted->sum('caught'),
            'false_flags' => $test->sum('false_flags'),
            'decoys_flagged' => $test->sum('decoys_flagged'),
        ];
    }

    public function show(): View
    {
        $report = self::report();
        if ($report === null) {
            return view('evaluation.show', ['report' => null]);
        }

        return view('evaluation.show', [
            'report' => $report,
            'summary' => self::summary(),
            'presetLabels' => [
                'standar' => 'Standar: 3 klaster tanaman, parafrase ringan sampai berat',
                'kontrol' => 'Kontrol: tanpa klaster tanaman (seharusnya tidak ada yang ditandai)',
                'parafrase' => 'Parafrase: 2 klaster tanaman yang menulis ulang hampir semua kalimat',
            ],
        ]);
    }
}
