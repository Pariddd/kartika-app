<?php

namespace App\Support;

use App\Enums\CaseMode;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\SignalType;
use App\Enums\Verdict;
use App\Models\AnalysisJob;
use App\Models\CaseFile;
use App\Models\Cluster;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ringkasan BLUF (bottom line up front) satu kasus: kesimpulan di kalimat pertama,
 * lalu bukti, status penilaian analis, saran tindak lanjut, dan keterbatasan.
 *
 * Semua kalimat dibangun dari angka hasil analisis dengan aturan tetap, bukan model bahasa,
 * sehingga setiap klaim bisa ditelusuri ke halaman bukti. Satu-satunya teks post yang dimuat adalah
 * narasi utama tiap klaster (post asli, mengikuti penyamaran konten dan mode presentasi). Tidak memuat domain.
 */
final class CaseReport
{
    /**
     * Batas tampilan untuk porsi post dari klaster ditandai. Ini batas penyajian,
     * bukan ambang deteksi dan bukan hasil kalibrasi.
     */
    public const BANDS = [
        [0.30, 'Koordinasi dominan'],
        [0.10, 'Koordinasi signifikan'],
        [0.0, 'Koordinasi terbatas'],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function build(CaseFile $case): array
    {
        $case->loadMissing(['creator', 'latestIssueScore']);

        $score = $case->latestIssueScore;
        $flagged = $case->clusters()
            ->where('is_flagged', true)
            ->with(['signals', 'latestReview.user'])
            ->orderByDesc('coordination_score')
            ->orderBy('display_label')
            ->get();
        $analysis = self::latestJob($case, [JobType::Analyze, JobType::AnalyzeCsv]);
        $collect = self::latestJob($case, [JobType::Collect]);
        $postsByCluster = self::postsByCluster($flagged);
        $narratives = self::narratives($case, $flagged);

        $period = DB::table('posts')->where('case_id', $case->getKey())
            ->selectRaw('min(posted_at) as first_at, max(posted_at) as last_at, count(*) as posts, count(distinct account_id) as accounts')
            ->first();

        $reviewed = $flagged->filter(fn (Cluster $c) => $c->latestReview !== null);
        $verdicts = collect(Verdict::cases())->mapWithKeys(fn (Verdict $v) => [
            $v->value => $reviewed->filter(fn (Cluster $c) => $c->latestReview->verdict === $v)->count(),
        ]);

        return [
            'ready' => $score !== null,
            'headline' => $score ? self::headline($score->organic_score, $score->coordinated_posts, $score->total_posts, $flagged) : null,
            'band' => $score && $flagged->isNotEmpty() ? self::band(1 - $score->organic_score) : null,
            'score' => $score,
            'period' => [
                'first' => $period?->first_at ? Carbon::parse($period->first_at) : null,
                'last' => $period?->last_at ? Carbon::parse($period->last_at) : null,
                'posts' => (int) ($period->posts ?? 0),
                'accounts' => (int) ($period->accounts ?? 0),
            ],
            'clusters' => $flagged->map(fn (Cluster $c) => [
                'model' => $c,
                'posts' => $postsByCluster[$c->id] ?? 0,
                'narrative' => $narratives[$c->id] ?? null,
                'active' => $c->signals->where('active', true)->sortBy(fn ($s) => array_search($s->signal, SignalType::ordered(), true))->values(),
            ]),
            'flagged_accounts' => $flagged->sum('size'),
            'review_sentence' => self::reviewSentence($reviewed->count(), $flagged->count(), $verdicts->all()),
            'review' => [
                'reviewed' => $reviewed->count(),
                'total' => $flagged->count(),
                'verdicts' => $verdicts,
                'unreviewed_labels' => $flagged->reject(fn (Cluster $c) => $c->latestReview !== null)->pluck('display_label')->all(),
            ],
            'recommendations' => self::recommendations($flagged),
            'limitations' => self::limitations($case, $analysis, $collect),
            'params' => $analysis?->payload['params'] ?? [],
            'analysis_at' => $score?->computed_at,
        ];
    }

    /**
     * @param  Collection<int, Cluster>  $flagged
     */
    public static function headline(float $organic, int $coordinatedPosts, int $totalPosts, Collection $flagged): string
    {
        if ($totalPosts === 0) {
            return 'Tidak ada post untuk dianalisis pada kasus ini.';
        }

        if ($flagged->isEmpty()) {
            return 'Tidak ditemukan koordinasi: tidak ada klaster akun yang memenuhi syarat minimal 2 sinyal. '
                .'Percakapan pada sampel ini tampak organik.';
        }

        return sprintf(
            '%s percakapan (%s dari %s post) berasal dari %d klaster akun yang bergerak terkoordinasi, berisi %s akun.',
            SignalExplainer::pct(1 - $organic),
            Format::number($coordinatedPosts),
            Format::number($totalPosts),
            $flagged->count(),
            Format::number($flagged->sum('size')),
        );
    }

    /**
     * @param  array<string, int>  $verdicts
     */
    public static function reviewSentence(int $reviewed, int $total, array $verdicts): ?string
    {
        if ($total === 0) {
            return null;
        }

        $sentence = "Penilaian analis: {$reviewed} dari {$total} klaster sudah dinilai";
        if ($reviewed > 0) {
            $sentence .= sprintf(
                ' (%d terkoordinasi, %d keliru, %d belum pasti)',
                $verdicts[Verdict::Coordinated->value] ?? 0,
                $verdicts[Verdict::Mistaken->value] ?? 0,
                $verdicts[Verdict::Unsure->value] ?? 0,
            );
        }

        return $sentence.'.';
    }

    public static function band(float $share): string
    {
        foreach (self::BANDS as [$min, $label]) {
            if ($share >= $min) {
                return $label;
            }
        }

        return self::BANDS[array_key_last(self::BANDS)][1];
    }

    /**
     * @param  Collection<int, Cluster>  $flagged
     * @return list<string>
     */
    private static function recommendations(Collection $flagged): array
    {
        if ($flagged->isEmpty()) {
            return [
                'Tidak perlu tindak lanjut khusus. Ulangi analisis bila volume percakapan naik tajam atau muncul tagar baru.',
            ];
        }

        $labels = fn (callable $filter) => $flagged->filter($filter)->pluck('display_label')->implode(', ');
        // Saran berbasis sinyal hanya untuk klaster yang tidak dinilai keliru.
        $kept = $flagged->reject(fn (Cluster $c) => $c->latestReview?->verdict === Verdict::Mistaken);
        $keptLabels = fn (callable $filter) => $kept->filter($filter)->pluck('display_label')->implode(', ');
        $withSignal = fn (SignalType $type) => fn (Cluster $c) => $c->signals->contains(fn ($s) => $s->signal === $type && $s->active);

        $items = [];

        $unreviewed = $labels(fn (Cluster $c) => $c->latestReview === null);
        if ($unreviewed !== '') {
            $items[] = "Periksa bukti dan beri penilaian untuk klaster {$unreviewed} sebelum ringkasan ini diteruskan.";
        }

        $mistaken = $labels(fn (Cluster $c) => $c->latestReview?->verdict === Verdict::Mistaken);
        if ($mistaken !== '') {
            $items[] = "Klaster {$mistaken} dinilai keliru oleh analis; jangan masukkan ke kesimpulan akhir.";
        }

        $infra = $keptLabels($withSignal(SignalType::Infra));
        if ($infra !== '') {
            $items[] = "Telusuri isi dan umur domain yang dibagikan klaster {$infra} (OSINT konten), bukan identitas pemilik akun.";
        }

        $time = $keptLabels($withSignal(SignalType::Time));
        if ($time !== '') {
            $items[] = "Pantau pola jam serentak klaster {$time} untuk mengenali gelombang berikutnya lebih awal.";
        }

        $text = $keptLabels($withSignal(SignalType::Text));
        if ($text !== '') {
            $items[] = "Catat narasi yang diulang klaster {$text} sebagai bahan klarifikasi bagi pihak berwenang.";
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private static function limitations(CaseFile $case, ?AnalysisJob $analysis, ?AnalysisJob $collect): array
    {
        $items = [
            'Koordinasi tidak selalu berarti manipulasi: komunitas, kepanitiaan, atau pengumuman resmi juga membuat akun bergerak bersama.',
            'Hasil hanya berlaku untuk sampel yang dikumpulkan, bukan seluruh percakapan di platform.',
            'Ambang deteksi dikalibrasi pada data sintetis; tidak ada klaim akurasi untuk data dunia nyata.',
        ];

        if ($case->mode === CaseMode::Demo) {
            array_unshift($items, 'Kasus ini memakai data sintetis untuk demonstrasi.');
        }

        foreach ([$collect, $analysis] as $job) {
            foreach ($job?->result_summary['warnings'] ?? [] as $warning) {
                $items[] = ucfirst((string) $warning).'.';
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * Narasi teratas tiap klaster ditandai: teks post wakilnya (dipotong) dan berapa akun yang menulisnya.
     *
     * @param  Collection<int, Cluster>  $flagged
     * @return array<int, array{text: string, accounts: int, share: float}>
     */
    private static function narratives(CaseFile $case, Collection $flagged): array
    {
        $top = [];
        foreach ($flagged as $cluster) {
            $evidence = $cluster->signals->first(fn ($s) => $s->signal === SignalType::Text)?->evidence ?? [];
            if (! empty($evidence['narratives'][0]['post'])) {
                $top[$cluster->id] = $evidence['narratives'][0];
            }
        }
        if ($top === []) {
            return [];
        }

        $texts = DB::table('posts')
            ->where('case_id', $case->getKey())
            ->whereIn('id', array_column($top, 'post'))
            ->pluck('text', 'id');
        $masked = $case->mode === CaseMode::Live && config('radar.presentation_mask');

        $result = [];
        foreach ($top as $clusterId => $narrative) {
            if (! isset($texts[$narrative['post']])) {
                continue;
            }
            $result[$clusterId] = [
                'text' => $masked ? '[teks disamarkan · mode presentasi]' : ContentMask::display(Str::limit($texts[$narrative['post']], 220)),
                'accounts' => (int) $narrative['accounts'],
                'share' => (float) $narrative['share'],
            ];
        }

        return $result;
    }

    /**
     * @param  list<JobType>  $types
     */
    private static function latestJob(CaseFile $case, array $types): ?AnalysisJob
    {
        return $case->jobs()
            ->whereIn('job_type', array_map(fn (JobType $t) => $t->value, $types))
            ->where('status', JobStatus::Done->value)
            ->latest('id')
            ->first();
    }

    /**
     * Jumlah post per klaster ditandai, dihitung dari anggota klaster.
     *
     * @param  Collection<int, Cluster>  $flagged
     * @return array<int, int>
     */
    private static function postsByCluster(Collection $flagged): array
    {
        if ($flagged->isEmpty()) {
            return [];
        }

        return DB::table('cluster_members')
            ->join('posts', 'posts.account_id', '=', 'cluster_members.account_id')
            ->whereIn('cluster_members.cluster_id', $flagged->pluck('id'))
            ->groupBy('cluster_members.cluster_id')
            ->selectRaw('cluster_members.cluster_id as cluster_id, count(posts.id) as posts')
            ->pluck('posts', 'cluster_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
