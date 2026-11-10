@if (\App\Support\AppMode::isPublic())
    <div class="border-b border-info-line bg-info-soft text-info-ink">
        <p class="mx-auto max-w-7xl px-6 py-2.5 text-sm leading-relaxed">
            <strong class="font-semibold">Mode publik.</strong>
            Semua data di sini sintetis atau unggahan Anda sendiri. Identitas akun dipseudonimisasi, dan post yang memuat kata kasar atau hinaan SARA dari daftar penyamaran tampil sebagai [konten disamarkan].
        </p>
    </div>
@else
    <div class="border-b border-amber-300 bg-amber-50 text-amber-900">
        <p class="mx-auto max-w-7xl px-6 py-2.5 text-sm leading-relaxed">
            <strong class="font-semibold">Mode lokal.</strong>
            Pengumpulan data nyata aktif. Jangan tampilkan layar ini di presentasi atau rekaman publik.
        </p>
    </div>
@endif
