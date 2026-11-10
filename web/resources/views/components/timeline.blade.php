@props(['timeline'])

@php
    use App\Support\Format;

    $buckets = $timeline['buckets'];
    $n = count($buckets);
    $w = 960; $h = 240;
    $left = 44; $right = 8; $top = 10; $bottom = 28;
    $plotW = $w - $left - $right;
    $plotH = $h - $top - $bottom;
    $slot = $plotW / $n;
    $bar = max(4, min(26, $slot - 4));
    $axisMax = $timeline['axis_max'];
    $y = fn (int $value) => $top + $plotH - ($value / $axisMax) * $plotH;
    $tickEvery = max(1, (int) ceil($n / 8));
    $gap = 2;
@endphp

<figure class="flex flex-col gap-3">
    @if ($timeline['has_coordinated'])
        <ul class="flex flex-wrap gap-x-5 gap-y-1 text-sm" aria-label="Keterangan warna">
            <li class="flex items-center gap-2"><span class="size-3 rounded-sm bg-coord" aria-hidden="true"></span>Post dari klaster ditandai</li>
            <li class="flex items-center gap-2"><span class="size-3 rounded-sm bg-organic" aria-hidden="true"></span>Post lain</li>
        </ul>
    @endif

    <svg viewBox="0 0 {{ $w }} {{ $h }}" role="img" class="h-auto w-full"
         aria-label="Jumlah post per {{ $timeline['bucket_label'] }}. Puncak {{ $timeline['peak']['total'] }} post pada {{ $timeline['peak']['label'] }}.">
        {{-- Garis bantu dan label sumbu Y --}}
        @foreach ([0, $axisMax / 2, $axisMax] as $value)
            <line x1="{{ $left }}" x2="{{ $w - $right }}" y1="{{ $y((int) $value) }}" y2="{{ $y((int) $value) }}" stroke="#e4e7e1" stroke-width="1" />
            <text x="{{ $left - 8 }}" y="{{ $y((int) $value) + 4 }}" text-anchor="end" class="fill-muted" font-size="12">{{ Format::number((int) $value) }}</text>
        @endforeach

        @foreach ($buckets as $i => $b)
            @php
                $x = $left + $i * $slot + ($slot - $bar) / 2;
                $otherH = $b['other'] / $axisMax * $plotH;
                $coordH = $b['coordinated'] / $axisMax * $plotH;
                $both = $b['other'] > 0 && $b['coordinated'] > 0;
                $coordDraw = max(0, $coordH - ($both ? $gap : 0));
            @endphp
            <g class="[&:hover>.hit]:fill-black/5">
                <title>{{ $b['label'] }}: {{ Format::number($b['total']) }} post@if ($timeline['has_coordinated']), {{ Format::number($b['coordinated']) }} dari klaster ditandai@endif</title>
                <rect class="hit" x="{{ $left + $i * $slot }}" y="{{ $top }}" width="{{ $slot }}" height="{{ $plotH }}" fill="transparent" />
                @if ($b['other'] > 0)
                    <rect x="{{ $x }}" y="{{ $top + $plotH - $otherH }}" width="{{ $bar }}" height="{{ $otherH }}" rx="{{ $b['coordinated'] > 0 ? 0 : 2 }}" class="fill-organic" />
                @endif
                @if ($b['coordinated'] > 0 && $coordDraw > 0)
                    <rect x="{{ $x }}" y="{{ $top + $plotH - $otherH - $coordH }}" width="{{ $bar }}" height="{{ $coordDraw }}" rx="2" class="fill-coord" />
                @endif
            </g>
            @if ($i % $tickEvery === 0)
                <text x="{{ $left + $i * $slot + $slot / 2 }}" y="{{ $h - 8 }}" text-anchor="middle" class="fill-muted" font-size="12">{{ $b['tick'] }}</text>
            @endif
        @endforeach
    </svg>

    <figcaption class="text-sm text-muted">
        Tiap batang = {{ $timeline['bucket_label'] }} (WIB).
        Puncak percakapan: {{ $timeline['peak']['label'] }} ({{ Format::number($timeline['peak']['total']) }} post).
        @if ($timeline['coordinated_peak'])
            Puncak klaster ditandai: {{ $timeline['coordinated_peak']['label'] }}
            ({{ Format::number($timeline['coordinated_peak']['coordinated']) }} dari {{ Format::number($timeline['coordinated_peak']['total']) }} post).
        @endif
    </figcaption>

    <details class="text-sm">
        <summary class="cursor-pointer text-organic">Lihat sebagai tabel</summary>
        <div class="mt-2 max-h-72 overflow-auto">
            <table class="w-full min-w-[420px]">
                <thead>
                    <tr class="text-left text-xs text-muted">
                        <th scope="col" class="py-1.5 pr-3 font-medium">Rentang</th>
                        <th scope="col" class="px-3 py-1.5 text-right font-medium">Post</th>
                        @if ($timeline['has_coordinated'])
                            <th scope="col" class="py-1.5 pl-3 text-right font-medium">Dari klaster ditandai</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($buckets as $b)
                        <tr class="border-t border-[#e4e7e1]">
                            <td class="py-1.5 pr-3">{{ $b['label'] }}</td>
                            <td class="px-3 py-1.5 text-right font-mono">{{ Format::number($b['total']) }}</td>
                            @if ($timeline['has_coordinated'])
                                <td class="py-1.5 pl-3 text-right font-mono">{{ Format::number($b['coordinated']) }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</figure>
