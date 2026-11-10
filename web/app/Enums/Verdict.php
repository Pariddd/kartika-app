<?php

namespace App\Enums;

enum Verdict: string
{
    case Coordinated = 'terkoordinasi';
    case Mistaken = 'keliru';
    case Unsure = 'belum_pasti';

    public function label(): string
    {
        return match ($this) {
            self::Coordinated => 'Terkoordinasi',
            self::Mistaken => 'Keliru',
            self::Unsure => 'Belum pasti',
        };
    }
}
