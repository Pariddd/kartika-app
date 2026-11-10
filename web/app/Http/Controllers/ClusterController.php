<?php

namespace App\Http\Controllers;

use App\Enums\CaseMode;
use App\Enums\SignalType;
use App\Http\Requests\StoreClusterReviewRequest;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Cluster;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClusterController extends Controller
{
    public function show(CaseFile $case, Cluster $cluster): View
    {
        $cluster->load([
            'signals',
            'latestReview.user',
            'members' => fn ($query) => $query->withCount('posts')->orderBy('display_label'),
        ]);

        $signals = $cluster->signals->keyBy(fn ($signal) => $signal->signal->value);

        // Post yang dirujuk bukti teks. Hanya post milik kasus ini yang diambil.
        $pairs = $signals->get(SignalType::Text->value)?->evidence['top_pairs'] ?? [];
        $postIds = collect($pairs)->flatMap(fn ($pair) => [$pair['post_a'], $pair['post_b']])->unique()->all();
        $posts = Post::query()
            ->where('case_id', $case->getKey())
            ->whereIn('id', $postIds)
            ->with('account:id,display_label')
            ->get()
            ->keyBy('id');

        return view('clusters.show', [
            'case' => $case,
            'cluster' => $cluster,
            'signals' => $signals,
            'pairs' => $pairs,
            'posts' => $posts,
            'masked' => $case->mode === CaseMode::Live && config('radar.presentation_mask'),
        ]);
    }

    public function review(StoreClusterReviewRequest $request, CaseFile $case, Cluster $cluster): RedirectResponse
    {
        $cluster->reviews()->create([
            'user_id' => $request->user()->getKey(),
            'verdict' => $request->validated('verdict'),
            'note' => $request->validated('note'),
        ]);

        AuditLog::record('clusters.review', $request->user()->getKey(), $case->getKey(), $request->ip());

        return redirect()
            ->route('clusters.show', [$case, $cluster])
            ->with('status', 'Penilaian tersimpan dan tercatat di log audit.');
    }
}
