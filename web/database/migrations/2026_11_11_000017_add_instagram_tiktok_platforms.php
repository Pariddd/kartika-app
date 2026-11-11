<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['accounts', 'posts'];

    private const BEFORE = ['x', 'youtube', 'csv', 'synthetic'];

    private const AFTER = ['x', 'youtube', 'csv', 'synthetic', 'instagram', 'tiktok'];

    /**
     * Menambah Instagram dan TikTok ke nilai kolom platform (impor ekspor Apify).
     */
    public function up(): void
    {
        $this->apply(self::AFTER);
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)->whereIn('platform', ['instagram', 'tiktok'])->delete();
        }
        $this->apply(self::BEFORE);
    }

    /**
     * @param  list<string>  $values
     */
    private function apply(array $values): void
    {
        foreach (self::TABLES as $table) {
            if (DB::getDriverName() === 'pgsql') {
                // enum() Laravel di PostgreSQL adalah varchar + CHECK; hanya CHECK-nya yang diganti.
                $list = implode(', ', array_map(fn (string $v) => "'{$v}'", $values));
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_platform_check");
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_platform_check CHECK (platform IN ({$list}))");
            } else {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->enum('platform', $values)->change());
            }
        }
    }
};
