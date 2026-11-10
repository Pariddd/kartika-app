@props(['sweep'])

@php
    $points = $sweep['points'];
    $n = count($points);
    $w = 420; $h = 200;
    $left = 40; $right = 28; $top = 12; $bottom = 30;
    $plotW = $w - $left - $right;
    $plotH = $h - $top - $bottom;
    $x = fn (int $i) => $left + ($n > 1 ? $i * $plotW / ($n - 1) : $plotW / 2);
    $y = fn (float $rate) => $top + $plotH - $rate * $plotH;
    $path = collect($points)->map(fn ($p, $i) => ($i === 0 ? 'M' : 'L').round($x($i), 1).' '.round($y($p['rate']), 1))->implode(' ');
    $fmt = fn ($value) => is_float($value) && floor($value) != $value ? str_replace('.', ',', (string) $value) : number_format((float) $value, 0, ',', '.');
@endphp

<figure class="flex flex-col gap-2 rounded-xl border border-line bg-white p-5">
    <figcaption>
        <h3 class="font-semibold">{{ $sweep['title'] }}</h3>
        <p class="text-sm text-muted">{{ $sweep['note'] }}</p>
    </figcaption>

    <svg viewBox="0 0 {{ $w }} {{ $h }}" role="img" class="h-auto w-full"
         aria-label="Tingkat deteksi terhadap {{ mb_strtolower($sweep['title']) }}">
        @foreach ([0, 0.5, 1] as $tick)
            <line x1="{{ $left }}" x2="{{ $w - $right }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" stroke="#e4e7e1" stroke-width="1" />
            <text x="{{ $left - 8 }}" y="{{ $y($tick) + 4 }}" text-anchor="end" class="fill-muted" font-size="12">{{ (int) ($tick * 100) }}%</text>
        @endforeach
        <path d="{{ $path }}" fill="none" class="stroke-organic" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
        @foreach ($points as $i => $p)
            <g>
                <title>{{ $fmt($p['value']) }} {{ $sweep['unit'] }}: terdeteksi {{ $p['detected'] }} dari {{ $p['runs'] }} dataset</title>
                <circle cx="{{ $x($i) }}" cy="{{ $y($p['rate']) }}" r="12" fill="transparent" />
                <circle cx="{{ $x($i) }}" cy="{{ $y($p['rate']) }}" r="5" class="fill-organic" stroke="#ffffff" stroke-width="2" />
            </g>
            <text x="{{ $x($i) }}" y="{{ $h - 10 }}" text-anchor="middle" class="fill-muted" font-size="12">{{ $fmt($p['value']) }}</text>
        @endforeach
    </svg>

    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs text-muted">
                <th scope="col" class="py-1 pr-3 font-medium">{{ ucfirst($sweep['unit']) }}</th>
                @foreach ($points as $p)
                    <td class="px-1 py-1 text-right font-mono">{{ $fmt($p['value']) }}</td>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr class="border-t border-[#e4e7e1]">
                <th scope="row" class="py-1 pr-3 text-left text-xs font-medium text-muted">Terdeteksi</th>
                @foreach ($points as $p)
                    <td class="px-1 py-1 text-right font-mono">{{ $p['detected'] }}/{{ $p['runs'] }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>
</figure>
