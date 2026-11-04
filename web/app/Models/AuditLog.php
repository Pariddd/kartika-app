<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak audit. Hanya punya created_at; baris tidak pernah diubah.
 */
#[Fillable(['user_id', 'action', 'case_id', 'ip'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public static function record(string $action, ?int $userId, ?int $caseId, ?string $ip): self
    {
        return self::create([
            'action' => mb_substr($action, 0, 64),
            'user_id' => $userId,
            'case_id' => $caseId,
            'ip' => mb_substr((string) $ip, 0, 45),
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
