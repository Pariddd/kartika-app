<?php

namespace App\Support;

use App\Models\CaseFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lini waktu percakapan: jumlah post per rentang waktu, dipisah antara post dari
 * klaster ditandai dan post lain. Menjawab "kapan isu ini naik, dan siapa yang mendorongnya".
 *
 * Hanya memakai waktu dan keanggotaan klaster; tidak menyentuh teks atau identitas.
 */
final class CaseTimeline
{
    /** Ukuran rentang yang boleh dipakai (detik), dari paling halus. */
    private const BUCKETS = [600, 1800, 3600, 3 * 3600, 6 * 3600, 86400, 7 * 86400];

    private const MAX_BARS = 48;

    /** Lonjakan baru dicari bila ada cukup rentang untuk menghitung nilai tengah yang berarti. */
    private const SPIKE_MIN_BUCKETS = 8;

    private const SPIKE_MIN_POSTS = 5;

    private const SPIKE_MAX_SHOWN = 3;

    /**
     * @return array<string, mixed>|null null bila kasus belum punya post
     */
    public static function build(CaseFile $case): ?array
    {
        $rows = DB::table('posts')
            ->where('case_id', $case->getKey())
            ->orderBy('posted_at')
            ->get(['account_id', 'posted_at']);

        if ($rows->isEmpty()) {
            return null;
        }

        $flagged = DB::table('cluster_members')
            ->join('clusters', 'clusters.id', '=', 'cluster_members.cluster_id')
            ->where('clusters.case_id', $case->getKey())
            ->where('clusters.is_flagged', true)
            ->pluck('cluster_members.account_id')
            ->flip();

        $times = $rows->map(fn ($row) => Carbon::parse($row->posted_at, 'UTC')->getTimestamp());
        $first = $times->first();
        $last = $times->last();

        $size = self::bucketSize($last - $first);
        // Batas rentang mengikuti jam WIB, supaya rentang harian mulai pukul 00.00 WIB.
        $offset = Carbon::createFromTimestamp($first, config('radar.display_timezone'))->getOffset();
        $start = intdiv($first + $offset, $size) * $size - $offset;
        $count = intdiv($last - $start, $size) + 1;

        $total = array_fill(0, $count, 0);
        $coordinated = array_fill(0, $count, 0);
        foreach ($rows as $i => $row) {
            $index = intdiv($times[$i] - $start, $size);
            $total[$index]++;
            if (isset($flagged[$row->account_id])) {
                $coordinated[$index]++;
            }
        }

        $buckets = [];
        for ($i = 0; $i < $count; $i++) {
            $from = Carbon::createFromTimestamp($start + $i * $size, config('radar.display_timezone'));
            $buckets[] = [
                'from' => $from,
                'label' => self::label($from, $size),
                'tick' => self::tick($from, $size),
                'total' => $total[$i],
                'coordinated' => $coordinated[$i],
                'other' => $total[$i] - $coordinated[$i],
            ];
        }

        $spikes = self::spikes($total);
        foreach ($buckets as $i => $bucket) {
            $buckets[$i]['spike'] = isset($spikes['indexes'][$i]);
        }

        $peakIndex = array_keys($total, max($total))[0];
        $coordPeakIndex = max($coordinated) > 0 ? array_keys($coordinated, max($coordinated))[0] : null;

        return [
            'bucket_seconds' => $size,
            'bucket_label' => self::sizeLabel($size),
            'buckets' => $buckets,
            'max' => max($total),
            'axis_max' => self::niceMax(max($total)),
            'peak' => $buckets[$peakIndex],
            'coordinated_peak' => $coordPeakIndex !== null ? $buckets[$coordPeakIndex] : null,
            'has_coordinated' => $flagged->isNotEmpty(),
            'median' => $spikes['median'],
            'spike_threshold' => $spikes['threshold'],
            // Lonjakan terbesar lebih dulu; tiap entri membawa kelipatan terhadap nilai tengah.
            'spikes' => collect($spikes['indexes'])->keys()
                ->sortByDesc(fn (int $i) => $total[$i])
                ->take(self::SPIKE_MAX_SHOWN)
                ->map(fn (int $i) => $buckets[$i] + [
                    'ratio' => $spikes['median'] > 0 ? $total[$i] / $spikes['median'] : null,
                    'coordinated_share' => $coordinated[$i] / $total[$i],
                ])
                ->values()->all(),
            'spike_count' => count($spikes['indexes']),
        ];
    }

    /**
     * Lonjakan = rentang yang jumlah post-nya jauh di atas kebiasaan kasus itu sendiri:
     * di atas nilai tengah + 3 x simpangan (MAD, tahan terhadap pencilan). Ini hanya
     * penanda "lihat di sini"; lonjakan bisa saja organik (misalnya ada berita besar).
     *
     * @param  list<int>  $total
     * @return array{indexes: array<int, true>, median: float, threshold: ?float}
     */
    public static function spikes(array $total): array
    {
        $median = self::median($total);
        if (count($total) < self::SPIKE_MIN_BUCKETS) {
            return ['indexes' => [], 'median' => $median, 'threshold' => null];
        }

        $mad = self::median(array_map(fn (int $value) => abs($value - $median), $total));
        // Simpangan minimal 1 agar deret yang hampir datar tidak menandai kenaikan satu-dua post.
        $threshold = $median + 3 * max(1.4826 * $mad, 1.0);

        $indexes = [];
        foreach ($total as $i => $value) {
            if ($value > $threshold && $value >= self::SPIKE_MIN_POSTS) {
                $indexes[$i] = true;
            }
        }

        return ['indexes' => $indexes, 'median' => $median, 'threshold' => $threshold];
    }

    /**
     * @param  list<int|float>  $values
     */
    private static function median(array $values): float
    {
        sort($values);
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }

        return $n % 2 === 1 ? (float) $values[intdiv($n, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2;
    }

    public static function bucketSize(int $span): int
    {
        foreach (self::BUCKETS as $size) {
            if (intdiv($span, $size) + 1 <= self::MAX_BARS) {
                return $size;
            }
        }

        return (int) (ceil($span / self::MAX_BARS / 86400) * 86400);
    }

    public static function niceMax(int $max): int
    {
        if ($max <= 4) {
            return 4;
        }
        $magnitude = 10 ** (int) floor(log10($max));
        foreach ([1, 2, 4, 5, 10] as $step) {
            if ($max <= $step * $magnitude) {
                return (int) ($step * $magnitude);
            }
        }

        return (int) (10 * $magnitude);
    }

    private static function label(Carbon $from, int $size): string
    {
        if ($size >= 86400) {
            return $size === 86400
                ? $from->translatedFormat('j M Y')
                : $from->translatedFormat('j M').' – '.$from->copy()->addSeconds($size - 1)->translatedFormat('j M Y');
        }

        return $from->translatedFormat('j M, H.i').'–'.$from->copy()->addSeconds($size)->format('H.i').' WIB';
    }

    /** Label sumbu: tanggal di rentang pertama tiap hari, selain itu jam. */
    private static function tick(Carbon $from, int $size): string
    {
        if ($size >= 86400) {
            return $from->translatedFormat('j M');
        }

        return $from->format('H.i') === '00.00' ? $from->translatedFormat('j M') : $from->format('H.i');
    }

    private static function sizeLabel(int $size): string
    {
        return match (true) {
            $size < 3600 => intdiv($size, 60).' menit',
            $size < 86400 => intdiv($size, 3600).' jam',
            default => intdiv($size, 86400).' hari',
        };
    }
}
