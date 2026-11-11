@props(['network'])

@php use App\Support\Format; @endphp

<figure class="flex flex-col gap-3" data-graph="{{ json_encode($network['data']) }}">
    <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2">
        <ul class="flex flex-wrap gap-x-5 gap-y-1 text-sm" aria-label="Keterangan">
            <li class="flex items-center gap-2"><span class="size-3 rounded-full bg-coord" aria-hidden="true"></span>Akun di klaster ditandai</li>
            <li class="flex items-center gap-2"><span class="size-3 rounded-full bg-[#6f7f92]" aria-hidden="true"></span>Akun di klaster tidak ditandai</li>
            <li class="flex items-center gap-2"><span class="size-3 rounded-full bg-[#b9bfb6]" aria-hidden="true"></span>Akun lain yang terhubung</li>
            <li class="flex items-center gap-2"><span class="inline-block w-6 border-t-2 border-[#8a939c]" aria-hidden="true"></span>Teks mirip</li>
            <li class="flex items-center gap-2"><span class="inline-block w-6 border-t-2 border-dashed border-[#8a939c]" aria-hidden="true"></span>Waktu serentak</li>
        </ul>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" checked data-graph-filter="text" class="size-4 accent-brand">Hubungan teks</label>
            <label class="flex items-center gap-2"><input type="checkbox" checked data-graph-filter="time" class="size-4 accent-brand">Hubungan waktu</label>
            <label class="flex items-center gap-2"><input type="checkbox" data-graph-filter="flagged" class="size-4 accent-brand">Hanya klaster ditandai</label>
        </div>
    </div>

    <div class="relative overflow-hidden rounded-lg border border-[#e4e7e1] bg-[#fbfbfa]">
        <svg data-graph-canvas viewBox="0 0 1000 1000" role="img" class="block h-[560px] w-full cursor-grab touch-none select-none"
             aria-label="Graf koordinasi: {{ $network['shown_nodes'] }} akun dan {{ $network['shown_edges'] }} hubungan. {{ $network['flagged_nodes'] }} akun berada di klaster ditandai."></svg>

        <div class="absolute right-3 top-3 flex flex-col gap-1.5">
            <button type="button" data-graph-zoom="in" aria-label="Perbesar" class="size-9 rounded-md border border-[#b9bfb6] bg-white text-lg leading-none hover:bg-paper">+</button>
            <button type="button" data-graph-zoom="out" aria-label="Perkecil" class="size-9 rounded-md border border-[#b9bfb6] bg-white text-lg leading-none hover:bg-paper">−</button>
            <button type="button" data-graph-zoom="reset" aria-label="Kembalikan tampilan" class="size-9 rounded-md border border-[#b9bfb6] bg-white text-xs hover:bg-paper">1:1</button>
        </div>

        <div data-graph-tip hidden class="pointer-events-none absolute z-10 max-w-64 rounded-md border border-line bg-white px-3 py-2 text-sm shadow-md"></div>

        <div data-graph-panel hidden class="absolute bottom-3 left-3 max-w-xs rounded-lg border border-line bg-white px-4 py-3 text-sm shadow-md">
            <p class="font-mono font-semibold" data-graph-panel-title></p>
            <p class="mt-0.5 text-muted" data-graph-panel-body></p>
            <a data-graph-panel-link hidden class="mt-2 inline-block font-medium text-organic hover:underline">Lihat bukti klaster</a>
        </div>
    </div>

    <figcaption class="text-sm leading-relaxed text-muted">
        Titik = akun, garis = hubungan yang dipakai membentuk klaster. Arahkan kursor ke akun untuk melihat tetangganya, klik untuk menguncinya,
        seret untuk memindahkan, gulir untuk memperbesar.
        Menampilkan {{ Format::number($network['shown_nodes']) }} dari {{ Format::number($network['total_nodes']) }} akun yang punya hubungan
        dan {{ Format::number($network['shown_edges']) }} dari {{ Format::number($network['total_edges']) }} hubungan{{ $network['truncated'] ? ' (tiap akun hanya membawa hubungan terkuatnya agar graf terbaca)' : '' }}.
        Garis menunjukkan kemiripan perilaku, bukan bukti bahwa akun saling mengenal.
    </figcaption>

    <details class="text-sm">
        <summary class="cursor-pointer text-organic">Lihat sebagai tabel</summary>
        <table class="mt-2 w-full max-w-md">
            <thead>
                <tr class="text-left text-xs text-muted">
                    <th scope="col" class="py-1.5 pr-3 font-medium">Klaster</th>
                    <th scope="col" class="px-3 py-1.5 text-right font-medium">Akun di graf</th>
                    <th scope="col" class="py-1.5 pl-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($network['clusters'] as $row)
                    <tr class="border-t border-[#e4e7e1]">
                        <td class="py-1.5 pr-3 font-mono">{{ $row['label'] }}</td>
                        <td class="px-3 py-1.5 text-right font-mono">{{ Format::number($row['accounts']) }}</td>
                        <td class="py-1.5 pl-3">{{ $row['flagged'] ? 'Ditandai' : 'Tidak ditandai' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </details>
</figure>
