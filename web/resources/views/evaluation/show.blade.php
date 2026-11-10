@php use App\Support\Format; @endphp

<x-layouts.app title="Evaluasi">
    <div class="flex max-w-3xl flex-col gap-1.5">
        <h1 class="text-3xl font-semibold">Evaluasi</h1>
        <p class="text-[15px] leading-relaxed text-muted">
            Seberapa baik sistem menemukan klaster terkoordinasi, dan pada titik mana ia mulai gagal.
            Diukur dengan menanam klaster buatan ke percakapan sintetis lalu memeriksa apakah sistem menemukannya.
        </p>
    </div>

    @if (! $report)
        <p class="mt-6 rounded-lg bg-info-soft px-4 py-3 text-sm text-info-ink">
            Hasil evaluasi belum dibuat. Jalankan <span class="font-mono">python -m radar.analysis.robustness --out ../web/resources/evaluation/hasil-evaluasi.json</span> dari folder engine.
        </p>
    @else
        <p class="mt-6 rounded-lg border border-info-line bg-info-soft px-4 py-3 text-sm leading-relaxed text-info-ink">
            <strong class="font-semibold">Cara membaca angka di halaman ini.</strong>
            Semua data sintetis. Angka di bawah menunjukkan perilaku sistem pada skenario yang kami buat sendiri,
            bukan akurasi pada data dunia nyata. Ambang deteksi disetel memakai seed {{ implode(', ', $report['calibration_seeds']) }};
            hasil uji hanya memakai seed lain yang tidak ikut menyetel ambang.
        </p>

        <section aria-label="Ringkasan hasil uji" class="mt-6 grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-4">
            <div class="flex flex-col gap-1 rounded-xl border border-line bg-white px-5 py-4">
                <span class="text-xs text-muted">Klaster tanaman yang ditemukan</span>
                <span class="text-2xl font-semibold">{{ $summary['caught'] }} dari {{ $summary['planted'] }}</span>
                <span class="text-xs text-muted">di {{ $summary['datasets'] }} dataset uji</span>
            </div>
            <div class="flex flex-col gap-1 rounded-xl border border-line bg-white px-5 py-4">
                <span class="text-xs text-muted">Klaster keliru ditandai</span>
                <span class="text-2xl font-semibold">{{ $summary['false_flags'] }}</span>
                <span class="text-xs text-muted">ditandai padahal bukan tanaman</span>
            </div>
            <div class="flex flex-col gap-1 rounded-xl border border-line bg-white px-5 py-4">
                <span class="text-xs text-muted">Pengecoh sah yang ikut ditandai</span>
                <span class="text-2xl font-semibold">{{ $summary['decoys_flagged'] }}</span>
                <span class="text-xs text-muted">akun yang membagikan pengumuman resmi yang sama</span>
            </div>
        </section>

        <section aria-labelledby="judul-dasar" class="mt-6 rounded-xl border border-line bg-white">
            <div class="border-b border-[#e4e7e1] px-5 py-4">
                <h2 id="judul-dasar" class="text-lg font-semibold">Hasil per skenario</h2>
                <p class="text-sm text-muted">Klaster temuan dianggap cocok dengan klaster tanaman bila irisan anggotanya (Jaccard) minimal {{ str_replace('.', ',', (string) $report['match_jaccard']) }}.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead>
                        <tr class="text-left text-xs text-muted">
                            <th scope="col" class="px-5 py-3 font-medium">Skenario</th>
                            <th scope="col" class="px-3 py-3 font-medium">Kelompok seed</th>
                            <th scope="col" class="px-3 py-3 text-right font-medium">Dataset</th>
                            <th scope="col" class="px-3 py-3 text-right font-medium">Ditemukan</th>
                            <th scope="col" class="px-3 py-3 text-right font-medium">Keliru ditandai</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Pengecoh ditandai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['baseline'] as $row)
                            <tr class="border-t border-[#e4e7e1]">
                                <td class="px-5 py-2.5">{{ $presetLabels[$row['preset']] ?? $row['preset'] }}</td>
                                <td class="px-3 py-2.5">{{ $row['group'] === 'uji' ? 'Uji' : 'Kalibrasi' }} <span class="font-mono text-xs text-muted">({{ implode(', ', $row['seeds']) }})</span></td>
                                <td class="px-3 py-2.5 text-right font-mono">{{ $row['datasets'] }}</td>
                                <td class="px-3 py-2.5 text-right font-mono">{{ $row['planted'] > 0 ? $row['caught'].'/'.$row['planted'] : '–' }}</td>
                                <td class="px-3 py-2.5 text-right font-mono">{{ $row['false_flags'] }}</td>
                                <td class="px-5 py-2.5 text-right font-mono">{{ $row['decoys_flagged'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="judul-ketahanan" class="mt-8">
            <h2 id="judul-ketahanan" class="text-lg font-semibold">Uji ketahanan: di mana deteksi mulai gagal</h2>
            <p class="mt-1 max-w-3xl text-sm leading-relaxed text-muted">
                Satu klaster acuan ({{ $report['base_cluster']['size'] }} akun, {{ $report['base_cluster']['events'] }} kejadian, sebaran {{ $report['base_cluster']['spread_seconds'] }} detik,
                parafrase {{ str_replace('.', ',', (string) $report['base_cluster']['paraphrase']) }}) ditanam ke 900 akun organik. Klaster ini sengaja tanpa domain khas dan tanpa akun baru,
                sehingga hanya sinyal teks dan waktu yang bisa menandainya. Satu parameter diubah bertahap; tiap titik diuji pada {{ count($report['sweep_seeds']) }} dataset.
                Sumbu tegak: bagian dataset tempat klaster ditemukan.
            </p>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach ($report['sweeps'] as $sweep)
                    <x-rate-chart :sweep="$sweep" />
                @endforeach
            </div>
        </section>

        <section aria-labelledby="judul-batas-eval" class="mt-8 rounded-xl border border-line bg-white p-5">
            <h2 id="judul-batas-eval" class="text-lg font-semibold">Keterbatasan evaluasi</h2>
            <ul class="mt-2 list-disc pl-5 text-[15px] leading-relaxed text-[#2f3833]">
                <li>Data sintetis dibuat oleh tim yang sama dengan pembuat sistem, sehingga skenarionya bisa lebih mudah daripada operasi sungguhan.</li>
                <li>Parafrase sintetis dibuat dari templat yang sama (ganti pilihan kata, tambah kata pembuka dan penutup). Penulisan ulang bebas, misalnya oleh model bahasa, lebih sulit dan belum diuji; karena itu grafik parafrase yang datar tidak berarti sistem kebal parafrase.</li>
                <li>Tidak ada data berlabel yang memastikan akun mana di dunia nyata yang terkoordinasi; karena itu tidak ada klaim akurasi dunia nyata.</li>
                <li>Operasi yang menyebar waktunya, menulis ulang seluruh teks, dan memakai akun lama bisa lolos. Grafik di atas menunjukkan batas itu apa adanya.</li>
                <li>Tiap titik uji ketahanan hanya {{ count($report['sweep_seeds']) }} dataset; angkanya menunjukkan arah, bukan estimasi statistik yang presisi.</li>
            </ul>
            <p class="mt-3 text-sm text-muted">
                Parameter: minimal {{ $report['params']['min_signals'] }} sinyal, jendela waktu {{ intdiv($report['params']['time_window_seconds'], 60) }} menit,
                minimal {{ $report['params']['min_co_events'] }} ko-kejadian, ambang MinHash {{ str_replace('.', ',', (string) $report['params']['minhash_threshold']) }},
                ambang TF-IDF {{ str_replace('.', ',', (string) $report['params']['tfidf_threshold']) }}, seed Louvain {{ $report['params']['seed'] }}.
                Bisa diulang dengan <span class="font-mono">python -m radar.analysis.robustness</span>.
            </p>
        </section>
    @endif
</x-layouts.app>
