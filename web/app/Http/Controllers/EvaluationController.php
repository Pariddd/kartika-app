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

    public function show(): View
    {
        $path = resource_path(self::FILE);
        $report = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($report) || ($report['schema_version'] ?? null) !== 1) {
            return view('evaluation.show', ['report' => null]);
        }

        $test = collect($report['baseline'])->where('group', 'uji');
        $withPlanted = $test->where('planted', '>', 0);

        return view('evaluation.show', [
            'report' => $report,
            'summary' => [
                'datasets' => $test->sum('datasets'),
                'planted' => $withPlanted->sum('planted'),
                'caught' => $withPlanted->sum('caught'),
                'false_flags' => $test->sum('false_flags'),
                'decoys_flagged' => $test->sum('decoys_flagged'),
            ],
            'presetLabels' => [
                'standar' => 'Standar: 3 klaster tanaman, parafrase ringan sampai berat',
                'kontrol' => 'Kontrol: tanpa klaster tanaman (seharusnya tidak ada yang ditandai)',
                'parafrase' => 'Parafrase: 2 klaster tanaman yang menulis ulang hampir semua kalimat',
            ],
        ]);
    }
}
