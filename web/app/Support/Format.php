<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class Format
{
    /**
     * Waktu UTC dari database ditampilkan dalam WIB, misalnya "4 Nov 2026, 23.40".
     */
    public static function wib(?CarbonInterface $time): string
    {
        if ($time === null) {
            return '–';
        }

        return $time->copy()
            ->setTimezone(config('radar.display_timezone'))
            ->locale('id')
            ->translatedFormat('j M Y, H.i');
    }

    public static function number(int|float|null $value): string
    {
        return $value === null ? '–' : number_format($value, 0, ',', '.');
    }

    public static function percent(?float $ratio): string
    {
        return $ratio === null ? '–' : (int) round($ratio * 100).'%';
    }
}
