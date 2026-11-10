<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nama tampilan akun dari platform (bukan ID, bukan URL profil). Null secara bawaan:
     * hanya diisi worker bila RADAR_STORE_ACCOUNT_NAMES=true di mode lokal.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('display_name', 100)->nullable()->after('display_label');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
