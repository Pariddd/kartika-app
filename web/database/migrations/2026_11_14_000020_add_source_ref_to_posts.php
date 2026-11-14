<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konten tempat sebuah komentar ditulis: "<platform>:<id konten>" (lihat engine/radar/source.py).
 * Hanya ID konten publik (video, tweet, post), tanpa username pembuatnya. Diisi worker.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('source_ref', 80)->nullable();
            $table->index(['case_id', 'source_ref']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['case_id', 'source_ref']);
            $table->dropColumn('source_ref');
        });
    }
};
