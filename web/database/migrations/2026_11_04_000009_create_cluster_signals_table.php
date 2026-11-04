<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Empat sinyal independen per klaster, beserta bukti (docs/kontrak.md bagian 6).
     */
    public function up(): void
    {
        Schema::create('cluster_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cluster_id')->constrained('clusters')->cascadeOnDelete();
            $table->enum('signal', ['text', 'time', 'profile', 'infra']);
            $table->boolean('active');
            $table->decimal('strength', 4, 3);
            $table->jsonb('evidence')->default('{}');

            $table->unique(['cluster_id', 'signal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_signals');
    }
};
