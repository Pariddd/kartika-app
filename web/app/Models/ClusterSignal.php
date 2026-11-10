<?php

namespace App\Models;

use App\Enums\SignalType;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Ditulis oleh Python. Format evidence: docs/kontrak.md bagian 6.
 */
#[Table(timestamps: false)]
class ClusterSignal extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signal' => SignalType::class,
            'active' => 'boolean',
            'strength' => 'float',
            'evidence' => 'array',
        ];
    }
}
