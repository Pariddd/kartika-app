<?php

namespace App\Enums;

enum SignalType: string
{
    case Text = 'text';
    case Time = 'time';
    case Profile = 'profile';
    case Infra = 'infra';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Kemiripan teks',
            self::Time => 'Ko-aktivitas waktu',
            self::Profile => 'Profil akun',
            self::Infra => 'Infrastruktur',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Text => 'Teks',
            self::Time => 'Waktu',
            self::Profile => 'Profil',
            self::Infra => 'Infrastruktur',
        };
    }

    /**
     * Urutan tampilan tetap.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::Text, self::Time, self::Profile, self::Infra];
    }
}
