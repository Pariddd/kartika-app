@props(['signals'])

{{-- Empat sinyal dalam urutan tetap; terisi = aktif, garis putus = tidak aktif. --}}
<div class="flex flex-wrap gap-1.5">
    @foreach (\App\Enums\SignalType::ordered() as $type)
        @php $signal = $signals->first(fn ($s) => $s->signal === $type); @endphp
        @if ($signal?->active)
            <span class="rounded-full bg-coord-soft px-2 py-0.5 text-xs font-medium text-coord-ink">{{ $type->shortLabel() }}</span>
        @else
            <span class="rounded-full border border-dashed border-[#b9bfb6] px-2 py-0.5 text-xs text-muted">
                {{ $type->shortLabel() }}<span class="sr-only"> (tidak aktif)</span>
            </span>
        @endif
    @endforeach
</div>
