@php use App\Support\Format; @endphp

<x-layouts.app title="Pengaturan privasi">
    <div class="flex max-w-3xl flex-col gap-1.5">
        <h1 class="text-3xl font-semibold">Pengaturan privasi</h1>
        <p class="text-[15px] leading-relaxed text-muted">
            Atur apakah nama akun dan @username asli boleh terlihat pada kasus data nyata.
            Setiap perubahan tercatat di log audit.
        </p>
    </div>

    @if (session('status'))
        <p role="status" class="mt-6 max-w-3xl rounded-lg bg-info-soft px-4 py-3 text-sm text-info-ink">{{ session('status') }}</p>
    @endif

    {{-- Saklar utama --}}
    <section aria-labelledby="judul-pseudonim" class="mt-8 max-w-3xl overflow-hidden rounded-xl border border-line bg-white">
        <div class="flex flex-wrap items-start justify-between gap-4 p-6">
            <div class="flex max-w-xl flex-col gap-2">
                <h2 id="judul-pseudonim" class="text-xl font-semibold">Mode pseudonim</h2>
                @if ($active)
                    <p class="inline-flex w-fit items-center gap-2 rounded-full bg-[#e3f1e7] px-3 py-1 text-sm font-semibold text-[#1f5a35]">
                        <span class="size-2 rounded-full bg-[#2f8a52]" aria-hidden="true"></span>Aktif
                    </p>
                    <p class="text-[15px] leading-relaxed text-muted">
                        Semua peran, termasuk admin dan analis, hanya melihat label seperti Akun-0001 dan mention tersamar seperti @u_3fa9c2d1.
                        Worker juga tidak menyimpan nama dari kasus baru.
                    </p>
                @else
                    <p class="inline-flex w-fit items-center gap-2 rounded-full bg-coord-soft px-3 py-1 text-sm font-semibold text-coord-ink">
                        <span class="size-2 rounded-full bg-coord" aria-hidden="true"></span>Nonaktif: nama asli terlihat
                    </p>
                    <p class="text-[15px] leading-relaxed text-muted">
                        Admin dan analis melihat nama akun dan @username asli pada kasus data nyata. Akun demo tetap tidak bisa membuka kasus data nyata.
                        Aktifkan kembali sebelum merekam video, mengambil screenshot, atau presentasi.
                    </p>
                @endif
                @if ($lastChange)
                    <p class="text-sm text-muted">
                        Terakhir diubah {{ Format::wib(\Illuminate\Support\Carbon::parse($lastChange->updated_at)) }} WIB
                        oleh {{ $lastChange->name ?? 'pengguna terhapus' }}.
                    </p>
                @endif
            </div>

            <form method="POST" action="{{ route('settings.pseudonym') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="pseudonym" value="{{ $active ? 'off' : 'on' }}">
                <button type="submit" @class([
                    'inline-flex min-h-11 items-center rounded-lg px-5 font-semibold transition-colors focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-organic',
                    'border border-[#b9bfb6] bg-white text-ink hover:bg-[#eceee9]' => $active,
                    'bg-brand text-white hover:bg-brand-dark' => ! $active,
                ])>
                    {{ $active ? 'Matikan mode pseudonim' : 'Aktifkan mode pseudonim' }}
                </button>
            </form>
        </div>

        {{-- Lapisan lain yang ikut menentukan; ditampilkan supaya admin tidak salah paham kenapa nama tidak muncul. --}}
        <dl class="grid gap-px border-t border-line bg-line text-sm sm:grid-cols-3">
            <div class="bg-paper px-5 py-3">
                <dt class="text-muted">Mode aplikasi</dt>
                <dd class="mt-0.5 font-medium">{{ $isLocal ? 'Lokal' : 'Publik (nama tidak pernah disimpan)' }}</dd>
            </div>
            <div class="bg-paper px-5 py-3">
                <dt class="text-muted">Izin di .env</dt>
                <dd class="mt-0.5 font-medium">{{ $envAllows ? 'Diizinkan' : 'Dimatikan (RADAR_SHOW_ACCOUNT_NAMES=false)' }}</dd>
            </div>
            <div class="bg-paper px-5 py-3">
                <dt class="text-muted">Mode presentasi</dt>
                <dd class="mt-0.5 font-medium">{{ $presentationMask ? 'Menyala (nama dan konten disamarkan)' : 'Mati' }}</dd>
            </div>
        </dl>
    </section>

    {{-- Penghapusan permanen --}}
    <section aria-labelledby="judul-hapus" class="mt-6 max-w-3xl rounded-xl border border-line bg-white p-6">
        <h2 id="judul-hapus" class="text-xl font-semibold">Hapus nama yang sudah tersimpan</h2>
        <p class="mt-2 text-[15px] leading-relaxed text-muted">
            Mode pseudonim hanya menyembunyikan. Untuk benar-benar membuang nama dari database, misalnya sebelum menyerahkan atau menyalin data, hapus di sini.
            Hasil analisis tidak berubah, karena analisis hanya memakai kode akun dan teks tersamar.
        </p>

        <p class="mt-4 text-[15px]">
            Tersimpan saat ini: <span class="font-mono font-semibold">{{ Format::number($stored['accounts']) }}</span> nama akun
            dan <span class="font-mono font-semibold">{{ Format::number($stored['posts']) }}</span> post berisi @username asli.
        </p>

        @if ($stored['accounts'] + $stored['posts'] > 0)
            <form method="POST" action="{{ route('settings.purge') }}" class="mt-4 flex flex-col gap-3">
                @csrf
                @method('DELETE')
                <label class="flex items-start gap-3 text-[15px]">
                    <input type="checkbox" name="confirm" value="1" class="mt-1 size-4 accent-coord" required>
                    <span>Saya paham penghapusan ini permanen. Nama tidak bisa dipulihkan kecuali data diimpor ulang.</span>
                </label>
                @error('confirm')
                    <p class="text-sm font-medium text-coord-ink">{{ $message }}</p>
                @enderror
                <button type="submit" class="inline-flex min-h-11 w-fit items-center rounded-lg bg-coord px-5 font-semibold text-white transition-colors hover:bg-coord-ink focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-organic">
                    Hapus semua nama tersimpan
                </button>
            </form>
        @endif
    </section>
</x-layouts.app>
