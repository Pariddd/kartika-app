<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Ditulis oleh Python. Laravel hanya membaca.
 */
#[Table(timestamps: false)]
class IssueScore extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'organic_score' => 'float',
            'total_posts' => 'integer',
            'coordinated_posts' => 'integer',
            'computed_at' => 'datetime',
        ];
    }
}
