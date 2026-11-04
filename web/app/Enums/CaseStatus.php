<?php

namespace App\Enums;

enum CaseStatus: string
{
    case Draft = 'draft';
    case Collecting = 'collecting';
    case Analyzing = 'analyzing';
    case Done = 'done';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Collecting => 'Mengumpulkan data',
            self::Analyzing => 'Menganalisis',
            self::Done => 'Selesai',
            self::Failed => 'Gagal',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Collecting, self::Analyzing], true);
    }
}
