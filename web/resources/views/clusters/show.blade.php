@php
    use App\Enums\SignalType;
    use App\Support\Format;
    use App\Support\ContentMask;
    use App\Support\SignalExplainer;
    $text = $signals->get('text');
    $time = $signals->get('time');
    $infra = $signals->get('infra');
@endphp

<x-layouts.app :title="'Klaster '.$cluster->display_label">
    <nav aria-label="Breadcrumb" class="flex flex-wrap gap-2 text-sm text-muted">
        <a href="{{ route('cases.index') }}" class="text-organic hover:underline">Kasus</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('cases.show', $case) }}" class="text-organic hover:underline">{{ $case->displayName() }}</a>
        <span aria-hidden="true">/</span>
        <span>{{ $cluster->display_label }}</span>
    </nav>

    <div class="mt-4 flex flex-wrap items-center gap-3">
        <h1 class="text-3xl font-semibold">Klaster <span class="font-mono">{{ $cluster->display_label }}</span></h1>
        @if ($cluster->is_flagged)
            <span class="rounded-full bg-coord-soft px-3 py-1 text-sm font-semibold text-coord-ink">Ditandai · {{ $cluster->signals_active }} dari 4 sinyal aktif</span>
        @else
            <span class="rounded-full bg-[#eef0ec] px-3 py-1 text-sm font-medium text-[#46504a]">Tidak ditandai · {{ $cluster->signals_active }} dari 4 sinyal aktif</span>
        @endif
    </div>
    <p class="mt-2 text-[15px] text-muted">
        {{ $cluster->size }} akun · skor koordinasi <strong class="font-mono text-ink">{{ number_format($cluster->coordination_score, 2, ',', '') }}</strong>
        (rata-rata kekuatan empat sinyal)
    </p>

    @if (session('status'))
        <p role="status" class="mt-6 rounded-lg bg-info-soft px-4 py-3 text-sm text-info-ink">{{ session('status') }}</p>
    @endif

    <p class="mt-6 rounded-lg bg-[#eef0ec] px-4 py-3 text-sm leading-relaxed text-[#2f3833]">
        Koordinasi tidak selalu berarti manipulasi: komunitas atau pengumuman resmi juga bisa membuat akun bergerak bersama.
        Klaster ditandai bila minimal 2 sinyal independen aktif. Sistem tidak mengambil tindakan otomatis.
    </p>

    <section aria-labelledby="judul-sinyal" class="mt-6">
        <h2 id="judul-sinyal" class="text-lg font-semibold">Empat sinyal</h2>
        <div class="mt-3 grid grid-cols-[repeat(auto-fit,minmax(260px,1fr))] gap-4">
            @foreach (SignalType::ordered() as $type)
                @php $signal = $signals->get($type->value); @endphp
                @continue($signal === null)
                <div @class(['flex flex-col gap-2 rounded-xl bg-white p-4', 'border border-[#e6b98c]' => $signal->active, 'border border-dashed border-[#b9bfb6]' => ! $signal->active])>
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold">{{ $type->label() }}</span>
                        <span @class(['rounded-full px-2 py-0.5 text-xs font-semibold', 'bg-coord-soft text-coord-ink' => $signal->active, 'bg-[#eef0ec] text-[#46504a]' => ! $signal->active])>
                            {{ $signal->active ? 'Aktif' : 'Tidak aktif' }} · {{ number_format($signal->strength, 2, ',', '') }}
                        </span>
                    </div>
                    <div class="h-1.5 rounded bg-[#eef0ec]" aria-hidden="true">
                        <div @class(['h-1.5 rounded', 'bg-coord' => $signal->active, 'bg-[#8a928c]' => ! $signal->active]) style="width: {{ (int) round($signal->strength * 100) }}%"></div>
                    </div>
                    <p class="text-sm leading-relaxed text-[#2f3833]">{{ SignalExplainer::sentence($signal) }}</p>
                </div>
            @endforeach
        </div>
    </section>

    @if ($pairs !== [])
        <section aria-labelledby="judul-teks" class="mt-6 rounded-xl border border-line bg-white">
            <div class="border-b border-[#e4e7e1] px-5 py-4">
                <h2 id="judul-teks" class="text-lg font-semibold">Bukti: pasangan teks mirip</h2>
                <p class="text-sm text-muted">
                    {{ count($pairs) }} pasangan dengan skor tertinggi dari {{ Format::number($text->evidence['similar_post_pairs'] ?? count($pairs)) }} pasangan di klaster ini
                </p>
            </div>
            @foreach ($pairs as $pair)
                <div class="grid grid-cols-[repeat(auto-fit,minmax(260px,1fr))] items-start gap-3 border-t border-[#e4e7e1] px-5 py-4 first:border-t-0">
                    @foreach ([$pair['post_a'], $pair['post_b']] as $postId)
                        @php $post = $posts->get($postId); @endphp
                        <div class="flex flex-col gap-1.5 rounded-lg bg-[#fafbf9] px-3.5 py-3">
                            <span class="font-mono text-xs text-muted">{{ $post?->account?->display_label ?? '–' }} · {{ Format::wib($post?->posted_at) }}</span>
                            @if ($masked)
                                <p class="text-[15px] italic text-muted">[teks disamarkan · mode presentasi]</p>
                            @elseif ($post === null)
                                <p class="text-[15px] italic text-muted">Post tidak ditemukan.</p>
                            @elseif (ContentMask::display($post->text) === ContentMask::PLACEHOLDER)
                                <p class="text-[15px] italic text-muted">{{ ContentMask::PLACEHOLDER }} <span class="text-xs not-italic">· memuat kata dari daftar penyamaran</span></p>
                            @else
                                <p class="text-[15px] leading-relaxed">{{ $post->text }}</p>
                            @endif
                        </div>
                    @endforeach
                    <div class="flex flex-col gap-1">
                        <span class="text-xs text-muted">{{ $pair['method'] === 'minhash' ? 'MinHash · nyaris identik' : 'TF-IDF · parafrase' }}</span>
                        <span class="font-mono text-2xl font-semibold">{{ number_format($pair['score'], 2, ',', '') }}</span>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    <div class="mt-6 grid grid-cols-[repeat(auto-fit,minmax(380px,1fr))] gap-6">
        @if (! empty($time?->evidence['events']))
            @php $maxAccounts = max(array_column($time->evidence['events'], 'accounts')); @endphp
            <section aria-labelledby="judul-waktu" class="rounded-xl border border-line bg-white p-5">
                <h2 id="judul-waktu" class="text-lg font-semibold">Bukti: kejadian serentak</h2>
                <p class="text-sm text-muted">Anggota yang aktif dalam jendela {{ intdiv($time->evidence['window_seconds'], 60) }} menit yang sama</p>
                <div class="mt-4 flex flex-col gap-2.5">
                    @foreach ($time->evidence['events'] as $event)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-36 shrink-0 font-mono text-xs">{{ Format::wib(\Illuminate\Support\Carbon::parse($event['start'])) }}</span>
                            <div class="h-2.5 flex-1 rounded bg-[#eef0ec]"><div class="h-2.5 rounded bg-coord" style="width: {{ (int) round($event['accounts'] / max($maxAccounts, 1) * 100) }}%"></div></div>
                            <span class="w-16 text-right font-mono">{{ $event['accounts'] }} akun</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if (! empty($infra?->evidence['domains']))
            <section aria-labelledby="judul-domain" class="rounded-xl border border-line bg-white p-5">
                <h2 id="judul-domain" class="text-lg font-semibold">Bukti: domain yang dibagikan</h2>
                <p class="text-sm text-muted">Domain khas: dibagikan banyak anggota tetapi hampir tidak oleh akun lain</p>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full min-w-[380px] text-sm">
                        <thead><tr class="text-left text-xs text-muted"><th scope="col" class="py-2 pr-3 font-medium">Domain</th><th scope="col" class="px-3 py-2 text-right font-medium">Anggota</th><th scope="col" class="px-3 py-2 text-right font-medium">Akun lain</th><th scope="col" class="py-2 pl-3 font-medium">Khas</th></tr></thead>
                        <tbody>
                            @foreach ($infra->evidence['domains'] as $domain)
                                <tr class="border-t border-[#e4e7e1]">
                                    <td class="py-2.5 pr-3 font-mono text-xs">{{ $masked ? '[domain disamarkan]' : $domain['domain'] }}</td>
                                    <td class="px-3 py-2.5 text-right font-mono">{{ $domain['accounts'] }}</td>
                                    <td class="px-3 py-2.5 text-right font-mono">{{ SignalExplainer::pct($domain['other_share']) }}</td>
                                    <td @class(['py-2.5 pl-3', 'font-medium text-coord-ink' => $domain['distinctive']])>{{ $domain['distinctive'] ? 'Ya' : 'Tidak' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>

    <section aria-labelledby="judul-anggota" class="mt-6 rounded-xl border border-line bg-white p-5">
        <h2 id="judul-anggota" class="text-lg font-semibold">Anggota ({{ $cluster->members->count() }} akun)</h2>
        <p class="text-sm text-muted">Label akun dibuat per kasus; identitas asli tidak disimpan.</p>
        <ul class="mt-3 flex flex-wrap gap-2">
            @foreach ($cluster->members as $member)
                <li class="rounded bg-paper px-2 py-1 font-mono text-xs">{{ $member->display_label }} <span class="text-muted">· {{ $member->posts_count }} post</span></li>
            @endforeach
        </ul>
    </section>

    <section id="penilaian" aria-labelledby="judul-penilaian" class="mt-6 rounded-xl border border-line bg-white p-5">
        <h2 id="judul-penilaian" class="text-lg font-semibold">Penilaian analis</h2>
        @if ($cluster->latestReview)
            <p class="mt-1 text-sm">
                Penilaian terakhir: <strong>{{ $cluster->latestReview->verdict->label() }}</strong>
                oleh {{ $cluster->latestReview->user?->name ?? 'pengguna terhapus' }}, {{ Format::wib($cluster->latestReview->created_at) }} WIB
                @if ($cluster->latestReview->note) <span class="text-muted">· “{{ $cluster->latestReview->note }}”</span> @endif
            </p>
        @else
            <p class="mt-1 text-sm text-muted">Belum dinilai. Label "Keliru" dipakai untuk kalibrasi ambang.</p>
        @endif

        <form method="POST" action="{{ route('clusters.review', [$case, $cluster]) }}" class="mt-4 flex flex-col gap-3">
            @csrf
            <fieldset class="flex flex-wrap gap-2">
                <legend class="sr-only">Pilih penilaian</legend>
                @foreach (\App\Enums\Verdict::cases() as $verdict)
                    <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-[#b9bfb6] px-4 has-[:checked]:border-ink has-[:checked]:bg-ink has-[:checked]:text-white has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-brand">
                        <input type="radio" name="verdict" value="{{ $verdict->value }}" @checked(old('verdict') === $verdict->value) class="sr-only">
                        {{ $verdict->label() }}
                    </label>
                @endforeach
            </fieldset>
            @error('verdict') <p class="text-sm text-coord-ink">{{ $message }}</p> @enderror
            <label for="note" class="text-sm font-medium">Catatan (opsional)</label>
            <textarea id="note" name="note" rows="2" maxlength="1000" class="w-full rounded-lg border border-[#b9bfb6] px-3 py-2 focus:border-brand focus:outline-2 focus:outline-brand/30">{{ old('note') }}</textarea>
            @error('note') <p class="text-sm text-coord-ink">{{ $message }}</p> @enderror
            <div><button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-brand px-5 font-medium text-white hover:bg-brand-dark">Simpan penilaian</button></div>
        </form>
    </section>
</x-layouts.app>
