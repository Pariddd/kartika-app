<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ditulis oleh Python. Laravel hanya membaca.
 * text_raw (teks dengan @username asli) hanya boleh ditampilkan lewat AccountNames.
 */
#[Hidden(['text_raw', 'post_key'])]
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

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
