<x-layouts.app title="Kasus">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex max-w-3xl flex-col gap-1.5">
            <h1 class="text-3xl font-semibold">Kasus pemantauan</h1>
            <p class="text-[15px] leading-relaxed text-muted">
                SyncTrace menandai koordinasi: akun yang bergerak bersama secara tidak wajar.
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

    @if ($overview['guide'])
        @php $guide = $overview['guide']; $guideCluster = $guide->clusters()->where('is_flagged', true)->orderBy('display_label')->first(); @endphp
        <section aria-labelledby="judul-panduan" class="mt-8 rounded-xl border border-info-line bg-info-soft p-5">
            <h2 id="judul-panduan" class="text-lg font-semibold text-info-ink">Mulai di sini</h2>
            <p class="mt-1 max-w-3xl text-sm leading-relaxed text-info-ink">
                Empat langkah untuk menilai sistem ini dalam lima menit. Semua data di akun demo adalah data sintetis.
            </p>
            <ol class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <li class="flex flex-col gap-2 rounded-lg bg-white p-4">
                    <span class="font-mono text-xs text-muted">Langkah 1</span>
                    <span class="font-semibold">Buka kasus contoh</span>
                    <span class="text-sm leading-relaxed text-muted">Lihat skor keorganikan, lini waktu, dan graf koordinasi.</span>
                    <a href="{{ route('cases.show', $guide) }}" class="mt-auto inline-flex min-h-11 items-center font-medium text-organic hover:underline">Buka {{ $guide->displayName() }}</a>
                </li>
                <li class="flex flex-col gap-2 rounded-lg bg-white p-4">
                    <span class="font-mono text-xs text-muted">Langkah 2</span>
                    <span class="font-semibold">Periksa bukti satu klaster</span>
                    <span class="text-sm leading-relaxed text-muted">Empat sinyal, pasangan post yang mirip, dan narasi utama.</span>
                    @if ($guideCluster)
                        <a href="{{ route('clusters.show', [$guide, $guideCluster]) }}" class="mt-auto inline-flex min-h-11 items-center font-medium text-organic hover:underline">Buka klaster {{ $guideCluster->display_label }}</a>
                    @endif
                </li>
                <li class="flex flex-col gap-2 rounded-lg bg-white p-4">
                    <span class="font-mono text-xs text-muted">Langkah 3</span>
                    <span class="font-semibold">Cocokkan dengan kunci jawaban</span>
                    <span class="text-sm leading-relaxed text-muted">Klaster tanaman diketahui, jadi hasil deteksi bisa diperiksa.</span>
                    <a href="{{ route('cases.answer', $guide) }}" class="mt-auto inline-flex min-h-11 items-center font-medium text-organic hover:underline">Tampilkan jawaban</a>
                </li>
                <li class="flex flex-col gap-2 rounded-lg bg-white p-4">
                    <span class="font-mono text-xs text-muted">Langkah 4</span>
                    <span class="font-semibold">Uji dengan data Anda sendiri</span>
                    <span class="text-sm leading-relaxed text-muted">Unggah CSV, lalu lihat batas sistem di halaman Evaluasi.</span>
                    <span class="mt-auto flex flex-wrap gap-x-4">
                        <a href="{{ route('upload.create') }}" class="inline-flex min-h-11 items-center font-medium text-organic hover:underline">Coba Sendiri</a>
                        <a href="{{ route('evaluation.show') }}" class="inline-flex min-h-11 items-center font-medium text-organic hover:underline">Evaluasi</a>
                    </span>
                </li>
            </ol>
        </section>
    @endif

    @if ($overview['cases'] > 0)
        <dl class="mt-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-line bg-white px-5 py-4">
                <dt class="text-sm text-muted">Kasus</dt>
                <dd class="mt-1 font-mono text-3xl font-semibold">{{ \App\Support\Format::number($overview['cases']) }}</dd>
            </div>
            <div class="rounded-xl border border-line bg-white px-5 py-4">
                <dt class="text-sm text-muted">Klaster ditandai</dt>
                <dd class="mt-1 font-mono text-3xl font-semibold">{{ \App\Support\Format::number($overview['flagged']) }}</dd>
            </div>
            <div class="rounded-xl border border-line bg-white px-5 py-4">
                <dt class="text-sm text-muted">Menunggu penilaian analis</dt>
                <dd @class(['mt-1 font-mono text-3xl font-semibold', 'text-coord-ink' => $overview['pending'] > 0])>{{ \App\Support\Format::number($overview['pending']) }}</dd>
            </div>
            <div class="rounded-xl border border-line bg-white px-5 py-4">
                <dt class="text-sm text-muted">Koordinasi tertinggi</dt>
                <dd class="mt-1">
                    @if ($overview['top'])
                        <a href="{{ route('cases.show', $overview['top']) }}" class="font-semibold text-ink hover:underline">{{ $overview['top']->displayName() }}</a>
                        <span class="block text-sm text-muted">{{ \App\Support\Format::percent(1 - $overview['top']->latestIssueScore->organic_score) }} post dari klaster ditandai</span>
                    @else
                        <span class="text-sm text-muted">Belum ada klaster ditandai</span>
                    @endif
                </dd>
            </div>
        </dl>
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
