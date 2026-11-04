@props(['status'])

@php
    $active = method_exists($status, 'isActive') && $status->isActive();
    $failed = $status->value === 'failed';
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium',
    'bg-info-soft text-info-ink' => $active,
    'bg-coord-soft text-coord-ink' => $failed,
    'bg-[#eef0ec] text-[#46504a]' => ! $active && ! $failed,
]) }}>
    @if ($active)
        <span class="size-1.5 animate-pulse rounded-full bg-organic" aria-hidden="true"></span>
    @endif
    {{ $status->label() }}
</span>
