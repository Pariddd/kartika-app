<?php

namespace App\Enums;

enum JobType: string
{
    case Collect = 'collect';
    case Analyze = 'analyze';
    case AnalyzeCsv = 'analyze_csv';
    case Evaluate = 'evaluate';

    public function label(): string
    {
        return match ($this) {
            self::Collect => 'Pengumpulan data',
            self::Analyze => 'Analisis',
            self::AnalyzeCsv => 'Analisis CSV',
            self::Evaluate => 'Evaluasi',
        };
    }
}
