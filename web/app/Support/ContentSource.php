<?php

namespace App\Support;

/**
 * Konten tempat sebuah komentar ditulis, disimpan worker sebagai "<platform>:<id konten>"
 * (engine/radar/source.py). Tautan dibentuk ulang dari ID saja, tanpa username pembuat konten,
 * jadi tidak ada URL dari data impor yang pernah dicetak apa adanya ke halaman.
 */
final class ContentSource
{
    private const PATTERN = '/^(youtube|x|tiktok|instagram):([A-Za-z0-9_-]{1,64})$/';

    private const NAMES = [
        'youtube' => 'Video YouTube',
        'x' => 'Tweet',
        'tiktok' => 'Video TikTok',
        'instagram' => 'Post Instagram',
    ];

    /**
     * @return array{platform: string, id: string}|null
     */
    public static function parse(?string $ref): ?array
    {
        if ($ref === null || preg_match(self::PATTERN, $ref, $match) !== 1) {
            return null;
        }

        return ['platform' => $match[1], 'id' => $match[2]];
    }

    public static function url(?string $ref): ?string
    {
        $parsed = self::parse($ref);
        if ($parsed === null) {
            return null;
        }
        $id = rawurlencode($parsed['id']);

        return match ($parsed['platform']) {
            'youtube' => 'https://www.youtube.com/watch?v='.$id,
            'x' => 'https://x.com/i/status/'.$id,
            // TikTok mengarahkan "@/video/<id>" ke video aslinya tanpa perlu username pembuat.
            'tiktok' => 'https://www.tiktok.com/@/video/'.$id,
            'instagram' => 'https://www.instagram.com/p/'.$id.'/',
        };
    }

    /** Label pendek, misalnya "Video TikTok …0000001". */
    public static function label(?string $ref): string
    {
        $parsed = self::parse($ref);
        if ($parsed === null) {
            return 'Sumber tidak diketahui';
        }
        $id = $parsed['id'];
        $short = mb_strlen($id) > 8 ? '…'.mb_substr($id, -7) : $id;

        return self::NAMES[$parsed['platform']].' '.$short;
    }
}
