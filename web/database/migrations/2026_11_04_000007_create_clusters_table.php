<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clusters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('analysis_jobs')->cascadeOnDelete();
            $table->string('display_label', 16);
            $table->integer('size');
            $table->smallInteger('signals_active');
            $table->decimal('coordination_score', 4, 3);
            $table->boolean('is_flagged')->default(false);

            $table->index(['case_id', 'is_flagged']);
            $table->index('job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clusters');
    }
};
