<?php

namespace App\Models;

use App\Enums\JobStatus;
use App\Enums\JobType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Antrean Laravel -> Python (docs/kontrak.md bagian 3 dan 4).
 * Laravel hanya membuat job berstatus pending; status selanjutnya ditulis worker Python.
 */
#[Fillable(['case_id', 'job_type', 'status', 'payload'])]
class AnalysisJob extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'job_type' => JobType::class,
            'status' => JobStatus::class,
            'payload' => 'array',
            'result_summary' => 'array',
            'locked_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CaseFile, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }
}
