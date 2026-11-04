<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kunci jawaban dataset sintetis. Hanya dibaca endpoint "Tampilkan jawaban".
     */
    public function up(): void
    {
        Schema::create('demo_ground_truth', function (Blueprint $table) {
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('planted_label', 16);
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();

            $table->primary(['case_id', 'account_id']);
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_ground_truth');
    }
};
