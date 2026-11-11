<?php

namespace App\Support;

/**
 * Batas ukuran unggahan yang benar-benar berlaku: nilai terkecil dari konfigurasi aplikasi
 * dan batas PHP (upload_max_filesize, post_max_size). Ekspor Apify bisa puluhan MB,
 * sedangkan bawaan PHP hanya 2 MB, jadi form harus menampilkan batas yang nyata.
 */
final class UploadLimit
{
    public static function importKilobytes(): int
    {
        $limits = [(int) config('radar.collect.import_file_max_kb')];
        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $bytes = self::bytes((string) ini_get($key));
            if ($bytes > 0) {
                $limits[] = intdiv($bytes, 1024);
            }
        }

        return max(1, min($limits));
    }

    public static function limitedByPhp(): bool
    {
        return self::importKilobytes() < (int) config('radar.collect.import_file_max_kb');
    }

    /** "8M", "512K", "1G", atau angka polos (byte). Nol atau kosong berarti tanpa batas. */
    public static function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || ! preg_match('/^(\d+)\s*([KMG])?$/i', $value, $m)) {
            return 0;
        }

        return (int) $m[1] * match (strtoupper($m[2] ?? '')) {
            'K' => 1024,
            'M' => 1024 ** 2,
            'G' => 1024 ** 3,
            default => 1,
        };
    }

    public static function label(): string
    {
        $kb = self::importKilobytes();

        return $kb >= 1024 ? rtrim(rtrim(number_format($kb / 1024, 1, ',', '.'), '0'), ',').' MB' : $kb.' KB';
    }
}
