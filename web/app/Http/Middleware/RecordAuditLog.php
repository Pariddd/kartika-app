<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Mencatat setiap akses ke rute yang memakainya, termasuk yang ditolak (403).
 * Dipasang pada grup rute kasus.
 */
class RecordAuditLog
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            AuditLog::record(
                action: $request->route()?->getName() ?? $request->method().' '.$request->path(),
                userId: $request->user()?->getAuthIdentifier(),
                caseId: $this->caseId($request),
                ip: $request->ip(),
                status: $response->getStatusCode(),
            );
        } catch (Throwable $exception) {
            // Kegagalan menulis audit dilaporkan, tetapi tidak menggagalkan halaman.
            report($exception);
        }

        return $response;
    }

    /**
     * Hanya kasus yang sudah terbukti ada (hasil route model binding) yang dicatat,
     * agar ID sembarang dari URL tidak memicu pelanggaran foreign key.
     */
    private function caseId(Request $request): ?int
    {
        $case = $request->route('case');

        return $case instanceof Model ? (int) $case->getKey() : null;
    }
}
