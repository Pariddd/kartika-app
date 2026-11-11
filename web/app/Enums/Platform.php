<?php

namespace App\Enums;

enum Platform: string
{
    case X = 'x';
    case YouTube = 'youtube';
    case Instagram = 'instagram';
    case TikTok = 'tiktok';
    case Csv = 'csv';
    case Synthetic = 'synthetic';

    public function label(): string
    {
        return match ($this) {
            self::X => 'X',
            self::YouTube => 'YouTube',
            self::Instagram => 'Instagram',
            self::TikTok => 'TikTok',
            self::Csv => 'CSV',
            self::Synthetic => 'Sintetis',
        };
    }

    /**
     * Sumber yang datanya masuk lewat file JSON hasil ekspor Apify.
     *
     * @return list<self>
     */
    public static function imported(): array
    {
        return [self::X, self::Instagram, self::TikTok];
    }

    public function isImported(): bool
    {
        return in_array($this, self::imported(), true);
    }

    /** Keterangan singkat di pilihan sumber data. */
    public function sourceHint(): string
    {
        return match ($this) {
            self::YouTube => 'Komentar dan balasan dari video, diambil langsung lewat API resmi',
            self::X => 'Tweet dan balasan per kata kunci, dari file ekspor Apify',
            self::Instagram => 'Komentar pada post atau reel, dari file ekspor Apify',
            self::TikTok => 'Komentar pada video, dari file ekspor Apify',
            default => '',
        };
    }
}
