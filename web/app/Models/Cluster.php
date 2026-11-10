<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Ditulis oleh Python. Laravel hanya membaca (kecuali penilaian analis di tabel terpisah).
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

    /**
     * @return BelongsTo<CaseFile, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    /**
     * @return BelongsToMany<Account, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'cluster_members');
    }

    /**
     * @return HasMany<ClusterSignal, $this>
     */
    public function signals(): HasMany
    {
        return $this->hasMany(ClusterSignal::class);
    }

    /**
     * @return HasMany<ClusterReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ClusterReview::class);
    }

    /**
     * @return HasOne<ClusterReview, $this>
     */
    public function latestReview(): HasOne
    {
        return $this->hasOne(ClusterReview::class)->latestOfMany();
    }
}
