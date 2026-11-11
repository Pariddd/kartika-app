<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teks post dengan @username asli. Hanya diisi worker di mode lokal bila fitur nama menyala,
 * dan hanya ditampilkan kepada admin dan analis (App\Support\AccountNames).
 * Kolom text tetap berisi teks tersamar dan tetap menjadi satu-satunya masukan analisis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->text('text_raw')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('text_raw');
        });
    }
};
