<?php

namespace App\Support;

use App\Enums\CaseMode;
use App\Enums\Role;
use App\Models\CaseFile;
use App\Models\Cluster;
use App\Models\User;

/**
 * Ringkasan di atas daftar kasus: apa yang perlu dikerjakan lebih dulu.
 * Hanya menghitung kasus yang boleh dilihat pengguna (kasus data nyata tidak ikut untuk akun demo).
 */
final class CaseOverview
{
    /**
     * @return array{cases: int, flagged: int, pending: int, top: ?CaseFile, guide: ?CaseFile}
     */
    public static function for(User $user): array
    {
        $visible = fn ($query) => $query->visibleTo($user);

        $flagged = Cluster::query()->where('is_flagged', true)->whereHas('case', $visible);

        // Kasus dengan koordinasi tertinggi = skor keorganikan terendah di antara yang punya klaster ditandai.
        $top = CaseFile::query()
            ->visibleTo($user)
            ->whereHas('clusters', fn ($query) => $query->where('is_flagged', true))
            ->with('latestIssueScore')
            ->get()
            ->filter(fn (CaseFile $case) => $case->latestIssueScore !== null)
            ->sortBy(fn (CaseFile $case) => $case->latestIssueScore->organic_score)
            ->first();

        return [
            'cases' => CaseFile::query()->visibleTo($user)->count(),
            'flagged' => (clone $flagged)->count(),
            'pending' => (clone $flagged)->whereDoesntHave('reviews')->count(),
            'top' => $top,
            // Panduan juri: kasus sintetis terbaru yang sudah punya klaster ditandai.
            'guide' => $user->hasRole(Role::Demo)
                ? CaseFile::query()
                    ->where('mode', CaseMode::Demo->value)
                    ->whereHas('clusters', fn ($query) => $query->where('is_flagged', true))
                    ->latest('id')
                    ->first()
                : null,
        ];
    }
}
