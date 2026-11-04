<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Antrean kerja Laravel -> Python. Bukan tabel "jobs" bawaan Laravel.
     */
    public function up(): void
    {
        Schema::create('analysis_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->enum('job_type', ['collect', 'analyze', 'analyze_csv', 'evaluate']);
            $table->enum('status', ['pending', 'running', 'done', 'failed'])->default('pending');
            $table->jsonb('payload');
            $table->smallInteger('attempts')->default(0);
            $table->timestampTz('locked_at')->nullable();
            $table->string('worker_id', 64)->nullable();
            $table->text('error')->nullable();
            $table->jsonb('result_summary')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
            $table->index('case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_jobs');
    }
};
