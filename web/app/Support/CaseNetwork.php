<?php

namespace App\Support;

use App\Models\CaseFile;
use Illuminate\Support\Facades\DB;

/**
 * Menyiapkan data graf koordinasi untuk browser: ID akun internal diganti label per kasus
 * (Akun-0001). Nama tampilan dan kunci akun tidak pernah ikut dikirim ke halaman.
 */
final class CaseNetwork
{
    /**
     * @return array<string, mixed>|null null bila analisis belum menghasilkan graf
     */
    public static function build(CaseFile $case): ?array
    {
        $raw = DB::table('case_graphs')->where('case_id', $case->getKey())->value('graph');
        $graph = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($graph) || empty($graph['nodes'])) {
            return null;
        }

        $labels = DB::table('accounts')
            ->where('case_id', $case->getKey())
            ->whereIn('id', array_column($graph['nodes'], 'id'))
            ->pluck('display_label', 'id');

        $clusterUrls = $case->clusters()
            ->get(['id', 'display_label'])
            ->mapWithKeys(fn ($cluster) => [$cluster->display_label => route('clusters.show', [$case, $cluster])]);

        $nodes = [];
        foreach ($graph['nodes'] as $node) {
            $nodes[] = [
                'id' => (int) $node['id'],
                'label' => $labels[$node['id']] ?? 'Akun',
                'x' => (int) $node['x'],
                'y' => (int) $node['y'],
                'cluster' => $node['cluster'],
                'flagged' => (bool) $node['flagged'],
                'posts' => (int) $node['posts'],
                'links' => (int) $node['links'],
            ];
        }

        $flagged = collect($nodes)->where('flagged', true);

        return [
            'data' => [
                'nodes' => $nodes,
                'edges' => array_map(fn ($e) => [
                    'a' => (int) $e['a'], 'b' => (int) $e['b'], 'text' => (bool) $e['text'], 'time' => (bool) $e['time'], 'w' => (float) $e['w'],
                ], $graph['edges']),
                'clusterUrls' => $clusterUrls,
            ],
            'shown_nodes' => count($nodes),
            'shown_edges' => count($graph['edges']),
            'total_nodes' => (int) ($graph['total_nodes'] ?? count($nodes)),
            'total_edges' => (int) ($graph['total_edges'] ?? count($graph['edges'])),
            'truncated' => (bool) ($graph['truncated'] ?? false),
            'flagged_nodes' => $flagged->count(),
            'clusters' => collect($nodes)->whereNotNull('cluster')->groupBy('cluster')
                ->map(fn ($members, $label) => ['label' => $label, 'accounts' => $members->count(), 'flagged' => (bool) $members->first()['flagged']])
                ->sortBy([['flagged', 'desc'], ['label', 'asc']])->values()->all(),
        ];
    }
}
