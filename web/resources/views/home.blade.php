@php use App\Support\Format; @endphp
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SyncTrace · Deteksi dini kampanye terkoordinasi</title>
    <meta name="description" content="SyncTrace membantu analis membedakan isu yang ramai karena banyak orang dari isu yang ramai karena sedikit akun yang bergerak serempak.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-paper font-sans text-ink antialiased">
    <a href="#isi" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:font-medium">Lompat ke isi</a>
    <x-mode-banner />

    <header class="mx-auto flex max-w-6xl items-center justify-between px-5 py-5 sm:px-8">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 rounded-lg focus-visible:outline-3 focus-visible:outline-organic">
            <x-logo class="size-8 text-coord" />
            <span class="text-xl font-semibold tracking-tight">SyncTrace</span>
        </a>
        <nav aria-label="Navigasi halaman" class="flex items-center gap-1 sm:gap-2">
            <a href="#cara-kerja" class="hidden min-h-11 items-center rounded-lg px-3 text-[15px] font-medium text-muted hover:text-ink sm:inline-flex">Cara kerja</a>
            <a href="#evaluasi" class="hidden min-h-11 items-center rounded-lg px-3 text-[15px] font-medium text-muted hover:text-ink sm:inline-flex">Evaluasi</a>
            <a href="#privasi" class="hidden min-h-11 items-center rounded-lg px-3 text-[15px] font-medium text-muted hover:text-ink sm:inline-flex">Privasi</a>
            <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-lg bg-brand px-5 font-semibold text-white transition-colors hover:bg-brand-dark focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-organic">Masuk</a>
        </nav>
    </header>

    <main id="isi">
        {{-- Pembuka: pertanyaan yang dijawab sistem, lalu peraga langsungnya. --}}
        <section class="mx-auto max-w-6xl px-5 pb-10 pt-10 sm:px-8 sm:pt-16">
            <p class="home-rise font-mono text-sm text-coord-ink">Deteksi dini kampanye terkoordinasi di media sosial</p>
            <h1 class="home-rise home-rise-2 mt-4 max-w-4xl text-4xl font-semibold leading-[1.08] tracking-tight text-balance sm:text-6xl">
                Ramai karena banyak orang, atau karena segelintir akun yang
                <span class="text-coord">bergerak serempak</span>?
            </h1>
            <p class="home-rise home-rise-3 mt-6 max-w-2xl text-lg leading-relaxed text-muted">
                SyncTrace membaca percakapan publik dan mencari kelompok akun yang berulang kali menulis hal serupa pada waktu yang sama.
                Yang dideteksi adalah koordinasi, bukan bot dan bukan opini.
            </p>
            <div class="home-rise home-rise-3 mt-8 flex flex-wrap gap-3">
                <a href="{{ route('login') }}" class="inline-flex min-h-12 items-center rounded-lg bg-brand px-6 text-base font-semibold text-white transition-colors hover:bg-brand-dark focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-organic">Masuk ke aplikasi</a>
                <a href="#cara-kerja" class="inline-flex min-h-12 items-center rounded-lg border border-[#b9bfb6] bg-white px-6 text-base font-medium transition-colors hover:bg-[#eceee9] focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-organic">Lihat cara kerja</a>
            </div>
        </section>

        <section aria-label="Peraga" class="home-rise home-rise-4 mx-auto max-w-6xl px-5 sm:px-8">
            <div class="overflow-hidden rounded-2xl bg-ink shadow-xl shadow-ink/15">
                <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 border-b border-white/10 px-5 py-3.5 sm:px-7">
                    <p class="font-mono text-[13px] text-[#aab5ae]">Satu jam percakapan · sepuluh akun · jendela 5 menit</p>
                    <ul class="flex gap-5 text-[13px] text-[#d5dcd6]" aria-label="Keterangan">
                        <li class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-[#6fa8e8]" aria-hidden="true"></span>Menulis sendiri-sendiri</li>
                        <li class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-[#f3a45a]" aria-hidden="true"></span>Menulis serempak</li>
                    </ul>
                </div>
                <div class="overflow-x-auto px-2 py-4 sm:px-4">
                    <x-sync-strip />
                </div>
                <p class="border-t border-white/10 px-5 py-4 text-[15px] leading-relaxed text-[#d5dcd6] sm:px-7">
                    Enam akun menulis pada waktu yang acak. Empat akun lain muncul dalam lima menit yang sama, tiga kali berturut-turut.
                    Sekali bisa kebetulan; berulang kali adalah pola. <span class="text-[#8d9a93]">Ilustrasi, bukan data kasus.</span>
                </p>
            </div>
        </section>

        {{-- Masalah --}}
        <section class="mx-auto max-w-6xl px-5 py-20 sm:px-8 sm:py-28">
            <div class="home-reveal max-w-3xl">
                <h2 class="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">Buzzer bukan bot. Itu sebabnya alat yang ada tidak melihatnya.</h2>
                <p class="mt-4 text-lg leading-relaxed text-muted">
                    Operasi pengaruh memakai banyak akun yang sebagian dikelola manusia, digerakkan bersama agar sebuah narasi tampak didukung publik luas.
                    Tiga alat yang umum dipakai menjawab pertanyaan lain.
                </p>
            </div>
            <div class="mt-10 grid gap-px overflow-hidden rounded-xl border border-line bg-line md:grid-cols-3">
                @foreach ([
                    ['Analisis sentimen', 'Mengukur nada percakapan.', 'Seribu warga yang marah dan lima puluh operator yang menyalin pesan terlihat sama.'],
                    ['Deteksi bot', 'Mencari akun otomatis.', 'Buzzer dikelola manusia: punya foto, riwayat, dan gaya bahasa yang wajar.'],
                    ['Pemeriksa fakta', 'Menilai benar-salahnya isi.', 'Kampanye terkoordinasi bisa memakai fakta yang benar. Masalahnya ada pada siapa yang mendorongnya.'],
                ] as [$tool, $does, $miss])
                    <div class="home-reveal bg-white p-6">
                        <h3 class="text-lg font-semibold">{{ $tool }}</h3>
                        <p class="mt-1 text-[15px] text-muted">{{ $does }}</p>
                        <p class="mt-4 border-t border-line pt-4 text-[15px] leading-relaxed">{{ $miss }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Cara kerja --}}
        <section id="cara-kerja" class="scroll-mt-6 border-y border-line bg-white">
            <div class="mx-auto grid max-w-6xl gap-12 px-5 py-20 sm:px-8 sm:py-28 lg:grid-cols-[5fr_6fr]">
                <div class="home-reveal">
                    <h2 class="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">Empat sinyal. Klaster ditandai bila minimal dua muncul bersamaan.</h2>
                    <p class="mt-4 text-lg leading-relaxed text-muted">
                        Tiap sinyal sendirian sering terjadi secara alami: orang mengutip kalimat yang sama, atau ramai-ramai berkomentar saat ada berita.
                        Karena itu satu sinyal tidak pernah cukup.
                    </p>
                    <p class="mt-4 text-[15px] leading-relaxed text-muted">
                        Setiap sinyal dibandingkan dengan kebiasaan seluruh percakapan dalam kasus yang sama, bukan dengan ambang yang berlaku umum.
                        Coba nyalakan dan matikan sinyal di samping.
                    </p>
                </div>

                <div class="signal-demo home-reveal">
                    <input type="checkbox" id="sinyal-teks" class="sr-only" checked>
                    <input type="checkbox" id="sinyal-waktu" class="sr-only" checked>
                    <input type="checkbox" id="sinyal-profil" class="sr-only">
                    <input type="checkbox" id="sinyal-tautan" class="sr-only">

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ([
                            ['sinyal-teks', 'Teks serupa', 'Banyak akun menulis kalimat yang nyaris sama, termasuk yang sudah diubah sedikit.'],
                            ['sinyal-waktu', 'Waktu serentak', 'Akun yang sama berulang kali aktif dalam jendela lima menit yang sama.'],
                            ['sinyal-profil', 'Akun dibuat berdekatan', 'Sebagian besar anggota klaster dibuat dalam rentang hari yang sama.'],
                            ['sinyal-tautan', 'Tautan yang sama', 'Klaster mendorong domain yang hampir tidak dibagikan akun lain.'],
                        ] as [$id, $name, $text])
                            <label for="{{ $id }}" class="flex cursor-pointer flex-col gap-2 rounded-xl border border-[#b9bfb6] bg-paper p-5">
                                <span class="flex items-center justify-between gap-3">
                                    <span class="text-lg font-semibold">{{ $name }}</span>
                                    <span data-state class="grid size-6 shrink-0 place-items-center rounded-md border border-[#8a928c] bg-white text-transparent" aria-hidden="true">
                                        <svg viewBox="0 0 12 10" class="h-2.5 w-3 fill-none stroke-current stroke-2"><path d="M1 5l3.5 3.5L11 1"/></svg>
                                    </span>
                                </span>
                                <span class="text-[15px] leading-relaxed text-muted">{{ $text }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-4 rounded-xl border border-line px-5 py-4" role="status">
                        <p data-verdict="yes" class="font-semibold text-coord-ink">Ditandai untuk diperiksa analis.
                            <span class="mt-1 block text-[15px] font-normal text-ink">Dua sinyal atau lebih muncul pada kelompok akun yang sama.</span></p>
                        <p data-verdict="no" class="font-semibold">Tidak ditandai.
                            <span class="mt-1 block text-[15px] font-normal text-muted">Kurang dari dua sinyal. Pola seperti ini wajar terjadi tanpa koordinasi.</span></p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Keluaran --}}
        <section class="mx-auto max-w-6xl px-5 py-20 sm:px-8 sm:py-28">
            <div class="home-reveal max-w-3xl">
                <h2 class="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">Bukti yang bisa diperiksa, bukan skor yang harus dipercaya.</h2>
                <p class="mt-4 text-lg leading-relaxed text-muted">Sistem menunjukkan alasannya. Analis yang menilai dan memutuskan; tidak ada tindakan otomatis.</p>
            </div>
            <dl class="mt-10 grid gap-x-10 gap-y-8 sm:grid-cols-2">
                @foreach ([
                    ['Graf koordinasi', 'Siapa bergerak bersama siapa. Garis menunjukkan apakah dua akun terhubung karena teks, waktu, atau keduanya.'],
                    ['Lini waktu dengan penanda lonjakan', 'Kapan percakapan naik tidak wajar, dan berapa bagiannya yang berasal dari klaster yang ditandai.'],
                    ['Narasi utama tiap klaster', 'Kalimat yang paling banyak didorong, lengkap dengan pasangan post yang mirip sebagai bukti.'],
                    ['Ringkasan satu halaman', 'Kesimpulan lebih dulu, siap dicetak untuk pimpinan, dengan tingkat keyakinan dan keterbatasannya.'],
                ] as [$term, $definition])
                    <div class="home-reveal border-t-2 border-ink pt-4">
                        <dt class="text-xl font-semibold">{{ $term }}</dt>
                        <dd class="mt-2 text-[17px] leading-relaxed text-muted">{{ $definition }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="home-reveal mt-10 max-w-3xl text-[15px] leading-relaxed text-muted">
                Sumber data: komentar YouTube lewat API resmi, serta impor file untuk X, Instagram, TikTok, dan CSV.
            </p>
        </section>

        {{-- Evaluasi --}}
        <section id="evaluasi" class="scroll-mt-6 bg-ink text-white">
            <div class="mx-auto max-w-6xl px-5 py-20 sm:px-8 sm:py-28">
                <div class="home-reveal max-w-3xl">
                    <h2 class="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">Diuji dengan klaster buatan. Titik gagalnya ikut ditampilkan.</h2>
                    <p class="mt-4 text-lg leading-relaxed text-[#c3ccc5]">
                        Kami menanam kelompok akun terkoordinasi ke dalam percakapan sintetis, lalu memeriksa apakah sistem menemukannya.
                        Ambang disetel pada satu kelompok dataset, lalu diuji pada kelompok lain yang belum pernah dilihat.
                    </p>
                </div>

                @if ($evaluation)
                    <dl class="home-reveal mt-10 grid gap-px overflow-hidden rounded-xl bg-white/15 sm:grid-cols-3">
                        <div class="bg-ink p-6">
                            <dt class="text-[15px] text-[#c3ccc5]">Klaster tanaman ditemukan</dt>
                            <dd class="mt-2 font-mono text-5xl font-semibold tracking-tight">{{ $evaluation['caught'] }}<span class="text-2xl text-[#8d9a93]"> dari {{ $evaluation['planted'] }}</span></dd>
                        </div>
                        <div class="bg-ink p-6">
                            <dt class="text-[15px] text-[#c3ccc5]">Kelompok organik yang salah ditandai</dt>
                            <dd class="mt-2 font-mono text-5xl font-semibold tracking-tight">{{ $evaluation['false_flags'] }}</dd>
                        </div>
                        <div class="bg-ink p-6">
                            <dt class="text-[15px] text-[#c3ccc5]">Dataset uji</dt>
                            <dd class="mt-2 font-mono text-5xl font-semibold tracking-tight">{{ $evaluation['datasets'] }}</dd>
                        </div>
                    </dl>
                @endif

                <div class="home-reveal mt-10 grid gap-8 md:grid-cols-2">
                    <div>
                        <h3 class="text-lg font-semibold text-[#f3a45a]">Kapan sistem ini gagal</h3>
                        <ul class="mt-3 flex flex-col gap-2 text-[17px] leading-relaxed text-[#e3e8e3]">
                            <li>Bila pelaku menyebar tulisan mereka lebih dari 15 menit.</li>
                            <li>Bila kelompok hanya bergerak bersama satu atau dua kali.</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-[#f3a45a]">Yang tidak kami klaim</h3>
                        <ul class="mt-3 flex flex-col gap-2 text-[17px] leading-relaxed text-[#e3e8e3]">
                            <li>Semua angka berasal dari data sintetis, bukan akurasi di dunia nyata.</li>
                            <li>Klaster yang ditandai adalah petunjuk untuk diperiksa, bukan tuduhan.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        {{-- Privasi --}}
        <section id="privasi" class="mx-auto max-w-6xl scroll-mt-6 px-5 py-20 sm:px-8 sm:py-28">
            <div class="grid gap-12 lg:grid-cols-[5fr_6fr]">
                <div class="home-reveal">
                    <h2 class="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">Membaca pola percakapan, bukan memantau orang.</h2>
                    <p class="mt-4 text-lg leading-relaxed text-muted">Pagar ini ada di kode, bukan hanya di kebijakan.</p>
                </div>
                <ul class="home-reveal flex flex-col divide-y divide-line border-y border-line text-[17px] leading-relaxed">
                    <li class="py-4"><span class="font-semibold">Pseudonim sejak data masuk.</span> ID akun diganti kode acak sebelum disimpan; label seperti Akun-0001 dipakai di seluruh tampilan.</li>
                    <li class="py-4"><span class="font-semibold">Nama akun hanya untuk analis berwenang.</span> Hanya di instalasi lokal, hanya untuk admin dan analis, dan setiap kali dibuka tercatat di log audit.</li>
                    <li class="py-4"><span class="font-semibold">Tidak ada profil per orang.</span> Yang dianalisis adalah kelompok dan kontennya, bukan riwayat satu akun.</li>
                    <li class="py-4"><span class="font-semibold">Konten sensitif disamarkan.</span> Kata bermuatan SARA ditutup secara bawaan di layar.</li>
                </ul>
            </div>
        </section>
    </main>

    <footer class="border-t border-line bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-6 px-5 py-10 sm:px-8">
            <div>
                <div class="flex items-center gap-2.5">
                    <x-logo class="size-7 text-coord" />
                    <span class="text-lg font-semibold tracking-tight">SyncTrace</span>
                </div>
                <p class="mt-2 text-sm text-muted">Kartika Cyber Hackathon 2026 · Kategori Siber Sosial</p>
            </div>
            <a href="{{ route('login') }}" class="inline-flex min-h-12 items-center rounded-lg bg-brand px-6 font-semibold text-white transition-colors hover:bg-brand-dark focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-organic">Masuk ke aplikasi</a>
        </div>
    </footer>
</body>
</html>
