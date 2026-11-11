<?php

namespace App\Support;

/**
 * Data ilustrasi di halaman depan: sepuluh akun fiktif pada satu jam percakapan.
 * Empat akun menulis dalam jendela lima menit yang sama sebanyak tiga kali; sisanya acak.
 * Murni gambar penjelas (bukan data kasus), dibangkitkan deterministik agar tampilan stabil.
 */
final class SyncStrip
{
    public const WIDTH = 1120;

    public const HEIGHT = 384;

    public const LEFT = 130;

    public const RIGHT = 1090;

    public const ROW_TOP = 40;

    public const ROW_GAP = 32;

    public const ROWS = 10;

    /** Lama garis pindai melintasi satu jam, dalam detik animasi. */
    public const SWEEP_SECONDS = 7.0;

    private const COORDINATED_ROWS = [1, 3, 6, 8];

    /** Awal tiap jendela 5 menit (menit ke-) tempat keempat akun bergerak bersama. */
    private const EVENT_MINUTES = [10, 30, 50];

    /**
     * @return array{rows: list<array{label: string, y: int, coordinated: bool}>, dots: list<array<string, mixed>>, events: list<array<string, mixed>>, ticks: list<array{x: float, label: string}>}
     */
    public static function build(): array
    {
        $seed = 20261115;
        $random = function () use (&$seed): float {
            $seed = ($seed * 1103515245 + 12345) % 2147483648;

            return $seed / 2147483648;
        };
        $x = fn (float $minute): float => round(self::LEFT + $minute / 60 * (self::RIGHT - self::LEFT), 1);
        $delay = fn (float $minute): float => round($minute / 60 * self::SWEEP_SECONDS, 2);

        $rows = [];
        $dots = [];
        foreach (range(0, self::ROWS - 1) as $row) {
            $coordinated = in_array($row, self::COORDINATED_ROWS, true);
            $y = self::ROW_TOP + $row * self::ROW_GAP;
            $rows[] = ['label' => sprintf('Akun-%04d', $row + 1), 'y' => $y, 'coordinated' => $coordinated];

            foreach (range(1, $coordinated ? 2 : 4 + (int) floor($random() * 3)) as $ignored) {
                $minute = 1 + $random() * 58;
                $dots[] = ['x' => $x($minute), 'y' => $y, 'delay' => $delay($minute), 'sync' => false];
            }
        }

        $events = [];
        foreach (self::EVENT_MINUTES as $start) {
            $points = [];
            foreach (self::COORDINATED_ROWS as $row) {
                $minute = $start + 0.8 + $random() * 3.4;
                $y = self::ROW_TOP + $row * self::ROW_GAP;
                $dots[] = ['x' => $x($minute), 'y' => $y, 'delay' => $delay($minute), 'sync' => true];
                $points[] = $x($minute).','.$y;
            }
            $events[] = [
                'x' => $x($start),
                'width' => $x($start + 5) - $x($start),
                'points' => implode(' ', $points),
                'delay' => $delay($start + 5),
                'label' => sprintf('4 akun · 14.%02d', $start),
            ];
        }

        $ticks = [];
        foreach (range(0, 60, 10) as $minute) {
            $ticks[] = ['x' => $x($minute), 'label' => $minute === 60 ? '15.00' : sprintf('14.%02d', $minute)];
        }

        return ['rows' => $rows, 'dots' => $dots, 'events' => $events, 'ticks' => $ticks];
    }
}
