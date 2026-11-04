<?php

namespace App\Support;

/**
 * Mengekstrak ID video YouTube (11 karakter) dari link atau ID mentah.
 * Hanya domain YouTube resmi yang diterima; link lain dianggap tidak valid.
 */
final class YouTubeVideoId
{
    private const ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/';

    private const HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com'];

    public static function extract(string $input): ?string
    {
        $input = trim($input);

        if (preg_match(self::ID_PATTERN, $input) === 1) {
            return $input;
        }

        if (! str_contains($input, '://')) {
            $input = 'https://'.$input;
        }

        $parts = parse_url($input);

        if ($parts === false || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        $candidate = null;

        if ($host === 'youtu.be') {
            $candidate = explode('/', ltrim($path, '/'))[0] ?? null;
        } elseif (in_array($host, self::HOSTS, true)) {
            if ($path === '/watch') {
                parse_str($parts['query'] ?? '', $query);
                $candidate = is_string($query['v'] ?? null) ? $query['v'] : null;
            } elseif (preg_match('#^/(shorts|embed|live|v)/([^/?]+)#', $path, $match) === 1) {
                $candidate = $match[2];
            }
        }

        return $candidate !== null && preg_match(self::ID_PATTERN, $candidate) === 1 ? $candidate : null;
    }
}
