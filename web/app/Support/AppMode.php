<?php

namespace App\Support;

/**
 * Mode aplikasi: "local" (laptop tim, pengumpulan data nyata aktif)
 * atau "public" (server juri, hanya data sintetis dan CSV unggahan).
 */
final class AppMode
{
    public const LOCAL = 'local';

    public const PUBLIC = 'public';

    /**
     * Nilai kosong atau tidak dikenal dianggap "public" (fail closed),
     * supaya salah konfigurasi tidak pernah mengaktifkan pengumpulan data.
     */
    public static function normalize(?string $value): string
    {
        return strtolower(trim((string) $value)) === self::LOCAL ? self::LOCAL : self::PUBLIC;
    }

    public static function current(): string
    {
        return self::normalize(config('radar.mode'));
    }

    public static function isLocal(): bool
    {
        return self::current() === self::LOCAL;
    }

    public static function isPublic(): bool
    {
        return ! self::isLocal();
    }
}
