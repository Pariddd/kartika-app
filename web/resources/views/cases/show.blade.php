@php use App\Support\Format; @endphp

<x-layouts.app :title="$case->name">
    @if ($isProcessing)
        {{-- Muat ulang otomatis selama job masih berjalan, tanpa JavaScript. --}}
        @push('head')
            <meta http-equiv="refresh" content="10">
        @endpush
    @endif

    <nav aria-label="Breadcrumb" class="flex gap-2 text-sm text-muted">
        <a href="{{ route('cases.index') }}" class="text-organic hover:underline">Kasus</a>
        <span aria-hidden="true">/</span>
        <span>{{ $case->name }}</span>
    </nav>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-2">
            <div class="flex flex-wrap gap-2">
                <span class="rounded-full bg-[#eef0ec] px-2.5 py-0.5 text-xs text-[#46504a]">{{ $case->mode->label() }}</span>
                @foreach ($case->sources ?? [] as $source)
                    <span class="rounded-full bg-[#eef0ec] px-2.5 py-0.5 text-xs text-[#46504a]">{{ \App\Enums\Platform::tryFrom($source)?->label() ?? $source }}</span>
                @endforeach
                <x-status-badge :status="$case->status" />
            </div>
            <h1 class="text-3xl font-semibold">{{ $case->name }}</h1>
            <p class="text-sm text-muted">
                Dibuat {{ Format::wib($case->created_at) }} WIB oleh {{ $case->creator?->name ?? 'pengguna terhapus' }}
            </p>
        </div>
    </div>

    @if (session('status'))
        <p role="status" class="mt-6 rounded-lg bg-info-soft px-4 py-3 text-sm text-info-ink">{{ session('status') }}</p>
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

    <section class="mt-6 rounded-xl border border-dashed border-[#b9bfb6] px-6 py-10 text-center">
        <h2 class="font-semibold">Hasil analisis</h2>
        <p class="mt-1 text-sm text-muted">Klaster, graf, dan bukti muncul di sini setelah job analisis selesai.</p>
    </section>
</x-layouts.app>
