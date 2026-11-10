@php
    use App\Support\AuditAction;
    use App\Support\Format;
@endphp

<x-layouts.app title="Log audit">
    <div class="flex max-w-3xl flex-col gap-1.5">
        <h1 class="text-3xl font-semibold">Log audit</h1>
        <p class="text-[15px] leading-relaxed text-muted">
            Setiap akses ke kasus, bukti, kunci jawaban, unggahan, dan penilaian tercatat, termasuk yang ditolak.
            Baris tidak bisa diubah atau dihapus dari aplikasi.
        </p>
    </div>

    <section aria-label="Ringkasan 24 jam terakhir" class="mt-6 grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-4">
        <div class="flex flex-col gap-1 rounded-xl border border-line bg-white px-5 py-4">
            <span class="text-xs text-muted">Aktivitas 24 jam terakhir</span>
            <span class="text-2xl font-semibold">{{ Format::number($stats['total']) }}</span>
        </div>
        <div class="flex flex-col gap-1 rounded-xl border border-line bg-white px-5 py-4">
            <span class="text-xs text-muted">Akses ditolak</span>
            <span @class(['text-2xl font-semibold', 'text-coord-ink' => $stats['denied'] > 0])>{{ Format::number($stats['denied']) }}</span>
        </div>
        <div class="flex flex-col gap-1 rounded-xl border border-line bg-white px-5 py-4">
            <span class="text-xs text-muted">Gagal masuk</span>
            <span @class(['text-2xl font-semibold', 'text-coord-ink' => $stats['failed_logins'] > 0])>{{ Format::number($stats['failed_logins']) }}</span>
        </div>
    </section>

    <form method="GET" action="{{ route('audit.index') }}" class="mt-6 flex flex-wrap items-end gap-4 rounded-xl border border-line bg-white p-5">
        <div class="flex flex-col gap-1.5">
            <label for="user" class="text-sm font-medium">Pengguna</label>
            <select id="user" name="user" class="min-h-11 rounded-lg border border-[#b9bfb6] bg-white px-3">
                <option value="">Semua</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((int) ($filters['user'] ?? 0) === $user->id)>{{ $user->name }} ({{ $user->role->label() }})</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1.5">
            <label for="action" class="text-sm font-medium">Aksi</label>
            <select id="action" name="action" class="min-h-11 rounded-lg border border-[#b9bfb6] bg-white px-3">
                <option value="">Semua</option>
                @foreach ($actions as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['action'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <label class="flex min-h-11 items-center gap-2 text-sm">
            <input type="checkbox" name="denied" value="1" @checked($filters['denied'] ?? false) class="size-4 accent-brand">
            Hanya akses ditolak
        </label>
        <div class="flex gap-2">
            <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-brand px-5 font-medium text-white hover:bg-brand-dark">Terapkan</button>
            @if (array_filter($filters))
                <a href="{{ route('audit.index') }}" class="inline-flex min-h-11 items-center rounded-lg border border-[#b9bfb6] bg-white px-5 hover:bg-paper">Reset</a>
            @endif
        </div>
    </form>

    <section aria-labelledby="judul-log" class="mt-6 rounded-xl border border-line bg-white">
        <div class="border-b border-[#e4e7e1] px-5 py-4">
            <h2 id="judul-log" class="text-lg font-semibold">Catatan</h2>
            <p class="text-sm text-muted">{{ Format::number($logs->total()) }} baris, terbaru di atas. Waktu dalam WIB.</p>
        </div>

        @if ($logs->isEmpty())
            <p class="px-5 py-10 text-center text-sm text-muted">Tidak ada catatan yang cocok dengan filter.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-sm">
                    <thead>
                        <tr class="text-left text-xs text-muted">
                            <th scope="col" class="px-5 py-3 font-medium">Waktu</th>
                            <th scope="col" class="px-3 py-3 font-medium">Pengguna</th>
                            <th scope="col" class="px-3 py-3 font-medium">Aksi</th>
                            <th scope="col" class="px-3 py-3 font-medium">Kasus</th>
                            <th scope="col" class="px-3 py-3 font-medium">Hasil</th>
                            <th scope="col" class="px-5 py-3 font-medium">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr @class(['border-t border-[#e4e7e1]', 'bg-coord-soft/40' => $log->isDenied() || $log->action === 'login_gagal'])>
                                <td class="whitespace-nowrap px-5 py-2.5 font-mono text-xs">{{ Format::wib($log->created_at) }}</td>
                                <td class="px-3 py-2.5">
                                    @if ($log->user)
                                        {{ $log->user->name }} <span class="text-xs text-muted">· {{ $log->user->role->label() }}</span>
                                    @elseif ($log->user_id === null && $log->action === 'login_gagal')
                                        <span class="text-muted">tidak dikenal</span>
                                    @else
                                        <span class="text-muted">pengguna terhapus</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    {{ AuditAction::label($log->action) }}
                                    <span class="block font-mono text-[11px] text-muted">{{ $log->action }}</span>
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($log->case)
                                        <a href="{{ route('cases.show', $log->case) }}" class="text-organic hover:underline">{{ $log->case->displayName() }}</a>
                                        <span class="font-mono text-xs text-muted">#{{ $log->case->id }}</span>
                                    @else
                                        <span class="text-muted">–</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($log->isDenied())
                                        <span class="rounded-full bg-coord-soft px-2 py-0.5 text-xs font-semibold text-coord-ink">Ditolak · {{ $log->status }}</span>
                                    @elseif ($log->action === 'login_gagal')
                                        <span class="rounded-full bg-coord-soft px-2 py-0.5 text-xs font-semibold text-coord-ink">Gagal</span>
                                    @elseif ($log->status !== null)
                                        <span class="rounded-full bg-[#eef0ec] px-2 py-0.5 text-xs text-[#46504a]">Berhasil · {{ $log->status }}</span>
                                    @else
                                        <span class="rounded-full bg-[#eef0ec] px-2 py-0.5 text-xs text-[#46504a]">Tercatat</span>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 font-mono text-xs">{{ $log->ip }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="mt-6">{{ $logs->links() }}</div>
</x-layouts.app>
