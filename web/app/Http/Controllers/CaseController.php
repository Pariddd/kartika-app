<?php

namespace App\Http\Controllers;

use App\Enums\CaseMode;
use App\Enums\CaseStatus;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Http\Requests\StoreCaseRequest;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Support\JobPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CaseController extends Controller
{
    public function index(Request $request): View
    {
        $cases = CaseFile::query()
            ->visibleTo($request->user())
            ->with('latestIssueScore')
            ->withCount([
                'posts',
                'clusters as flagged_clusters_count' => fn ($query) => $query->where('is_flagged', true),
            ])
            ->latest('updated_at')
            ->paginate(20);

        return view('cases.index', ['cases' => $cases]);
    }

    public function create(): View
    {
        return view('cases.create', [
            'xEnabled' => (bool) config('radar.sources.x_enabled'),
            'limits' => config('radar.collect'),
        ]);
    }

    /**
     * Kasus dan job collect dibuat dalam satu transaksi: tidak ada kasus tanpa job.
     */
    public function store(StoreCaseRequest $request): RedirectResponse
    {
        $user = $request->user();
        $params = $request->collectParams();

        $case = DB::transaction(function () use ($request, $user, $params): CaseFile {
            $case = CaseFile::create([
                'name' => $request->string('name')->trim()->toString(),
                'mode' => CaseMode::Live,
                'sources' => [$params['platform']],
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

        AuditLog::record('cases.store', $user->getKey(), $case->getKey(), $request->ip());

        return redirect()
            ->route('cases.show', $case)
            ->with('status', 'Kasus dibuat. Job pengumpulan data masuk antrean.');
    }

    public function show(CaseFile $case): View
    {
        $case->load([
            'creator',
            'latestIssueScore',
            'jobs' => fn ($query) => $query->latest('id'),
        ])->loadCount([
            'posts',
            'clusters as flagged_clusters_count' => fn ($query) => $query->where('is_flagged', true),
        ]);

        $isProcessing = $case->status->isActive()
            || $case->jobs->contains(fn ($job) => $job->status->isActive());

        return view('cases.show', [
            'case' => $case,
            'isProcessing' => $isProcessing,
        ]);
    }
}
