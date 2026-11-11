@php
    use App\Support\Format;
    use App\Support\SignalExplainer;
    $r = $report;
@endphp

<x-layouts.app :title="'Ringkasan · '.$case->displayName()">
    <nav aria-label="Breadcrumb" class="flex flex-wrap gap-2 text-sm text-muted print:hidden">
        <a href="{{ route('cases.index') }}" class="text-organic hover:underline">Kasus</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('cases.show', $case) }}" class="text-organic hover:underline">{{ $case->displayName() }}</a>
        <span aria-hidden="true">/</span>
        <span>Ringkasan</span>
    </nav>

    <article class="mx-auto mt-4 max-w-4xl rounded-xl border border-line bg-white p-5 sm:p-8 print:mt-0 print:max-w-none print:border-0 print:p-0">
        <header class="flex flex-wrap items-start justify-between gap-4 border-b border-[#e4e7e1] pb-5">
            <div class="flex flex-col gap-1">
                <span class="text-xs font-semibold tracking-wide text-muted">RINGKASAN KASUS · DOKUMEN KERJA ANALIS</span>
                <h1 class="text-2xl font-semibold">{{ $case->displayName() }}</h1>
                <p class="text-sm text-muted">
                    {{ $case->mode->label() }}
                    @if ($r['period']['first'])
                        · data {{ Format::wib($r['period']['first']) }} – {{ Format::wib($r['period']['last']) }} WIB
                    @endif
                    · {{ Format::number($r['period']['posts']) }} post dari {{ Format::number($r['period']['accounts']) }} akun
                </p>
            </div>
            <button type="button" data-print class="inline-flex min-h-11 items-center rounded-lg border border-[#b9bfb6] bg-white px-5 font-medium hover:bg-paper print:hidden">
                Cetak / simpan PDF
            </button>
        </header>

        @if (! $r['ready'])
            <p class="mt-6 rounded-lg bg-info-soft px-4 py-3 text-sm text-info-ink">Analisis kasus ini belum selesai. Ringkasan tersedia setelah job analisis berstatus selesai.</p>
        @else
            <section aria-labelledby="judul-bluf" class="mt-6">
                <h2 id="judul-bluf" class="text-xs font-semibold tracking-wide text-muted">KESIMPULAN</h2>
                @if ($r['band'])
                    <p class="mt-2"><span class="rounded-full bg-coord-soft px-3 py-1 text-sm font-semibold text-coord-ink print:border print:border-coord">{{ $r['band'] }}</span></p>
                @else
                    <p class="mt-2"><span class="rounded-full bg-info-soft px-3 py-1 text-sm font-semibold text-info-ink">Tidak ada koordinasi terdeteksi</span></p>
                @endif
                <p class="mt-3 text-xl leading-relaxed">{{ $r['headline'] }}</p>
                @if ($r['review_sentence'])
                    <p class="mt-2 text-[15px] text-[#2f3833]">{{ $r['review_sentence'] }}</p>
                @endif
                <p class="mt-2 text-sm text-muted">Skor keorganikan {{ number_format($r['score']->organic_score, 2, ',', '') }}: porsi post yang tidak berasal dari klaster ditandai.</p>
            </section>

            @if ($r['clusters']->isNotEmpty())
                <section aria-labelledby="judul-temuan" class="mt-8">
                    <h2 id="judul-temuan" class="text-lg font-semibold">Temuan dan bukti utama</h2>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full min-w-[560px] text-sm">
                            <thead>
                                <tr class="text-left text-xs text-muted">
                                    <th scope="col" class="py-2 pr-3 font-medium">Klaster</th>
                                    <th scope="col" class="px-3 py-2 text-right font-medium">Akun</th>
                                    <th scope="col" class="px-3 py-2 text-right font-medium">Post</th>
                                    <th scope="col" class="px-3 py-2 text-right font-medium">Skor</th>
                                    <th scope="col" class="px-3 py-2 font-medium">Sinyal</th>
                                    <th scope="col" class="py-2 pl-3 font-medium">Penilaian</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($r['clusters'] as $row)
                                    @php $cluster = $row['model']; @endphp
                                    <tr class="border-t border-[#e4e7e1] align-top">
                                        <td class="py-2.5 pr-3 font-mono"><a href="{{ route('clusters.show', [$case, $cluster]) }}" class="text-organic hover:underline print:text-ink print:no-underline">{{ $cluster->display_label }}</a></td>
                                        <td class="px-3 py-2.5 text-right font-mono">{{ Format::number($cluster->size) }}</td>
                                        <td class="px-3 py-2.5 text-right font-mono">{{ Format::number($row['posts']) }}</td>
                                        <td class="px-3 py-2.5 text-right font-mono">{{ number_format($cluster->coordination_score, 2, ',', '') }}</td>
                                        <td class="px-3 py-2.5"><x-signal-chips :signals="$cluster->signals" /></td>
                                        <td class="py-2.5 pl-3">{{ $cluster->latestReview?->verdict->label() ?? 'Belum dinilai' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-5 flex flex-col gap-4">
                        @foreach ($r['clusters'] as $row)
                            <div class="break-inside-avoid">
                                <h3 class="font-semibold">Klaster <span class="font-mono">{{ $row['model']->display_label }}</span> · {{ $row['active']->count() }} sinyal aktif</h3>
                                @if ($row['narrative'])
                                    <p class="mt-1.5 text-[15px] leading-relaxed text-[#2f3833]">
                                        <strong class="font-medium">Narasi utama</strong> (ditulis dalam bentuk mirip oleh {{ $row['narrative']['accounts'] }} dari {{ $row['model']->size }} akun):
                                        <span class="italic">“{{ $row['narrative']['text'] }}”</span>
                                    </p>
                                @endif
                                <ul class="mt-1.5 list-disc pl-5 text-[15px] leading-relaxed text-[#2f3833]">
                                    @foreach ($row['active'] as $signal)
                                        <li><strong class="font-medium">{{ $signal->signal->label() }}:</strong> {{ SignalExplainer::sentence($signal) }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section aria-labelledby="judul-saran" class="mt-8 break-inside-avoid">
                <h2 id="judul-saran" class="text-lg font-semibold">Saran tindak lanjut</h2>
                <ul class="mt-2 list-disc pl-5 text-[15px] leading-relaxed">
                    @foreach ($r['recommendations'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
                <p class="mt-2 text-sm text-muted">Sistem tidak mengambil tindakan otomatis. Keputusan tindak lanjut ada pada analis dan pimpinan.</p>
            </section>

            <section aria-labelledby="judul-batas" class="mt-8 break-inside-avoid">
                <h2 id="judul-batas" class="text-lg font-semibold">Keterbatasan</h2>
                <ul class="mt-2 list-disc pl-5 text-[15px] leading-relaxed text-[#2f3833]">
                    @foreach ($r['limitations'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </section>

            <section aria-labelledby="judul-metode" class="mt-8 break-inside-avoid rounded-lg bg-paper px-5 py-4 print:bg-white print:px-0">
                <h2 id="judul-metode" class="text-sm font-semibold">Metode</h2>
                <p class="mt-1 text-sm leading-relaxed text-[#2f3833]">
                    Klaster dibentuk dari kemiripan teks (MinHash dan TF-IDF) dan aktivitas serentak (graf + Louvain),
                    lalu ditandai bila minimal {{ $r['params']['min_signals'] ?? 2 }} dari 4 sinyal aktif terhadap baseline kasus.
                    Jendela waktu {{ intdiv((int) ($r['params']['time_window_seconds'] ?? 300), 60) }} menit,
                    ambang MinHash {{ number_format((float) ($r['params']['minhash_threshold'] ?? 0.8), 2, ',', '') }},
                    ambang TF-IDF {{ number_format((float) ($r['params']['tfidf_threshold'] ?? 0.6), 2, ',', '') }},
                    seed {{ $r['params']['seed'] ?? 42 }}.
                    Analisis selesai {{ Format::wib($r['analysis_at']) }} WIB.
                </p>
            </section>
        @endif

        <footer class="mt-8 border-t border-[#e4e7e1] pt-4 text-xs leading-relaxed text-muted">
            Dibuat Radar Narasi {{ Format::wib($printedAt) }} WIB untuk {{ $printedBy->name }} ({{ $printedBy->role->label() }}).
            Identitas akun dipseudonimisasi; label akun dan klaster hanya berlaku di kasus ini. Akses ke halaman ini tercatat di log audit.
        </footer>
    </article>
</x-layouts.app>
