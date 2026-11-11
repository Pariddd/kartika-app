@php
    use App\Enums\Platform;
    use App\Support\Format;

    $inputClass = 'min-h-11 w-full rounded-lg border border-[#b9bfb6] bg-white px-3 text-base focus:border-brand focus:outline-2 focus:outline-brand/30';
    $platform = old('platform', 'youtube');
    $sources = [Platform::YouTube, ...Platform::imported()];
    $importSteps = [
        'x' => [
            'Jalankan Actor apidojo/tweet-scraper di Apify Console. Isi Search terms dengan kata kunci atau tagar topik; kosongkan Twitter handles dan Start URLs.',
            'Setelah selesai, buka tab Output lalu pilih Export → JSON.',
            'Unggah file itu di bawah.',
        ],
        'instagram' => [
            'Jalankan Actor pengambil komentar Instagram di Apify Console (misalnya Instagram Comments Scraper). Isi dengan link post atau reel.',
            'Setelah selesai, buka tab Output lalu pilih Export → JSON.',
            'Unggah file itu di bawah.',
        ],
        'tiktok' => [
            'Jalankan Actor pengambil komentar TikTok di Apify Console (misalnya TikTok Comments Scraper). Isi dengan link video.',
            'Setelah selesai, buka tab Output lalu pilih Export → JSON.',
            'Unggah file itu di bawah.',
        ],
    ];
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
            Pilih sumber, masukkan datanya, lalu sistem mengganti identitas akun dengan pseudonim dan menganalisis koordinasi secara otomatis.
        </p>
    </div>

    <form method="POST" action="{{ route('cases.store') }}" enctype="multipart/form-data" data-source-form
          class="mt-8 flex max-w-3xl flex-col gap-6" novalidate>
        @csrf

        {{-- 1. Sumber data --}}
        <fieldset class="flex flex-col gap-4 rounded-xl border border-line bg-white p-6">
            <legend class="sr-only">Sumber data</legend>
            <div class="flex items-baseline gap-3">
                <span class="font-mono text-sm text-muted" aria-hidden="true">1</span>
                <h2 class="text-lg font-semibold">Sumber data</h2>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($sources as $source)
                    @php $enabled = $source === Platform::YouTube || $importEnabled; @endphp
                    <label @class([
                        'flex gap-3 rounded-lg border border-line px-4 py-3',
                        'cursor-pointer hover:border-[#8f988c] has-[:checked]:border-brand has-[:checked]:bg-[#f3f6f2] has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-brand/40' => $enabled,
                        'opacity-55' => ! $enabled,
                    ])>
                        <input type="radio" name="platform" value="{{ $source->value }}"
                               @checked($platform === $source->value) @disabled(! $enabled)
                               class="mt-1 size-4 shrink-0 accent-brand">
                        <span class="flex flex-col gap-0.5">
                            <span class="font-medium">{{ $source->label() }}</span>
                            <span class="text-sm leading-snug text-muted">{{ $enabled ? $source->sourceHint() : 'Belum diaktifkan (RADAR_APIFY_ENABLED)' }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('platform') <p class="text-sm text-coord-ink">{{ $message }}</p> @enderror
        </fieldset>

        {{-- 2a. YouTube --}}
        <section data-source="youtube" aria-labelledby="judul-youtube" class="flex flex-col gap-4 rounded-xl border border-line bg-white p-6">
            <div class="flex items-baseline gap-3">
                <span class="font-mono text-sm text-muted" aria-hidden="true">2</span>
                <h2 id="judul-youtube" class="text-lg font-semibold">Video YouTube</h2>
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="video_urls" class="text-sm font-medium">Link atau ID video</label>
                <textarea id="video_urls" name="video_urls" rows="4"
                          placeholder="https://www.youtube.com/watch?v=...&#10;https://youtu.be/..."
                          aria-describedby="video_urls-help @error('video_urls') video_urls-error @enderror"
                          @error('video_urls') aria-invalid="true" @enderror
                          class="{{ $inputClass }} py-2 font-mono text-sm">{{ old('video_urls') }}</textarea>
                <p id="video_urls-help" class="text-sm text-muted">Satu per baris, maksimal {{ $limits['max_video_ids'] }} video. Jumlah post dihitung total untuk semua video.</p>
                @error('video_urls') <p id="video_urls-error" class="text-sm text-coord-ink">{{ $message }}</p> @enderror
            </div>
        </section>

        {{-- 2b. Impor dari Apify (X, Instagram, TikTok) --}}
        @if ($importEnabled)
            <section data-source="x instagram tiktok" aria-labelledby="judul-impor" class="flex flex-col gap-4 rounded-xl border border-line bg-white p-6">
                <div class="flex items-baseline gap-3">
                    <span class="font-mono text-sm text-muted" aria-hidden="true">2</span>
                    <h2 id="judul-impor" class="text-lg font-semibold">
                        Impor dari Apify
                        @foreach (Platform::imported() as $source)
                            <span data-source="{{ $source->value }}" class="font-normal text-muted">· {{ $source->label() }}</span>
                        @endforeach
                    </h2>
                </div>

                @foreach ($importSteps as $value => $steps)
                    <ol data-source="{{ $value }}" class="list-decimal space-y-1 pl-5 text-sm leading-relaxed text-[#2f3833]">
                        @foreach ($steps as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ol>
                @endforeach

                <div class="flex flex-col gap-1.5">
                    <label for="import_file" class="text-sm font-medium">File JSON hasil ekspor</label>
                    <input id="import_file" name="import_file" type="file" accept=".json,application/json"
                           aria-describedby="import_file-help @error('import_file') import_file-error @enderror"
                           @error('import_file') aria-invalid="true" @enderror
                           class="w-full rounded-lg border border-dashed border-[#b9bfb6] bg-[#fafbf9] p-3 text-sm file:mr-3 file:min-h-10 file:cursor-pointer file:rounded-md file:border file:border-[#b9bfb6] file:bg-white file:px-4 file:font-medium">
                    <p id="import_file-help" class="text-sm leading-relaxed text-muted">
                        Maksimal {{ $uploadLimit }}@if ($uploadLimitedByPhp) (batas unggahan PHP di komputer ini; naikkan <span class="font-mono">upload_max_filesize</span> dan <span class="font-mono">post_max_size</span> untuk file lebih besar)@endif.
                        File ekspor memuat profil penulis. SyncTrace hanya membaca kolom yang dibutuhkan analisis dan menghapus file setelah diimpor.
                    </p>
                    @error('import_file') <p id="import_file-error" class="text-sm text-coord-ink">{{ $message }}</p> @enderror
                </div>

                <p data-source="instagram tiktok" class="rounded-lg bg-info-soft px-4 py-3 text-sm leading-relaxed text-info-ink">
                    Nama kolom berbeda di tiap Actor. Pengimpor mengenali nama yang lazim; bila Actor pilihanmu memakai nama lain,
                    job berhenti dengan pesan "nama field belum dikenali" dan tidak ada data yang tersimpan.
                </p>

                <div class="flex flex-col gap-1.5">
                    <label for="query" class="text-sm font-medium">Kata kunci atau link yang dipakai di Apify <span class="font-normal text-muted">(opsional, sebagai catatan)</span></label>
                    <input id="query" name="query" type="text" value="{{ old('query') }}" maxlength="{{ $limits['query_max_length'] }}"
                           @error('query') aria-invalid="true" aria-describedby="query-error" @enderror
                           class="{{ $inputClass }}">
                    @error('query') <p id="query-error" class="text-sm text-coord-ink">{{ $message }}</p> @enderror
                </div>
            </section>
        @endif

        {{-- 3. Nama dan batas --}}
        <section aria-labelledby="judul-kasus" class="flex flex-col gap-5 rounded-xl border border-line bg-white p-6">
            <div class="flex items-baseline gap-3">
                <span class="font-mono text-sm text-muted" aria-hidden="true">3</span>
                <h2 id="judul-kasus" class="text-lg font-semibold">Kasus</h2>
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="name" class="text-sm font-medium">Nama kasus</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="200" required
                       placeholder="Contoh: Isu penutupan pasar, gelombang 1"
                       @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
                       class="{{ $inputClass }}">
                @error('name') <p id="name-error" class="text-sm text-coord-ink">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="max_items" class="text-sm font-medium">Jumlah maksimal post <span class="font-normal text-muted">(opsional)</span></label>
                <input id="max_items" name="max_items" type="number" min="1" max="{{ $limits['max_items'] }}" value="{{ old('max_items') }}"
                       placeholder="Semua"
                       aria-describedby="max_items-help @error('max_items') max_items-error @enderror"
                       @error('max_items') aria-invalid="true" @enderror
                       class="{{ $inputClass }} max-w-48 font-mono">
                <p id="max_items-help" class="text-sm leading-relaxed text-muted">
                    Kosongkan untuk mengambil semua yang tersedia, sampai batas {{ Format::number($limits['max_items']) }} post per kasus.
                    Batas itu ada karena memori analisis naik tajam: 20.000 post butuh sekitar 3 GB.
                </p>
                @error('max_items') <p id="max_items-error" class="text-sm text-coord-ink">{{ $message }}</p> @enderror
            </div>
        </section>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-brand px-5 font-medium text-white hover:bg-brand-dark">
                <span data-source="youtube">Buat kasus dan ambil komentar</span>
                <span data-source="x instagram tiktok">Buat kasus dan impor file</span>
            </button>
            <a href="{{ route('cases.index') }}" class="inline-flex min-h-11 items-center rounded-lg border border-[#b9bfb6] bg-white px-5 hover:bg-paper">Batal</a>
        </div>
    </form>
</x-layouts.app>
