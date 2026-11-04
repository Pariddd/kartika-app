<?php

namespace App\Enums;

enum JobStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Running => 'Diproses',
            self::Done => 'Selesai',
            self::Failed => 'Gagal',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Running], true);
    }
}
