<?php

namespace App\Http\Middleware;

use App\Support\AppMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menolak rute pengumpulan data di mode publik. Pemeriksaan ini di sisi server,
 * bukan sekadar menyembunyikan tombol di tampilan.
 */
class EnsureLocalMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (AppMode::isPublic()) {
            abort(403, 'Fitur ini hanya tersedia di mode lokal.');
        }

        return $next($request);
    }
}
