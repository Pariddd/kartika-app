<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Akun terpseudonimisasi. Ditulis oleh Python; Laravel hanya membaca.
 * Tidak pernah memuat ID asli atau URL profil. display_name (nama tampilan) hanya terisi
 * bila fitur nama dinyalakan di mode lokal, dan hanya boleh ditampilkan lewat AccountNames.
 */
#[Hidden(['display_name', 'account_key'])]
#[Table(timestamps: false)]
class Account extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'account_created_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
