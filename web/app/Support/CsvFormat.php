<?php

namespace App\Support;

/**
 * Format CSV "Coba Sendiri" (docs/kontrak.md bagian 4, analyze_csv).
 * Aturan yang sama diperiksa ulang oleh engine/radar/csv_input.py.
 */
final class CsvFormat
{
    public const REQUIRED = ['akun', 'teks', 'waktu'];

    public const OPTIONAL = ['url', 'reply_to', 'tanggal_akun'];

    public const MAX_ROWS = 2000;

    public const MAX_KILOBYTES = 2048;

    public const MAX_TEXT = 1000;

    public const MAX_ACCOUNT = 100;

    /** ISO 8601, zona opsional; tanpa zona dianggap WIB. */
    public const TIME_PATTERN = '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:?\d{2})?$/';

    public const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

    /**
     * Membaca dan memeriksa file. Mengembalikan jumlah baris data dan daftar kesalahan
     * (maksimal 5, dengan nomor baris seperti di spreadsheet: header = baris 1).
     *
     * @return array{rows: int, errors: list<string>}
     */
    public static function inspect(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return ['rows' => 0, 'errors' => ['File tidak bisa dibaca.']];
        }

        try {
            $header = fgetcsv($handle, escape: '');
            if ($header === false || $header === [null]) {
                return ['rows' => 0, 'errors' => ['File kosong.']];
            }

            $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
            $missing = array_diff(self::REQUIRED, $header);
            if ($missing !== []) {
                return ['rows' => 0, 'errors' => ['Kolom wajib tidak ada: '.implode(', ', $missing).'. Baris pertama harus berisi nama kolom.']];
            }
            $unknown = array_diff($header, [...self::REQUIRED, ...self::OPTIONAL]);
            if ($unknown !== []) {
                return ['rows' => 0, 'errors' => ['Kolom tidak dikenal: '.implode(', ', $unknown).'.']];
            }

            $index = array_flip($header);
            $errors = [];
            $rows = 0;
            $line = 1;

            while (($row = fgetcsv($handle, escape: '')) !== false) {
                $line++;
                if ($row === [null]) {
                    continue; // baris kosong
                }
                $rows++;
                if ($rows > self::MAX_ROWS) {
                    return ['rows' => $rows, 'errors' => ['Maksimal '.number_format(self::MAX_ROWS, 0, ',', '.').' baris data.']];
                }
                if (count($errors) >= 5) {
                    continue;
                }

                $get = fn (string $column) => trim((string) ($row[$index[$column] ?? -1] ?? ''));

                if ($get('akun') === '' || mb_strlen($get('akun')) > self::MAX_ACCOUNT) {
                    $errors[] = "Baris {$line}: kolom akun kosong atau lebih dari ".self::MAX_ACCOUNT.' karakter.';
                } elseif ($get('teks') === '' || mb_strlen($get('teks')) > self::MAX_TEXT) {
                    $errors[] = "Baris {$line}: kolom teks kosong atau lebih dari ".self::MAX_TEXT.' karakter.';
                } elseif (preg_match(self::TIME_PATTERN, $get('waktu')) !== 1) {
                    $errors[] = "Baris {$line}: format waktu harus ISO 8601, misalnya 2026-11-01T19:02:00+07:00.";
                } elseif (isset($index['tanggal_akun']) && $get('tanggal_akun') !== '' && preg_match(self::DATE_PATTERN, $get('tanggal_akun')) !== 1) {
                    $errors[] = "Baris {$line}: tanggal_akun harus YYYY-MM-DD.";
                }
            }

            if ($rows === 0) {
                $errors[] = 'Tidak ada baris data di bawah header.';
            }

            return ['rows' => $rows, 'errors' => $errors];
        } finally {
            fclose($handle);
        }
    }
}
