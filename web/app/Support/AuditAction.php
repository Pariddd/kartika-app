<?php

namespace App\Support;

/**
 * Label yang bisa dibaca untuk nilai audit_logs.action (nama rute atau kejadian).
 */
final class AuditAction
{
    private const LABELS = [
        'login' => 'Masuk',
        'login_gagal' => 'Gagal masuk',
        'logout' => 'Keluar',
        'cases.index' => 'Membuka daftar kasus',
        'cases.create' => 'Membuka form kasus baru',
        'cases.store' => 'Membuat kasus data nyata',
        'cases.show' => 'Membuka kasus',
        'cases.answer' => 'Membuka kunci jawaban',
        'cases.report' => 'Membuka ringkasan BLUF',
        'cases.destroy' => 'Menghapus kasus',
        'evaluation.show' => 'Membuka halaman evaluasi',
        'clusters.show' => 'Membuka bukti klaster',
        'clusters.review' => 'Menilai klaster',
        'clusters.names' => 'Melihat nama akun klaster',
        'cases.names' => 'Melihat nama akun di graf',
        'upload.create' => 'Membuka Coba Sendiri',
        'upload.store' => 'Mengunggah CSV',
        'audit.index' => 'Membuka log audit',
    ];

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::LABELS;
    }
}
