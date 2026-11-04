<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Ditulis oleh Python. Laravel hanya membaca.
 */
#[Table(timestamps: false)]
class Post extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'posted_at' => 'datetime',
            'urls' => 'array',
            'media_phash' => 'array',
        ];
    }
}
