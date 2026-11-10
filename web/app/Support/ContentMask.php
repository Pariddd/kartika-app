<?php

namespace App\Support;

/**
 * Penyamaran tampilan untuk kata kasar dan hinaan SARA (aturan kerja: konten SARA dan
 * kata kasar tampil sebagai [konten disamarkan] di mode publik).
 *
 * Hanya mengubah tampilan. Teks di database dan analisis tidak disentuh, sehingga skor
 * kemiripan tetap dihitung dari teks asli.
 *
 * Batasan yang disengaja: berbasis daftar kata (resources/moderation/kata-disamarkan.txt),
 * bukan klasifikasi makna. Ujaran kebencian tanpa kata dari daftar tidak tertangkap.
 */
final class ContentMask
{
    public const PLACEHOLDER = '[konten disamarkan]';

    private const SUFFIXES = ['nya', 'lah', 'mu', 'ku'];

    private const LEET = ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's'];

    /** @var array<string, true>|null */
    private static ?array $words = null;

    /**
     * Aktif di mode publik, dan di mode lokal saat mode presentasi dinyalakan.
     */
    public static function active(): bool
    {
        return AppMode::isPublic() || (bool) config('radar.presentation_mask');
    }

    public static function display(?string $text): string
    {
        $text ??= '';

        return self::active() && self::contains($text) ? self::PLACEHOLDER : $text;
    }

    public static function contains(string $text): bool
    {
        $words = self::words();

        foreach (self::tokens($text) as $token) {
            if (isset($words[$token])) {
                return true;
            }
            foreach (self::SUFFIXES as $suffix) {
                if (str_ends_with($token, $suffix) && isset($words[substr($token, 0, -strlen($suffix))])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $text): array
    {
        return array_map(
            self::normalize(...),
            preg_split('/[^\p{L}]+/u', strtr(mb_strtolower($text), self::LEET), -1, PREG_SPLIT_NO_EMPTY) ?: [],
        );
    }

    /**
     * "Anjiiing" dan "anjing" menjadi bentuk yang sama: huruf berulang dirapatkan.
     */
    private static function normalize(string $word): string
    {
        return (string) preg_replace('/(\p{L})\1+/u', '$1', $word);
    }

    /**
     * @return array<string, true>
     */
    private static function words(): array
    {
        if (self::$words !== null) {
            return self::$words;
        }

        $lines = file(resource_path('moderation/kata-disamarkan.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $words = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && ! str_starts_with($line, '#')) {
                $words[self::normalize(mb_strtolower($line))] = true;
            }
        }

        return self::$words = $words;
    }
}
