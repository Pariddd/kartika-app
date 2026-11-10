<x-layouts.app title="Kasus">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex max-w-3xl flex-col gap-1.5">
            <h1 class="text-3xl font-semibold">Kasus pemantauan</h1>
            <p class="text-[15px] leading-relaxed text-muted">
                Radar Narasi menandai koordinasi: akun yang bergerak bersama secara tidak wajar.
                Sistem menyajikan bukti; keputusan tetap di tangan analis.
            </p>
        </div>

        @can('collect-data')
            <a href="{{ route('cases.create') }}" class="inline-flex min-h-11 items-center rounded-lg bg-brand px-5 font-medium text-white hover:bg-brand-dark">
                Kasus baru
            </a>
        @endcan
    </div>

    @if (session('status'))
        <p role="status" class="mt-6 rounded-lg bg-info-soft px-4 py-3 text-sm text-info-ink">{{ session('status') }}</p>
    @endif

    @if ($cases->isEmpty())
        <section class="mt-8 rounded-xl border border-line bg-white px-6 py-12 text-center">
            <h2 class="text-lg font-semibold">Belum ada kasus</h2>
            <p class="mt-1 text-sm text-muted">Kasus demo dan unggahan CSV akan muncul di sini.</p>
        </section>
    @else
        <section aria-labelledby="judul-tabel" class="mt-8 rounded-xl border border-line bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#e4e7e1] px-5 py-4">
                <h2 id="judul-tabel" class="text-lg font-semibold">Semua kasus</h2>
                <div class="flex gap-4 text-xs text-muted">
                    <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-sm bg-organic"></span>Organik</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-sm bg-coord"></span>Dari klaster terkoordinasi</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-sm">
                    <thead>
                        <tr class="text-left text-xs text-muted">
                            <th scope="col" class="px-5 py-3 font-medium">Kasus</th>
                            <th scope="col" class="px-3 py-3 font-medium">Sumber</th>
                            <th scope="col" class="px-3 py-3 text-right font-medium">Post</th>
                            <th scope="col" class="w-56 px-3 py-3 font-medium">Skor keorganikan</th>
                            <th scope="col" class="px-3 py-3 text-right font-medium">Klaster ditandai</th>
                            <th scope="col" class="px-3 py-3 font-medium">Status</th>
                            <th scope="col" class="px-5 py-3 font-medium">Diperbarui</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cases as $case)
                            <tr class="border-t border-[#e4e7e1]">
                                <td class="px-5 py-3.5">
                                    <a href="{{ route('cases.show', $case) }}" class="font-semibold text-ink hover:underline">{{ $case->displayName() }}</a>
                                    <div class="mt-0.5 text-xs text-muted">{{ $case->mode->label() }}</div>
                                </td>
                                <td class="px-3 py-3.5">
                                    @foreach ($case->sources ?? [] as $source)
                                        <span class="rounded-full bg-[#eef0ec] px-2.5 py-0.5 text-xs text-[#46504a]">{{ \App\Enums\Platform::tryFrom($source)?->label() ?? $source }}</span>
                                    @endforeach
                                </td>
                                <td class="px-3 py-3.5 text-right font-mono">{{ \App\Support\Format::number($case->posts_count) }}</td>
                                <td class="px-3 py-3.5"><x-organic-bar :score="$case->latestIssueScore?->organic_score" /></td>
                                <td @class(['px-3 py-3.5 text-right font-mono', 'font-medium text-coord-ink' => $case->flagged_clusters_count > 0])>
                                    {{ $case->flagged_clusters_count }}
                                </td>
                                <td class="px-3 py-3.5"><x-status-badge :status="$case->status" /></td>
                                <td class="px-5 py-3.5 text-muted">{{ \App\Support\Format::wib($case->updated_at) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="border-t border-[#e4e7e1] px-5 py-3.5 text-xs leading-relaxed text-muted">
                Skor keorganikan = porsi percakapan yang tidak berasal dari klaster terkoordinasi.
                Koordinasi tidak selalu berarti manipulasi: komunitas atau gerakan sah juga bisa bergerak bersama.
            </p>
        </section>

        <div class="mt-6">{{ $cases->links() }}</div>
    @endif
</x-layouts.app>
