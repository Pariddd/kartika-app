@php
    use App\Support\SyncStrip;
    $strip = SyncStrip::build();
    $bottom = SyncStrip::ROW_TOP + (SyncStrip::ROWS - 1) * SyncStrip::ROW_GAP + 18;
@endphp

{{-- Ilustrasi: garis pindai melintasi satu jam percakapan; empat akun muncul serempak tiga kali. --}}
<figure {{ $attributes->class(['sync-strip']) }}>
    <svg viewBox="0 0 {{ SyncStrip::WIDTH }} {{ SyncStrip::HEIGHT }}" role="img" aria-labelledby="judul-strip desc-strip" class="block h-auto w-full min-w-[640px]">
        <title id="judul-strip">Ilustrasi: sepuluh akun dalam satu jam percakapan</title>
        <desc id="desc-strip">Enam akun menulis pada waktu yang acak. Empat akun menulis dalam jendela lima menit yang sama sebanyak tiga kali, lalu dihubungkan dengan garis.</desc>

        @foreach ($strip['rows'] as $row)
            <line x1="{{ SyncStrip::LEFT }}" x2="{{ SyncStrip::RIGHT }}" y1="{{ $row['y'] }}" y2="{{ $row['y'] }}" class="sync-rail" />
            <text x="{{ SyncStrip::LEFT - 16 }}" y="{{ $row['y'] + 4 }}" text-anchor="end" @class(['sync-label', 'sync-label-hit' => $row['coordinated']])>{{ $row['label'] }}</text>
        @endforeach

        @foreach ($strip['ticks'] as $tick)
            <line x1="{{ $tick['x'] }}" x2="{{ $tick['x'] }}" y1="{{ $bottom }}" y2="{{ $bottom + 6 }}" class="sync-tick" />
            <text x="{{ $tick['x'] }}" y="{{ $bottom + 24 }}" text-anchor="middle" class="sync-label">{{ $tick['label'] }}</text>
        @endforeach

        @foreach ($strip['events'] as $event)
            <g class="sync-event" style="animation-delay: {{ $event['delay'] }}s">
                <rect x="{{ $event['x'] }}" y="{{ SyncStrip::ROW_TOP - 22 }}" width="{{ $event['width'] }}" height="{{ $bottom - SyncStrip::ROW_TOP + 22 }}" rx="6" class="sync-window" />
                <text x="{{ $event['x'] + $event['width'] / 2 }}" y="{{ SyncStrip::ROW_TOP - 28 }}" text-anchor="middle" class="sync-count">{{ $event['label'] }}</text>
            </g>
            <polyline points="{{ $event['points'] }}" pathLength="1" class="sync-trace" style="animation-delay: {{ $event['delay'] }}s" />
        @endforeach

        @foreach ($strip['dots'] as $dot)
            <circle cx="{{ $dot['x'] }}" cy="{{ $dot['y'] }}" r="{{ $dot['sync'] ? 6 : 4.5 }}" @class(['sync-dot', 'sync-dot-hit' => $dot['sync']]) style="animation-delay: {{ $dot['delay'] }}s" />
        @endforeach

        <line x1="{{ SyncStrip::LEFT }}" x2="{{ SyncStrip::LEFT }}" y1="{{ SyncStrip::ROW_TOP - 22 }}" y2="{{ $bottom }}" class="sync-playhead" />
    </svg>
</figure>
