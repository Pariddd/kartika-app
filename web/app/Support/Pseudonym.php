<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Mode pseudonim: saklar admin untuk menyembunyikan nama akun dan @username asli dari
 * semua peran, dan mencegah worker menyimpan nama baru. AKTIF secara bawaan; hanya admin
 * yang bisa mematikannya, dan setiap perubahan tercatat di log audit.
 *
 * Saklar ini berlapis di atas syarat lain (mode lokal, .env, peran). Mematikannya tidak
 * pernah membuka nama di server publik.
 */
final class Pseudonym
{
    public const KEY = 'pseudonym_mode';

    public static function active(): bool
    {
        $value = DB::table('app_settings')->where('key', self::KEY)->value('value');

        // Tidak ada baris atau nilai rusak = aktif (fail closed).
        return $value === null || json_decode((string) $value) !== false;
    }

    public static function set(bool $active, User $by, ?string $ip): void
    {
        DB::table('app_settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => json_encode($active), 'updated_by' => $by->getKey(), 'updated_at' => now()],
        );
        AuditLog::record($active ? 'settings.pseudonym_on' : 'settings.pseudonym_off', $by->getKey(), null, $ip, 200);
    }

    /**
     * Siapa dan kapan saklar terakhir diubah.
     *
     * @return object{name: ?string, updated_at: ?string}|null
     */
    public static function lastChange(): ?object
    {
        return DB::table('app_settings')
            ->leftJoin('users', 'users.id', '=', 'app_settings.updated_by')
            ->where('app_settings.key', self::KEY)
            ->first(['users.name', 'app_settings.updated_at']);
    }

    /**
     * @return array{accounts: int, posts: int}
     */
    public static function storedNames(): array
    {
        return [
            'accounts' => DB::table('accounts')->whereNotNull('display_name')->count(),
            'posts' => DB::table('posts')->whereNotNull('text_raw')->count(),
        ];
    }

    /**
     * Menghapus permanen semua nama akun dan teks dengan @username asli yang tersimpan.
     * Analisis tidak terpengaruh: analisis hanya memakai kunci HMAC dan teks tersamar.
     *
     * @return array{accounts: int, posts: int}
     */
    public static function purgeNames(User $by, ?string $ip): array
    {
        $result = DB::transaction(fn () => [
            'accounts' => DB::table('accounts')->whereNotNull('display_name')->update(['display_name' => null]),
            'posts' => DB::table('posts')->whereNotNull('text_raw')->update(['text_raw' => null]),
        ]);

        AuditLog::record('settings.names_purged', $by->getKey(), null, $ip, 200);

        return $result;
    }
}
