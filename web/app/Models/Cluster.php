<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Ditulis oleh Python. Laravel hanya membaca.
 */
#[Table(timestamps: false)]
class Cluster extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'coordination_score' => 'float',
            'is_flagged' => 'boolean',
            'size' => 'integer',
            'signals_active' => 'integer',
        ];
    }
}
