<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penilaian analis. Label "keliru" dipakai untuk kalibrasi ambang.
     */
    public function up(): void
    {
        Schema::create('cluster_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cluster_id')->constrained('clusters')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('verdict', ['terkoordinasi', 'keliru', 'belum_pasti']);
            $table->text('note')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('cluster_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_reviews');
    }
};
