@props(['title' => null])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · Radar Narasi' : 'Radar Narasi' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper font-sans text-ink antialiased">
    <x-mode-banner />

    <main class="mx-auto flex max-w-md flex-col gap-6 px-6 py-16">
        {{ $slot }}
    </main>
</body>
</html>
