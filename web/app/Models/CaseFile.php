<?php

namespace App\Models;

use App\Enums\CaseMode;
use App\Enums\CaseStatus;
use App\Enums\Role;
use App\Support\ContentMask;
use Database\Factories\CaseFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Kasus pemantauan. Nama kelas bukan "Case" karena "case" adalah kata kunci PHP.
 */
#[Table('cases')]
#[Fillable(['name', 'mode', 'sources', 'query', 'status', 'created_by', 'expires_at'])]
class CaseFile extends Model
{
    /** @use HasFactory<CaseFileFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => CaseMode::class,
            'status' => CaseStatus::class,
            'sources' => 'array',
            'query' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Nama untuk ditampilkan: disamarkan bila memuat kata dari daftar penyamaran
     * (nama kasus unggahan diketik oleh pengguna, termasuk juri di mode publik).
     */
    public function displayName(): string
    {
        return ContentMask::display($this->name);
    }

    /**
     * Demo (juri) tidak pernah melihat kasus data nyata.
     *
     * @param  Builder<CaseFile>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->hasRole(Role::Admin, Role::Analis)) {
            $query->where('mode', '!=', CaseMode::Live->value);
        }
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AnalysisJob, $this>
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(AnalysisJob::class, 'case_id');
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'case_id');
    }

    /**
     * @return HasMany<Cluster, $this>
     */
    public function clusters(): HasMany
    {
        return $this->hasMany(Cluster::class, 'case_id');
    }

    /**
     * @return HasOne<IssueScore, $this>
     */
    public function latestIssueScore(): HasOne
    {
        return $this->hasOne(IssueScore::class, 'case_id')->latestOfMany('computed_at');
    }
}
