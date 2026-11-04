<?php

namespace App\Enums;

enum CaseMode: string
{
    case Live = 'live';
    case Demo = 'demo';
    case Csv = 'csv';

    public function label(): string
    {
        return match ($this) {
            self::Live => 'Data nyata',
            self::Demo => 'Sintetis',
            self::Csv => 'CSV',
        };
    }
}
