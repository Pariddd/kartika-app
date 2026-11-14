<?php

namespace App\Http\Controllers;

use App\Enums\CaseMode;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Post;
use App\Support\AccountNames;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Semua post (komentar) satu kasus, agar analis bisa membaca data mentahnya sendiri.
 * Teks tetap lewat komponen post-text (penyamaran konten dan aturan nama yang sama
 * dengan halaman klaster). Hanya post milik kasus di URL yang pernah diambil.
 */
class CasePostController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request, CaseFile $case): View
    {
        $filters = $request->validate([
            'klaster' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:100'],
            'urut' => ['nullable', 'in:lama,baru'],
            'sumber' => ['nullable', 'string', 'max:80'],
        ]);

        $clusters = $case->clusters()->orderByDesc('is_flagged')->orderBy('display_label')->get(['id', 'display_label', 'is_flagged', 'size']);
        $clusterOf = $this->clusterOfAccounts($case);

        $query = Post::query()->where('case_id', $case->getKey())->with('account:id,display_label,display_name');

        $selected = $filters['klaster'] ?? '';
        if ($selected === 'ditandai') {
            $query->whereIn('account_id', $clusterOf->filter(fn ($c) => $c->is_flagged)->keys());
        } elseif ($selected === 'lain') {
            $query->whereNotIn('account_id', $clusterOf->filter(fn ($c) => $c->is_flagged)->keys());
        } elseif ($selected !== '' && ($cluster = $clusters->firstWhere('display_label', $selected))) {
            $query->whereIn('account_id', $clusterOf->filter(fn ($c) => $c->id === $cluster->id)->keys());
        } else {
            $selected = '';
        }

        $sources = $this->sources($case, $clusterOf);
        $source = $filters['sumber'] ?? '';
        if ($source !== '' && $sources->has($source)) {
            $query->where('source_ref', $source);
        } else {
            $source = '';
        }

        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            // Wildcard LIKE di-escape: pencarian adalah teks biasa, bukan pola.
            $pattern = '%'.addcslashes(mb_strtolower($search), '\\%_').'%';
            $query->whereRaw("LOWER(text) LIKE ? ESCAPE '\\'", [$pattern]);
        }

        $oldest = ($filters['urut'] ?? 'lama') === 'lama';
        $posts = $query->orderBy('posted_at', $oldest ? 'asc' : 'desc')->orderBy('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        $showNames = AccountNames::visibleTo($request->user(), $case);
        if ($showNames && $posts->isNotEmpty()) {
            AuditLog::record('posts.names', $request->user()->getKey(), $case->getKey(), $request->ip(), 200);
        }

        return view('cases.posts', [
            'case' => $case,
            'posts' => $posts,
            'clusters' => $clusters,
            'clusterOf' => $clusterOf,
            'selected' => $selected,
            'sources' => $sources,
            'source' => $source,
            'search' => $search,
            'oldest' => $oldest,
            'total' => Post::query()->where('case_id', $case->getKey())->count(),
            'masked' => $case->mode === CaseMode::Live && config('radar.presentation_mask'),
            'showNames' => $showNames,
        ]);
    }

    /**
     * Ringkasan per konten sumber: jumlah komentar, berapa yang dari klaster ditandai, dan klaster
     * ditandai mana saja yang hadir. Satu klaster yang muncul di banyak konten adalah pola kampanye.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $clusterOf
     * @return \Illuminate\Support\Collection<string, object>
     */
    private function sources(CaseFile $case, $clusterOf)
    {
        $rows = \Illuminate\Support\Facades\DB::table('posts')
            ->where('case_id', $case->getKey())
            ->whereNotNull('source_ref')
            ->select('source_ref', 'account_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) AS n'))
            ->groupBy('source_ref', 'account_id')
            ->get();

        return $rows->groupBy('source_ref')->map(function ($group, $ref) use ($clusterOf) {
            $flagged = $group->filter(fn ($row) => $clusterOf->get($row->account_id)?->is_flagged);

            return (object) [
                'ref' => $ref,
                'total' => (int) $group->sum('n'),
                'flagged' => (int) $flagged->sum('n'),
                'clusters' => $flagged->map(fn ($row) => $clusterOf->get($row->account_id)->display_label)->unique()->sort()->values()->all(),
            ];
        })->sortByDesc('total')->take(100);
    }

    /**
     * account_id → klaster (id, display_label, is_flagged) untuk kasus ini.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function clusterOfAccounts(CaseFile $case)
    {
        return \Illuminate\Support\Facades\DB::table('cluster_members')
            ->join('clusters', 'clusters.id', '=', 'cluster_members.cluster_id')
            ->where('clusters.case_id', $case->getKey())
            ->get(['cluster_members.account_id', 'clusters.id', 'clusters.display_label', 'clusters.is_flagged'])
            ->keyBy('account_id')
            ->map(fn ($row) => (object) ['id' => (int) $row->id, 'display_label' => $row->display_label, 'is_flagged' => (bool) $row->is_flagged]);
    }
}
