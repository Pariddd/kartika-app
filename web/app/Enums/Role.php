<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Analis = 'analis';
    case Demo = 'demo';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Analis => 'Analis',
            self::Demo => 'Demo',
        };
    }
}
