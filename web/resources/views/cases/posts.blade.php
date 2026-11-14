@php use App\Support\Format; use App\Support\ContentSource; @endphp

<x-layouts.app :title="'Komentar · '.$case->displayName()">
    <nav aria-label="Breadcrumb" class="flex flex-wrap gap-2 text-sm text-muted">
        <a href="{{ route('cases.index') }}" class="text-organic hover:underline">Kasus</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('cases.show', $case) }}" class="text-organic hover:underline">{{ $case->displayName() }}</a>
        <span aria-hidden="true">/</span>
        <span>Komentar</span>
    </nav>

    <div class="mt-4 flex flex-col gap-1.5">
        <h1 class="text-3xl font-semibold">Komentar</h1>
        <p class="text-[15px] text-muted">
            Semua {{ Format::number($total) }} post dalam kasus ini, apa adanya. Gunakan filter untuk membaca isi satu klaster, lalu bandingkan dengan percakapan lainnya.
        </p>
    </div>

    <form method="GET" action="{{ route('cases.posts', $case) }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-xl border border-line bg-white p-4" role="search">
        <div class="flex min-w-[200px] flex-col gap-1.5">
            <label for="klaster" class="text-sm font-medium">Tampilkan</label>
            <select id="klaster" name="klaster" class="min-h-11 rounded-lg border border-[#b9bfb6] bg-white px-3 text-[15px]">
                <option value="" @selected($selected === '')>Semua post</option>
                <option value="ditandai" @selected($selected === 'ditandai')>Semua klaster ditandai</option>
                <option value="lain" @selected($selected === 'lain')>Selain klaster ditandai</option>
                @foreach ($clusters as $cluster)
                    <option value="{{ $cluster->display_label }}" @selected($selected === $cluster->display_label)>
                        Klaster {{ $cluster->display_label }}{{ $cluster->is_flagged ? ' (ditandai)' : '' }} · {{ $cluster->size }} akun
                    </option>
                @endforeach
            </select>
        </div>
        @if ($sources->isNotEmpty())
            <div class="flex min-w-[200px] flex-col gap-1.5">
                <label for="sumber" class="text-sm font-medium">Konten sumber</label>
                <select id="sumber" name="sumber" class="min-h-11 rounded-lg border border-[#b9bfb6] bg-white px-3 text-[15px]">
                    <option value="" @selected($source === '')>Semua konten</option>
                    @foreach ($sources as $item)
                        <option value="{{ $item->ref }}" @selected($source === $item->ref)>{{ ContentSource::label($item->ref) }} · {{ Format::number($item->total) }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="flex min-w-[240px] flex-1 flex-col gap-1.5">
            <label for="q" class="text-sm font-medium">Cari kata</label>
            <input id="q" name="q" type="search" value="{{ $search }}" maxlength="100" placeholder="Misalnya: tarif parkir"
                   class="min-h-11 rounded-lg border border-[#b9bfb6] px-3 text-[15px]">
        </div>
        <div class="flex flex-col gap-1.5">
            <label for="urut" class="text-sm font-medium">Urutan</label>
            <select id="urut" name="urut" class="min-h-11 rounded-lg border border-[#b9bfb6] bg-white px-3 text-[15px]">
                <option value="lama" @selected($oldest)>Terlama dulu</option>
                <option value="baru" @selected(! $oldest)>Terbaru dulu</option>
            </select>
        </div>
        <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-brand px-5 font-semibold text-white hover:bg-brand-dark focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-organic">Terapkan</button>
        @if ($selected !== '' || $search !== '' || $source !== '')
            <a href="{{ route('cases.posts', $case) }}" class="inline-flex min-h-11 items-center px-2 text-[15px] font-medium text-organic hover:underline">Hapus filter</a>
        @endif
    </form>

    @if ($sources->count() > 1)
        <details class="mt-4 rounded-xl border border-line bg-white" @if ($sources->count() <= 8) open @endif>
            <summary class="cursor-pointer px-5 py-3 font-semibold">Konten sumber ({{ $sources->count() }})</summary>
            <p class="px-5 text-sm text-muted">Klaster yang sama muncul di beberapa konten sekaligus adalah tanda kampanye yang lebih kuat daripada satu konten saja.</p>
            <div class="overflow-x-auto">
                <table class="mt-2 w-full min-w-[640px] text-sm">
                    <thead>
                        <tr class="text-left text-xs text-muted">
                            <th scope="col" class="px-5 py-2 font-medium">Konten</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">Komentar</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">Dari klaster ditandai</th>
                            <th scope="col" class="px-5 py-2 font-medium">Klaster ditandai yang hadir</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sources as $item)
                            <tr class="border-t border-[#e4e7e1]">
                                <td class="px-5 py-2">
                                    <a href="{{ route('cases.posts', [$case, 'sumber' => $item->ref]) }}" class="font-medium text-organic hover:underline">{{ ContentSource::label($item->ref) }}</a>
                                    <a href="{{ ContentSource::url($item->ref) }}" target="_blank" rel="noopener noreferrer nofollow" class="ml-2 text-xs text-muted hover:underline">buka ↗</a>
                                </td>
                                <td class="px-3 py-2 text-right font-mono">{{ Format::number($item->total) }}</td>
                                <td class="px-3 py-2 text-right font-mono {{ $item->flagged > 0 ? 'text-coord-ink' : 'text-muted' }}">{{ Format::percent($item->total > 0 ? $item->flagged / $item->total : 0) }}</td>
                                <td class="px-5 py-2 font-mono">{{ $item->clusters === [] ? '–' : implode(', ', $item->clusters) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    <p class="mt-4 text-sm text-muted" aria-live="polite">
        {{ Format::number($posts->total()) }} post cocok{{ $posts->total() > 0 ? ' · halaman '.$posts->currentPage().' dari '.$posts->lastPage() : '' }}
    </p>

    @if ($posts->isEmpty())
        <section class="mt-3 rounded-xl border border-dashed border-[#b9bfb6] px-6 py-10 text-center">
            <p class="font-semibold">Tidak ada post yang cocok.</p>
            <p class="mt-1 text-sm text-muted">Ubah kata pencarian atau pilih "Semua post".</p>
        </section>
    @else
        <ol class="mt-3 flex flex-col divide-y divide-[#e4e7e1] rounded-xl border border-line bg-white">
            @foreach ($posts as $post)
                @php $cluster = $clusterOf->get($post->account_id); @endphp
                <li class="flex flex-col gap-1.5 px-5 py-4">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                        <span class="font-mono font-medium">{{ $post->account?->display_label ?? '–' }}</span>
                        @if ($showNames && $post->account?->display_name)
                            <span class="text-ink">{{ $post->account->display_name }}</span>
                        @endif
                        <span class="text-muted">{{ Format::wib($post->posted_at) }} WIB</span>
                        @if ($post->source_ref && ($sourceUrl = ContentSource::url($post->source_ref)))
                            <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer nofollow" class="text-xs text-organic hover:underline">di {{ ContentSource::label($post->source_ref) }} ↗</a>
                        @endif
                        @if ($cluster)
                            <a href="{{ route('clusters.show', [$case, $cluster->id]) }}"
                               @class([
                                   'rounded-full px-2.5 py-0.5 text-xs font-semibold hover:underline',
                                   'bg-coord-soft text-coord-ink' => $cluster->is_flagged,
                                   'bg-[#eef0ec] text-[#46504a]' => ! $cluster->is_flagged,
                               ])>Klaster {{ $cluster->display_label }}{{ $cluster->is_flagged ? ' · ditandai' : '' }}</a>
                        @endif
                    </div>
                    <x-post-text :post="$post" :masked="$masked" :show-names="$showNames" />
                </li>
            @endforeach
        </ol>

        <div class="mt-4">{{ $posts->links() }}</div>
    @endif
</x-layouts.app>
