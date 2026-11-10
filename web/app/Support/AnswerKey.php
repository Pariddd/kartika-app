<?php

namespace App\Support;

use App\Models\CaseFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Membandingkan klaster yang ditandai dengan kunci jawaban sintetis
 * (docs/kontrak.md bagian 7). Logikanya sama dengan engine/radar/analysis/evaluate.py:
 * cocok bila Jaccard anggota >= 0,5; satu klaster hanya untuk satu tanaman.
 */
final class AnswerKey
{
    public const MATCH_JACCARD = 0.5;

    /**
     * @return array{
     *     rows: list<array{cluster: string, size: int, planted: ?string, jaccard: ?float}>,
     *     missed: list<string>,
     *     planted_count: int,
     *     flagged_count: int,
     *     recall: ?float,
     *     precision: ?float
     * }
     */
    public static function evaluate(CaseFile $case): array
    {
        /** @var Collection<string, list<int>> $planted */
        $planted = DB::table('demo_ground_truth')
            ->where('case_id', $case->getKey())
            ->get(['planted_label', 'account_id'])
            ->groupBy('planted_label')
            ->map(fn (Collection $rows) => $rows->pluck('account_id')->all());

        $flagged = $case->clusters()
            ->where('is_flagged', true)
            ->orderBy('display_label')
            ->with('members:id')
            ->get()
            ->mapWithKeys(fn ($cluster) => [$cluster->display_label => $cluster->members->pluck('id')->all()]);

        $candidates = [];
        foreach ($planted as $label => $members) {
            foreach ($flagged as $clusterLabel => $clusterMembers) {
                $candidates[] = [self::jaccard($members, $clusterMembers), $label, $clusterLabel];
            }
        }
        usort($candidates, fn (array $a, array $b) => $b[0] <=> $a[0]);

        $matchByCluster = [];
        $matchedPlanted = [];
        foreach ($candidates as [$score, $label, $clusterLabel]) {
            if ($score < self::MATCH_JACCARD || isset($matchedPlanted[$label]) || isset($matchByCluster[$clusterLabel])) {
                continue;
            }
            $matchByCluster[$clusterLabel] = [$label, round($score, 2)];
            $matchedPlanted[$label] = true;
        }

        $rows = $flagged->map(fn (array $members, string $label) => [
            'cluster' => $label,
            'size' => count($members),
            'planted' => $matchByCluster[$label][0] ?? null,
            'jaccard' => $matchByCluster[$label][1] ?? null,
        ])->values()->all();

        return [
            'rows' => $rows,
            'missed' => $planted->keys()->reject(fn ($label) => isset($matchedPlanted[$label]))->sort()->values()->all(),
            'planted_count' => $planted->count(),
            'flagged_count' => $flagged->count(),
            'recall' => $planted->isEmpty() ? null : count($matchedPlanted) / $planted->count(),
            'precision' => $flagged->isEmpty() ? null : count($matchByCluster) / $flagged->count(),
        ];
    }

    /**
     * @param  list<int>  $a
     * @param  list<int>  $b
     */
    public static function jaccard(array $a, array $b): float
    {
        $union = count(array_unique(array_merge($a, $b)));

        return $union === 0 ? 0.0 : count(array_intersect($a, $b)) / $union;
    }
}
