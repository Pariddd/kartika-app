<?php

namespace App\Support;

use App\Enums\JobType;
use App\Models\CaseFile;
use App\Models\User;

/**
 * Membangun payload JSON antrean persis sesuai docs/kontrak.md bagian 4.
 */
final class JobPayload
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function make(JobType $type, CaseFile $case, User $requestedBy, array $params): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'job_type' => $type->value,
            'case_id' => $case->getKey(),
            'requested_by' => $requestedBy->getKey(),
            'params' => $params,
        ];
    }
}
