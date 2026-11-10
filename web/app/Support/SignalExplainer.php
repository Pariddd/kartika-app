<?php

namespace App\Support;

use App\Enums\SignalType;
use App\Models\ClusterSignal;

/**
 * Mengubah evidence JSON (docs/kontrak.md bagian 6) menjadi satu kalimat yang bisa dibaca analis.
 */
final class SignalExplainer
{
    public static function sentence(ClusterSignal $signal): string
    {
        $e = $signal->evidence ?? [];

        return match ($signal->signal) {
            SignalType::Text => sprintf(
                '%s pasangan akun di klaster ini saling menulis teks mirip; di seluruh kasus hanya %s.',
                self::pct($e['pair_ratio'] ?? 0),
                self::pct($e['baseline_ratio'] ?? 0),
            ),
            SignalType::Time => sprintf(
                '%s pasangan akun muncul bersamaan (jendela %s menit) di minimal 3 kejadian; di seluruh kasus %s. Tercatat %d kejadian serentak.',
                self::pct($e['pair_ratio'] ?? 0),
                intdiv((int) ($e['window_seconds'] ?? 300), 60),
                self::pct($e['baseline_ratio'] ?? 0),
                count($e['events'] ?? []),
            ),
            SignalType::Profile => ($e['available'] ?? false)
                ? sprintf(
                    '%s anggota (%d akun) dibuat dalam %d hari yang sama; di seluruh kasus %s.',
                    self::pct($e['member_share'] ?? 0),
                    (int) ($e['accounts_in_window'] ?? 0),
                    (int) ($e['window_days'] ?? 30),
                    self::pct($e['baseline_share'] ?? 0),
                )
                : 'Tidak tersedia: sumber data tidak menyediakan tanggal pembuatan akun.',
            SignalType::Infra => self::infra($e['domains'] ?? []),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $domains
     */
    private static function infra(array $domains): string
    {
        $distinctive = array_values(array_filter($domains, fn ($d) => $d['distinctive'] ?? false));

        if ($distinctive === []) {
            return $domains === []
                ? 'Anggota klaster tidak membagikan tautan.'
                : 'Domain yang dibagikan anggota juga umum dibagikan akun lain.';
        }

        $top = $distinctive[0];

        return sprintf(
            '%d akun (%s anggota) membagikan domain yang hampir tidak dipakai akun lain (%s).',
            (int) $top['accounts'],
            self::pct($top['member_share'] ?? 0),
            self::pct($top['other_share'] ?? 0),
        );
    }

    /**
     * Persentase dengan presisi yang cukup untuk baseline kecil (0,04%).
     */
    public static function pct(float|int $ratio): string
    {
        $value = $ratio * 100;
        $decimals = $value > 0 && $value < 1 ? 2 : ($value < 10 ? 1 : 0);

        return number_format($value, $decimals, ',', '.').'%';
    }
}
