<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kode status HTTP dari akses yang dicatat, supaya akses yang ditolak (403)
     * bisa dibedakan dari yang berhasil. Null untuk kejadian tanpa respons (login, logout).
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->smallInteger('status')->nullable()->after('ip');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['action']);
            $table->dropColumn('status');
        });
    }
};
