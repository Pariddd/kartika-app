<?php

namespace App\Http\Controllers;

use App\Enums\CaseMode;
use App\Enums\CaseStatus;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Http\Requests\StoreCaseRequest;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\User;
use App\Support\AccountNames;
use App\Support\AnswerKey;
use App\Support\CaseNetwork;
use App\Support\CaseOverview;
use App\Support\CaseTimeline;
use App\Support\JobPayload;
use App\Support\UploadLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        return view('cases.index', ['cases' => $cases, 'overview' => CaseOverview::for($request->user())]);
    }

    public function create(): View
    {
        return view('cases.create', [
            'importEnabled' => (bool) config('radar.sources.import_enabled'),
            'limits' => config('radar.collect'),
            'uploadLimit' => UploadLimit::label(),
            'uploadLimitedByPhp' => UploadLimit::limitedByPhp(),
        ]);
    }

    /**
     * Kasus dan job collect dibuat dalam satu transaksi: tidak ada kasus tanpa job.
     */
    public function store(StoreCaseRequest $request): RedirectResponse
    {
        $user = $request->user();

        // File ekspor Apify disimpan di storage privat dengan nama buatan server; worker menghapusnya setelah dibaca.
        $fileRef = null;
        if ($request->isImport()) {
            // storeAs menyalin lewat stream, jadi file besar tidak dimuat ke memori PHP.
            $fileRef = $request->file('import_file')->storeAs('uploads', Str::uuid()->toString().'.json', 'local');
        }
        $params = $request->collectParams($fileRef);

        try {
            $case = $this->createLiveCase($request, $user, $params);
        } catch (\Throwable $error) {
            if ($fileRef !== null) {
                Storage::disk('local')->delete($fileRef);
            }
            throw $error;
        }

        AuditLog::record('cases.store', $user->getKey(), $case->getKey(), $request->ip());

        return redirect()
            ->route('cases.show', $case)
            ->with('status', $fileRef ? 'Kasus dibuat. File Apify masuk antrean untuk diimpor.' : 'Kasus dibuat. Job pengumpulan data masuk antrean.');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function createLiveCase(StoreCaseRequest $request, User $user, array $params): CaseFile
    {
        return DB::transaction(function () use ($request, $user, $params): CaseFile {
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
    }

    public function show(CaseFile $case): View
    {
        return $this->renderShow($case, answer: null);
    }

    /**
     * Kunci jawaban baru dimuat saat diminta, dan hanya untuk kasus sintetis.
     */
    public function answer(CaseFile $case): View
    {
        abort_unless($case->mode === CaseMode::Demo, 404);

        return $this->renderShow($case, answer: AnswerKey::evaluate($case));
    }

    /**
     * @param  array<string, mixed>|null  $answer
     */
    private function renderShow(CaseFile $case, ?array $answer): View
    {
        $case->load([
            'creator',
            'latestIssueScore',
            'jobs' => fn ($query) => $query->latest('id'),
            'clusters' => fn ($query) => $query
                ->with(['signals', 'latestReview'])
                ->orderByDesc('is_flagged')
                ->orderBy('display_label'),
        ])->loadCount([
            'posts',
            'clusters as flagged_clusters_count' => fn ($query) => $query->where('is_flagged', true),
        ]);

        $isProcessing = $case->status->isActive()
            || $case->jobs->contains(fn ($job) => $job->status->isActive());

        $hasAnswerKey = $case->mode === CaseMode::Demo
            && DB::table('demo_ground_truth')->where('case_id', $case->getKey())->exists();

        // Nama akun di graf: syaratnya sama dengan halaman klaster, dan tercatat di log audit.
        $showNames = AccountNames::visibleTo(request()->user(), $case);
        $network = CaseNetwork::build($case, $showNames);
        if ($showNames && $network !== null) {
            AuditLog::record('cases.names', request()->user()->getKey(), $case->getKey(), request()->ip(), 200);
        }

        return view('cases.show', [
            'case' => $case,
            'showNames' => $showNames,
            'isProcessing' => $isProcessing,
            'flagged' => $case->clusters->where('is_flagged', true)->values(),
            'unflagged' => $case->clusters->where('is_flagged', false)->values(),
            'hasAnswerKey' => $hasAnswerKey,
            'answer' => $answer,
            'timeline' => CaseTimeline::build($case),
            'network' => $network,
        ]);
    }

    /**
     * Menghapus kasus beserta seluruh datanya (post, akun, klaster, penilaian) lewat ON DELETE CASCADE.
     * Ditolak selama worker masih memproses kasus ini.
     */
    public function destroy(Request $request, CaseFile $case): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Centang kotak konfirmasi untuk menghapus kasus.']);

        if ($case->jobs()->where('status', JobStatus::Running->value)->exists()) {
            return back()->withErrors(['confirm' => 'Kasus sedang diproses worker. Tunggu sampai selesai, lalu hapus.']);
        }

        // File unggahan (CSV atau ekspor Apify) yang belum sempat dibaca worker ikut dihapus.
        foreach ($case->jobs()->get() as $job) {
            $fileRef = $job->payload['params']['file_ref'] ?? null;
            if (is_string($fileRef) && preg_match('#^uploads/[0-9a-f-]{36}\.(csv|json)$#', $fileRef)) {
                Storage::disk('local')->delete($fileRef);
            }
        }

        $name = $case->displayName();
        $case->delete();

        // case_id sengaja null: barisnya sudah tidak ada. Jejak siapa dan kapan tetap tercatat.
        AuditLog::record('cases.destroy', $request->user()->getKey(), null, $request->ip(), 200);

        return redirect()->route('cases.index')->with('status', "Kasus \"{$name}\" dan seluruh datanya dihapus.");
    }
}
