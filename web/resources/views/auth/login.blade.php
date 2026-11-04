<x-layouts.guest title="Masuk">
    <div class="flex items-center gap-2.5">
        <x-logo class="size-8 text-coord" />
        <span class="text-xl font-semibold">Radar Narasi</span>
    </div>

    <section class="rounded-xl border border-line bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-semibold">Masuk</h1>
        <p class="mt-1 text-sm text-muted">Gunakan akun yang diberikan tim. Tidak ada pendaftaran publik.</p>

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 flex flex-col gap-4" novalidate>
            @csrf

            <div class="flex flex-col gap-1.5">
                <label for="email" class="text-sm font-medium">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}"
                       required autofocus autocomplete="username"
                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                       class="min-h-11 rounded-lg border border-[#b9bfb6] px-3 text-base focus:border-brand focus:outline-2 focus:outline-brand/30">
                @error('email')
                    <p id="email-error" class="text-sm text-coord-ink">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="password" class="text-sm font-medium">Kata sandi</label>
                <input id="password" name="password" type="password"
                       required autocomplete="current-password"
                       @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                       class="min-h-11 rounded-lg border border-[#b9bfb6] px-3 text-base focus:border-brand focus:outline-2 focus:outline-brand/30">
                @error('password')
                    <p id="password-error" class="text-sm text-coord-ink">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="mt-2 inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-5 font-medium text-white hover:bg-brand-dark">
                Masuk
            </button>
        </form>
    </section>
</x-layouts.guest>
