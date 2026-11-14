<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengaturan aplikasi yang diubah admin dari antarmuka (bukan dari .env).
     * Saat ini satu kunci: pseudonym_mode. Baris yang tidak ada berarti pseudonim AKTIF
     * (fail closed). Worker hanya boleh membaca tabel ini, tidak boleh mengubahnya.
     */
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->jsonb('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('updated_at');
        });

        // Hak akses hanya ada di PostgreSQL; test memakai SQLite.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'radar_engine') THEN
                    REVOKE ALL ON app_settings FROM radar_engine;
                    GRANT SELECT ON app_settings TO radar_engine;
                END IF;
            END
            $$;
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
