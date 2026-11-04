@props(['score'])

{{-- Biru = porsi organik, oranye = porsi dari klaster terkoordinasi. --}}
@if ($score === null)
    <span class="text-sm text-muted">Belum tersedia</span>
@else
    <div class="flex items-center gap-2.5" role="img" aria-label="{{ \App\Support\Format::percent($score) }} organik">
        <div class="flex h-2 flex-1 overflow-hidden rounded bg-coord">
            <div class="bg-organic" style="width: {{ (int) round($score * 100) }}%"></div>
        </div>
        <span class="min-w-10 text-right font-mono text-sm font-medium">{{ \App\Support\Format::percent($score) }}</span>
    </div>
@endif
