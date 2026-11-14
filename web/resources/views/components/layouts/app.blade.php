@props(['title' => null])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · SyncTrace' : 'SyncTrace' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-paper font-sans text-ink antialiased print:bg-white">
    <header class="sticky top-0 z-40 bg-ink text-paper print:hidden">
        <div class="mx-auto flex min-h-16 max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-2.5">
            <a href="{{ route('cases.index') }}" class="flex items-center gap-2.5 text-paper">
                <x-logo class="size-7 text-[#e7b27a]" />
                <span class="text-lg font-semibold">SyncTrace</span>
            </a>

            <nav aria-label="Navigasi utama" class="flex flex-wrap gap-1">
                <a href="{{ route('cases.index') }}"
                   @class([
                       'inline-flex min-h-11 items-center rounded-md px-3.5 text-sm',
                       'bg-white/10 font-medium text-white' => request()->routeIs('cases.*'),
                       'text-[#d7dcd6] hover:text-white' => ! request()->routeIs('cases.*'),
                   ])
                   @if (request()->routeIs('cases.*')) aria-current="page" @endif>
                    Kasus
                </a>
                <a href="{{ route('upload.create') }}"
                   @class([
                       'inline-flex min-h-11 items-center rounded-md px-3.5 text-sm',
                       'bg-white/10 font-medium text-white' => request()->routeIs('upload.*'),
                       'text-[#d7dcd6] hover:text-white' => ! request()->routeIs('upload.*'),
                   ])
                   @if (request()->routeIs('upload.*')) aria-current="page" @endif>
                    Coba Sendiri
                </a>
                <a href="{{ route('evaluation.show') }}"
                   @class([
                       'inline-flex min-h-11 items-center rounded-md px-3.5 text-sm',
                       'bg-white/10 font-medium text-white' => request()->routeIs('evaluation.*'),
                       'text-[#d7dcd6] hover:text-white' => ! request()->routeIs('evaluation.*'),
                   ])
                   @if (request()->routeIs('evaluation.*')) aria-current="page" @endif>
                    Evaluasi
                </a>
                @can('view-audit-log')
                    <a href="{{ route('audit.index') }}"
                       @class([
                           'inline-flex min-h-11 items-center rounded-md px-3.5 text-sm',
                           'bg-white/10 font-medium text-white' => request()->routeIs('audit.*'),
                           'text-[#d7dcd6] hover:text-white' => ! request()->routeIs('audit.*'),
                       ])
                       @if (request()->routeIs('audit.*')) aria-current="page" @endif>
                        Log audit
                    </a>
                @endcan
                @can('manage-privacy')
                    <a href="{{ route('settings.privacy') }}"
                       @class([
                           'inline-flex min-h-11 items-center rounded-md px-3.5 text-sm',
                           'bg-white/10 font-medium text-white' => request()->routeIs('settings.*'),
                           'text-[#d7dcd6] hover:text-white' => ! request()->routeIs('settings.*'),
                       ])
                       @if (request()->routeIs('settings.*')) aria-current="page" @endif>
                        Privasi
                    </a>
                @endcan
            </nav>

            <div class="flex items-center gap-3 text-sm text-[#d7dcd6]">
                @if (\App\Support\AppMode::isLocal() && auth()->user()->role !== \App\Enums\Role::Demo)
                    {{-- Status nama akun selalu terlihat oleh yang berhak melihat nama, supaya tidak ada yang lupa sebelum merekam. --}}
                    @if (\App\Support\Pseudonym::active())
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-[#2f4a3a] px-2.5 py-0.5 text-xs font-medium text-[#cfe8d6]">
                            <span class="size-1.5 rounded-full bg-[#7fd39b]" aria-hidden="true"></span>Pseudonim aktif
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-[#5a3b1c] px-2.5 py-0.5 text-xs font-medium text-[#ffd9b0]">
                            <span class="size-1.5 rounded-full bg-[#f3a45a]" aria-hidden="true"></span>Nama asli terlihat
                        </span>
                    @endif
                @endif
                <span>{{ auth()->user()->name }}</span>
                <span class="rounded-full border border-[#5c6a61] px-2 py-0.5 text-xs">
                    peran: {{ auth()->user()->role->label() }}
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center rounded-md px-3 text-sm text-[#d7dcd6] hover:bg-white/10 hover:text-white">
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </header>

    <div class="print:hidden"><x-mode-banner /></div>

    <main class="mx-auto max-w-7xl px-6 py-8 print:max-w-none print:p-0">
        {{ $slot }}
    </main>
</body>
</html>
