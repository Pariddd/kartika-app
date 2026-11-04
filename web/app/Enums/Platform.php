<?php

namespace App\Enums;

enum Platform: string
{
    case X = 'x';
    case YouTube = 'youtube';
    case Csv = 'csv';
    case Synthetic = 'synthetic';

    public function label(): string
    {
        return match ($this) {
            self::X => 'X',
            self::YouTube => 'YouTube',
            self::Csv => 'CSV',
            self::Synthetic => 'Sintetis',
        };
    }
}
