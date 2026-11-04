@php
    $inputClass = 'min-h-11 w-full rounded-lg border border-[#b9bfb6] bg-white px-3 text-base focus:border-brand focus:outline-2 focus:outline-brand/30';
    $platform = old('platform', 'youtube');
@endphp

<x-layouts.app title="Kasus baru">
    <nav aria-label="Breadcrumb" class="flex gap-2 text-sm text-muted">
        <a href="{{ route('cases.index') }}" class="text-organic hover:underline">Kasus</a>
        <span aria-hidden="true">/</span>
        <span>Kasus baru</span>
    </nav>

    <div class="mt-4 flex max-w-3xl flex-col gap-1.5">
        <h1 class="text-3xl font-semibold">Kasus baru</h1>
        <p class="text-[15px] leading-relaxed text-muted">
            Sistem mengambil komentar publik, mengganti identitas akun dengan pseudonim sebelum disimpan,
            lalu menganalisis koordinasi secara otomatis.
        </p>
    </div>

    <form method="POST" action="{{ route('cases.store') }}" class="mt-8 flex max-w-3xl flex-col gap-6" novalidate>
        @csrf

        <section class="flex flex-col gap-5 rounded-xl border border-line bg-white p-6">
            <div class="flex flex-col gap-1.5">
                <label for="name" class="text-sm font-medium">Nama kasus</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="200" required
                       placeholder="Contoh: Isu penutupan pasar, gelombang 1"
                       @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
                       class="{{ $inputClass }}">
                @error('name') <p id="name-error" class="text-sm text-coord-ink">{{ $message }}</p> @enderror
            </div>

            <fieldset class="flex flex-col gap-2">
                <legend class="mb-1 text-sm font-medium">Sumber data</legend>
                <label class="flex min-h-11 items-center gap-3 rounded-lg border border-line px-3">
                    <input type="radio" name="platform" value="youtube" @checked($platform === 'youtube') class="size-4 accent-brand">
                    <span>YouTube <span class="text-sm text-muted">· komentar dan balasan dari video tertentu</span></span>
                </label>
                <label @class(['flex min-h-11 items-center gap-3 rounded-lg border border-line px-3', 'opacity-60' => ! $xEnabled])>
                    <input type="radio" name="platform" value="x" @checked($platform === 'x') @disabled(! $xEnabled) class="size-4 accent-brand">
                    <span>X <span class="text-sm text-muted">· {{ $xEnabled ? 'pencarian kata kunci lewat Apify' : 'belum aktif, menunggu izin panitia' }}</span></span>
                </label>
                @error('platform') <p class="text-sm text-coord-ink">{{ $message }}</p> @enderror
            </fieldset>

            <div class="flex flex-col gap-1.5">
                <label for="max_items" class="text-sm font-medium">Jumlah maksimal post</label>
                <input id="max_items" name="max_items" type="number" min="1" value="{{ old('max_items', 1000) }}" required
                       aria-describedby="max_items-help @error('max_items') max_items-error @enderror"
                       @error('max_items') aria-invalid="true" @enderror
                       class="{{ $inputClass }} max-w-48 font-mono">
                <p id="max_items-help" class="text-sm text-muted">
                    YouTube maksimal {{ \App\Support\Format::number($limits['youtube_max_items']) }}, X maksimal {{ \App\Support\Format::number($limits['x_max_items']) }}.
                </p>
                @error('max_items') <p id="max_items-error" class="text-sm text-coord-ink">{{ $message }}</p> @enderror
            </div>
        </section>

        <section aria-labelledby="judul-youtube" class="flex flex-col gap-4 rounded-xl border border-line bg-white p-6">
            <h2 id="judul-youtube" class="text-lg font-semibold">YouTube</h2>
            <div class="flex flex-col gap-1.5">
                <label for="video_urls" class="text-sm font-medium">Link atau ID video</label>
                <textarea id="video_urls" name="video_urls" rows="4"
                          placeholder="https://www.youtube.com/watch?v=...&#10;https://youtu.be/..."
                          aria-describedby="video_urls-help @error('video_urls') video_urls-error @enderror"
                          @error('video_urls') aria-invalid="true" @enderror
                          class="w-full rounded-lg border border-[#b9bfb6] bg-white px-3 py-2.5 font-mono text-sm focus:border-brand focus:outline-2 focus:outline-brand/30">{{ old('video_urls') }}</textarea>
                <p id="video_urls-help" class="text-sm text-muted">Satu per baris, maksimal {{ $limits['max_video_ids'] }} video. Memakai link video jauh lebih hemat kuota daripada pencarian kata kunci.</p>
                @error('video_urls') <p id="video_urls-error" class="text-sm text-coord-ink">{{ $message }}</p> @enderror
            </div>
        </section>

        @if ($xEnabled)
            <section aria-labelledby="judul-x" class="flex flex-col gap-4 rounded-xl border border-line bg-white p-6">
                <h2 id="judul-x" class="text-lg font-semibold">X</h2>
                <div class="flex flex-col gap-1.5">
                    <label for="query" class="text-sm font-medium">Kata kunci atau tagar</label>
                    <input id="query" name="query" type="text" value="{{ old('query') }}" maxlength="{{ $limits['query_max_length'] }}"
                           @error('query') aria-invalid="true" aria-describedby="query-error" @enderror
                           class="{{ $inputClass }}">
                    @error('query') <p id="query-error" class="text-sm text-coord-ink">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-wrap gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label for="since" class="text-sm font-medium">Mulai (opsional)</label>
                        <input id="since" name="since" type="date" value="{{ old('since') }}" class="{{ $inputClass }}">
                        @error('since') <p class="text-sm text-coord-ink">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="until" class="text-sm font-medium">Sampai (opsional)</label>
                        <input id="until" name="until" type="date" value="{{ old('until') }}" class="{{ $inputClass }}">
                        @error('until') <p class="text-sm text-coord-ink">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-brand px-5 font-medium text-white hover:bg-brand-dark">
                Buat kasus dan mulai pengumpulan
            </button>
            <a href="{{ route('cases.index') }}" class="inline-flex min-h-11 items-center rounded-lg border border-[#b9bfb6] bg-white px-5 text-ink hover:bg-paper">Batal</a>
        </div>
    </form>
</x-layouts.app>
