@php use App\Support\Format; @endphp

<x-layouts.app :title="$case->displayName()">
    @if ($isProcessing)
        {{-- Muat ulang otomatis selama job masih berjalan, tanpa JavaScript. --}}
        @push('head')
            <meta http-equiv="refresh" content="10">
        @endpush
    @endif

    <nav aria-label="Breadcrumb" class="flex gap-2 text-sm text-muted">
        <a href="{{ route('cases.index') }}" class="text-organic hover:underline">Kasus</a>
        <span aria-hidden="true">/</span>
        <span>{{ $case->displayName() }}</span>
    </nav>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-2">
            <div class="flex flex-wrap gap-2">
                <span class="rounded-full bg-[#eef0ec] px-2.5 py-0.5 text-xs text-[#46504a]">{{ $case->mode->label() }}</span>
                @foreach ($case->sources ?? [] as $source)
                    @php $sourceLabel = \App\Enums\Platform::tryFrom($source)?->label() ?? $source; @endphp
                    @continue($sourceLabel === $case->mode->label())
                    <span class="rounded-full bg-[#eef0ec] px-2.5 py-0.5 text-xs text-[#46504a]">{{ $sourceLabel }}</span>
                @endforeach
                <x-status-badge :status="$case->status" />
            </div>
            <h1 class="text-3xl font-semibold">{{ $case->displayName() }}</h1>
            <p class="text-sm text-muted">
                Dibuat {{ Format::wib($case->created_at) }} WIB oleh {{ $case->creator?->name ?? 'pengguna terhapus' }}
            </p>
        </div>
        @if ($case->latestIssueScore)
            <a href="{{ route('cases.report', $case) }}" class="inline-flex min-h-11 items-center rounded-lg bg-brand px-5 font-medium text-white hover:bg-brand-dark">
                Ringkasan BLUF
            </a>
        @endif
    </div>

    @if (session('status'))
        <p role="status" class="mt-6 rounded-lg bg-info-soft px-4 py-3 text-sm text-info-ink">{{ session('status') }}</p>
    @endif

    @if ($case->latestIssueScore)
        @php
            $score = $case->latestIssueScore;
            $mistaken = $flagged->filter(fn ($c) => $c->latestReview?->verdict === \App\Enums\Verdict::Mistaken)->count();
        @endphp
        <section aria-label="Kesimpulan" class="mt-6 rounded-xl border border-line bg-white p-5">
            <span class="text-xs font-semibold tracking-wide text-muted">KESIMPULAN</span>
            <p class="mt-1.5 text-lg leading-relaxed">
                @if ($flagged->isEmpty())
                    Tidak ada klaster yang memenuhi syarat minimal 2 sinyal. Percakapan ini tampak organik.
                @else
                    {{ \App\Support\Format::percent(1 - $score->organic_score) }} percakapan
                    ({{ \App\Support\Format::number($score->coordinated_posts) }} dari {{ \App\Support\Format::number($score->total_posts) }} post)
                    berasal dari {{ $flagged->count() }} klaster yang ditandai terkoordinasi.
                    @if ($mistaken)
                        Analis menilai {{ $mistaken }} di antaranya keliru.
                    @endif
                @endif
            </p>
            <p class="mt-1 text-sm text-muted">Koordinasi tidak selalu berarti manipulasi. Periksa bukti tiap klaster sebelum menyimpulkan.</p>
        </section>
    @endif

    <section aria-label="Angka kasus" class="mt-6 grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-4">
        <div class="flex flex-col gap-1 rounded-xl border border-line bg-white px-5 py-4">
            <span class="text-xs text-muted">Post</span>
            <span class="text-2xl font-semibold">{{ Format::number($case->posts_count) }}</span>
        </div>
        <div class="flex flex-col gap-1 rounded-xl border border-line bg-white px-5 py-4">
            <span class="text-xs text-muted">Klaster ditandai</span>
            <span @class(['text-2xl font-semibold', 'text-coord-ink' => $case->flagged_clusters_count > 0])>{{ $case->flagged_clusters_count }}</span>
        </div>
        <div class="flex flex-col gap-2 rounded-xl border border-line bg-white px-5 py-4">
            <span class="text-xs text-muted">Skor keorganikan</span>
            <x-organic-bar :score="$case->latestIssueScore?->organic_score" />
        </div>
    </section>

    @if ($hasAnswerKey)
        <section aria-labelledby="judul-jawaban" class="mt-6 rounded-xl border-2 border-organic bg-white p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="judul-jawaban" class="text-lg font-semibold">Kunci jawaban</h2>
                    <p class="text-sm text-muted">Data ini sintetis. Bandingkan temuan sistem dengan klaster yang sengaja ditanam.</p>
                </div>
                @if ($answer === null)
                    <a href="{{ route('cases.answer', $case) }}" class="inline-flex min-h-11 items-center rounded-lg border border-[#b9bfb6] bg-white px-5 font-medium hover:bg-paper">Tampilkan jawaban</a>
                @else
                    <a href="{{ route('cases.show', $case) }}" class="inline-flex min-h-11 items-center rounded-lg border border-[#b9bfb6] bg-white px-5 hover:bg-paper">Sembunyikan jawaban</a>
                @endif
            </div>

            @if ($answer !== null)
                <div class="mt-4 grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-3">
                    <div class="rounded-lg bg-paper px-4 py-3"><span class="block text-xs text-muted">Klaster tanaman</span><span class="text-2xl font-semibold">{{ $answer['planted_count'] }}</span></div>
                    <div class="rounded-lg bg-paper px-4 py-3"><span class="block text-xs text-muted">Ditandai sistem</span><span class="text-2xl font-semibold">{{ $answer['flagged_count'] }}</span></div>
                    <div class="rounded-lg bg-paper px-4 py-3"><span class="block text-xs text-muted">Recall</span><span class="font-mono text-2xl font-semibold">{{ $answer['recall'] === null ? '–' : \App\Support\Format::percent($answer['recall']) }}</span></div>
                    <div class="rounded-lg bg-paper px-4 py-3"><span class="block text-xs text-muted">Precision</span><span class="font-mono text-2xl font-semibold">{{ $answer['precision'] === null ? '–' : \App\Support\Format::percent($answer['precision']) }}</span></div>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[520px] text-sm">
                        <thead><tr class="text-left text-xs text-muted"><th scope="col" class="py-2 pr-3 font-medium">Klaster sistem</th><th scope="col" class="px-3 py-2 font-medium">Akun</th><th scope="col" class="px-3 py-2 font-medium">Klaster tanaman</th><th scope="col" class="px-3 py-2 font-medium">Jaccard</th><th scope="col" class="py-2 pl-3 font-medium">Hasil</th></tr></thead>
                        <tbody>
                            @foreach ($answer['rows'] as $row)
                                <tr class="border-t border-[#e4e7e1]">
                                    <td class="py-2.5 pr-3 font-mono">{{ $row['cluster'] }}</td>
                                    <td class="px-3 py-2.5 font-mono">{{ $row['size'] }}</td>
                                    <td class="px-3 py-2.5">{{ $row['planted'] ?? 'Tidak ada' }}</td>
                                    <td class="px-3 py-2.5 font-mono">{{ $row['jaccard'] === null ? '–' : number_format($row['jaccard'], 2, ',', '') }}</td>
                                    <td @class(['py-2.5 pl-3', 'font-medium text-coord-ink' => $row['planted'] === null])>{{ $row['planted'] ? 'Tertangkap' : 'Salah tanda' }}</td>
                                </tr>
                            @endforeach
                            @foreach ($answer['missed'] as $label)
                                <tr class="border-t border-[#e4e7e1]"><td class="py-2.5 pr-3 text-muted">–</td><td class="px-3 py-2.5">–</td><td class="px-3 py-2.5">{{ $label }}</td><td class="px-3 py-2.5">–</td><td class="py-2.5 pl-3 font-medium text-coord-ink">Terlewat</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-muted">Cocok bila Jaccard anggota minimal 0,5. Angka ini hanya berlaku untuk dataset sintetis ini, bukan klaim akurasi umum.</p>
            @endif
        </section>
    @endif

    @if ($case->clusters->isEmpty())
        <section class="mt-6 rounded-xl border border-dashed border-[#b9bfb6] px-6 py-10 text-center">
            <h2 class="font-semibold">Hasil analisis</h2>
            <p class="mt-1 text-sm text-muted">
                {{ $isProcessing ? 'Klaster dan bukti muncul di sini setelah job analisis selesai.' : 'Analisis tidak menemukan kelompok akun yang saling terhubung.' }}
            </p>
        </section>
    @else
        @foreach ([['Klaster ditandai', $flagged, true], ['Klaster tidak ditandai', $unflagged, false]] as [$title, $list, $isFlaggedList])
            @continue($list->isEmpty())
            @if (! $isFlaggedList) <details class="mt-6"> <summary class="cursor-pointer text-sm font-medium text-organic">{{ $title }} ({{ $list->count() }}): kurang dari 2 sinyal aktif</summary> @endif
            <section aria-label="{{ $title }}" class="{{ $isFlaggedList ? 'mt-6' : 'mt-3' }} rounded-xl border border-line bg-white">
                @if ($isFlaggedList)
                    <div class="border-b border-[#e4e7e1] px-5 py-4"><h2 class="text-lg font-semibold">{{ $title }}</h2></div>
                @endif
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead>
                            <tr class="text-left text-xs text-muted">
                                <th scope="col" class="px-5 py-3 font-medium">Klaster</th>
                                <th scope="col" class="px-3 py-3 text-right font-medium">Akun</th>
                                <th scope="col" class="px-3 py-3 font-medium">Sinyal aktif</th>
                                <th scope="col" class="px-3 py-3 text-right font-medium">Skor</th>
                                <th scope="col" class="px-3 py-3 font-medium">Penilaian analis</th>
                                <th scope="col" class="px-5 py-3 font-medium"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($list as $cluster)
                                <tr class="border-t border-[#e4e7e1]">
                                    <td class="px-5 py-3 font-mono font-medium">{{ $cluster->display_label }}</td>
                                    <td class="px-3 py-3 text-right font-mono">{{ $cluster->size }}</td>
                                    <td class="px-3 py-3"><x-signal-chips :signals="$cluster->signals" /></td>
                                    <td class="px-3 py-3 text-right font-mono">{{ number_format($cluster->coordination_score, 2, ',', '') }}</td>
                                    <td class="px-3 py-3 {{ $cluster->latestReview ? '' : 'text-muted' }}">{{ $cluster->latestReview?->verdict->label() ?? 'Belum dinilai' }}</td>
                                    <td class="px-5 py-3"><a href="{{ route('clusters.show', [$case, $cluster]) }}" class="font-medium text-organic hover:underline">Lihat bukti</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
            @if (! $isFlaggedList) </details> @endif
        @endforeach
    @endif

    <details class="mt-6">
        <summary class="cursor-pointer text-sm font-medium text-organic">Antrean pemrosesan dan parameter</summary>
    @if (is_array($case->query))
        <section aria-labelledby="judul-parameter" class="mt-6 rounded-xl border border-line bg-white p-5">
            <h2 id="judul-parameter" class="text-lg font-semibold">Parameter pengumpulan</h2>
            <dl class="mt-3 grid grid-cols-[max-content_1fr] gap-x-6 gap-y-2 text-sm">
                @if (! empty($case->query['video_ids']))
                    <dt class="text-muted">Video</dt>
                    <dd class="flex flex-wrap gap-1.5">
                        @foreach ($case->query['video_ids'] as $videoId)
                            <span class="rounded bg-paper px-1.5 py-0.5 font-mono text-xs">{{ $videoId }}</span>
                        @endforeach
                    </dd>
                @endif
                @if (! empty($case->query['query']))
                    <dt class="text-muted">Kata kunci</dt>
                    <dd>{{ $case->query['query'] }}</dd>
                @endif
                @if (! empty($case->query['since']) || ! empty($case->query['until']))
                    <dt class="text-muted">Rentang</dt>
                    <dd>{{ $case->query['since'] ?? '…' }} sampai {{ $case->query['until'] ?? '…' }}</dd>
                @endif
                @isset($case->query['max_items'])
                    <dt class="text-muted">Maksimal post</dt>
                    <dd class="font-mono">{{ Format::number($case->query['max_items']) }}</dd>
                @endisset
            </dl>
        </section>
    @endif

    <section aria-labelledby="judul-job" class="mt-6 rounded-xl border border-line bg-white">
        <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-[#e4e7e1] px-5 py-4">
            <h2 id="judul-job" class="text-lg font-semibold">Antrean pemrosesan</h2>
            @if ($isProcessing)
                <span class="text-xs text-muted">Halaman dimuat ulang otomatis setiap 10 detik</span>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead>
                    <tr class="text-left text-xs text-muted">
                        <th scope="col" class="px-5 py-3 font-medium">Job</th>
                        <th scope="col" class="px-3 py-3 font-medium">Status</th>
                        <th scope="col" class="px-3 py-3 text-right font-medium">Percobaan</th>
                        <th scope="col" class="px-3 py-3 font-medium">Dibuat</th>
                        <th scope="col" class="px-5 py-3 font-medium">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($case->jobs as $job)
                        <tr class="border-t border-[#e4e7e1] align-top">
                            <td class="px-5 py-3">{{ $job->job_type->label() }} <span class="font-mono text-xs text-muted">#{{ $job->id }}</span></td>
                            <td class="px-3 py-3"><x-status-badge :status="$job->status" /></td>
                            <td class="px-3 py-3 text-right font-mono">{{ $job->attempts }}</td>
                            <td class="px-3 py-3 text-muted">{{ Format::wib($job->created_at) }}</td>
                            <td class="px-5 py-3 text-muted">
                                @if ($job->error)
                                    <span class="text-coord-ink">{{ $job->error }}</span>
                                @elseif (! empty($job->result_summary['warnings']))
                                    {{ implode('; ', $job->result_summary['warnings']) }}
                                @elseif ($job->status === \App\Enums\JobStatus::Pending)
                                    Menunggu worker mengambil job.
                                @else
                                    –
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-6 text-center text-muted">Belum ada job.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    </details>
</x-layouts.app>
