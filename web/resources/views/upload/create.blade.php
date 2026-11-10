<x-layouts.app title="Coba Sendiri">
    <div class="flex max-w-3xl flex-col gap-1.5">
        <h1 class="text-3xl font-semibold">Coba Sendiri</h1>
        <p class="text-[15px] leading-relaxed text-muted">
            Unggah percakapan dalam format CSV. Radar Narasi mencari kelompok akun yang bergerak bersama,
            lalu hasilnya muncul sebagai kasus baru.
        </p>
    </div>

    <div class="mt-8 flex flex-wrap items-start gap-6">
        <form method="POST" action="{{ route('upload.store') }}" enctype="multipart/form-data"
              class="flex min-w-0 flex-[3_1_520px] flex-col gap-5 rounded-xl border border-line bg-white p-6" novalidate>
            @csrf
            <h2 class="text-lg font-semibold">Unggah file</h2>

            <div class="flex flex-col gap-1.5">
                <label for="file" class="text-sm font-medium">File CSV</label>
                <input id="file" name="file" type="file" accept=".csv,text/csv" required
                       aria-describedby="file-help @error('file') file-error @enderror"
                       class="rounded-lg border-2 border-dashed border-[#b9bfb6] bg-[#fafbf9] p-4 text-sm">
                <p id="file-help" class="text-sm text-muted">Maksimal 2 MB dan {{ number_format($maxRows, 0, ',', '.') }} baris.</p>
                @if ($errors->has('file'))
                    <div id="file-error" role="alert" class="rounded-lg bg-coord-soft px-4 py-3 text-sm text-[#6e3105]">
                        <ul class="list-disc pl-4">
                            @foreach ($errors->get('file') as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="name" class="text-sm font-medium">Nama kasus (opsional)</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="200"
                       class="min-h-11 rounded-lg border border-[#b9bfb6] px-3 focus:border-brand focus:outline-2 focus:outline-brand/30">
                @error('name') <p class="text-sm text-coord-ink">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="consent" value="1" @checked(old('consent')) class="mt-0.5 size-4 accent-brand">
                <span>Saya berhak memakai data ini, dan memahami bahwa kolom akun diganti pseudonim lalu data dihapus otomatis setelah {{ $retentionHours }} jam.</span>
            </label>
            @error('consent') <p class="text-sm text-coord-ink">{{ $message }}</p> @enderror

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-brand px-5 font-medium text-white hover:bg-brand-dark">Analisis</button>
                <a href="{{ route('upload.sample') }}" class="inline-flex min-h-11 items-center rounded-lg border border-[#b9bfb6] bg-white px-5 hover:bg-paper">Unduh file contoh</a>
            </div>

            <div class="flex flex-col gap-2">
                <h3 class="font-semibold">Kolom</h3>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[520px] text-sm">
                        <thead><tr class="text-left text-xs text-muted"><th scope="col" class="py-2 pr-3 font-medium">Kolom</th><th scope="col" class="px-3 py-2 font-medium">Wajib</th><th scope="col" class="py-2 pl-3 font-medium">Isi</th></tr></thead>
                        <tbody>
                            <tr class="border-t border-[#e4e7e1]"><td class="py-2.5 pr-3 font-mono">akun</td><td class="px-3 py-2.5">Ya</td><td class="py-2.5 pl-3">Penanda akun apa pun; langsung diganti pseudonim</td></tr>
                            <tr class="border-t border-[#e4e7e1]"><td class="py-2.5 pr-3 font-mono">teks</td><td class="px-3 py-2.5">Ya</td><td class="py-2.5 pl-3">Isi post, maksimal 1.000 karakter</td></tr>
                            <tr class="border-t border-[#e4e7e1]"><td class="py-2.5 pr-3 font-mono">waktu</td><td class="px-3 py-2.5">Ya</td><td class="py-2.5 pl-3">ISO 8601, misalnya <span class="font-mono">2026-11-01T19:02:00+07:00</span>; tanpa zona dianggap WIB</td></tr>
                            <tr class="border-t border-[#e4e7e1]"><td class="py-2.5 pr-3 font-mono">url</td><td class="px-3 py-2.5 text-muted">Opsional</td><td class="py-2.5 pl-3">Tautan di post; mengaktifkan sinyal infrastruktur</td></tr>
                            <tr class="border-t border-[#e4e7e1]"><td class="py-2.5 pr-3 font-mono">reply_to</td><td class="px-3 py-2.5 text-muted">Opsional</td><td class="py-2.5 pl-3">Penanda post yang dibalas</td></tr>
                            <tr class="border-t border-[#e4e7e1]"><td class="py-2.5 pr-3 font-mono">tanggal_akun</td><td class="px-3 py-2.5 text-muted">Opsional</td><td class="py-2.5 pl-3">YYYY-MM-DD; mengaktifkan sinyal profil</td></tr>
                        </tbody>
                    </table>
                </div>
                <pre class="overflow-x-auto rounded-lg bg-ink p-4 font-mono text-xs leading-relaxed text-[#e8ece6]">akun,teks,waktu,url
warga_a,"Pasar Lama harus dipindah.",2026-11-01T19:01:00+07:00,
warga_b,"Pasar Lama harus dipindah!",2026-11-01T19:02:00+07:00,https://berita.example/x</pre>
            </div>
        </form>

        <aside class="flex min-w-0 flex-[2_1_320px] flex-col gap-4">
            <section class="rounded-xl border border-line bg-white p-5">
                <h2 class="font-semibold">Sinyal yang bisa dihitung</h2>
                <p class="mt-2 text-sm leading-relaxed text-[#2f3833]">Dengan tiga kolom wajib, sistem menghitung sinyal teks dan waktu. Tambahkan <span class="font-mono">url</span> dan <span class="font-mono">tanggal_akun</span> agar keempat sinyal tersedia.</p>
            </section>
            <section class="rounded-xl border border-line bg-white p-5">
                <h2 class="font-semibold">Privasi data Anda</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm leading-relaxed text-[#2f3833]">
                    <li>Kolom akun diganti pseudonim sebelum disimpan.</li>
                    <li>File asli dihapus setelah dibaca; kasus dihapus otomatis setelah {{ $retentionHours }} jam.</li>
                    <li>Unggah hanya data yang Anda berhak gunakan.</li>
                </ul>
            </section>
        </aside>
    </div>
</x-layouts.app>
