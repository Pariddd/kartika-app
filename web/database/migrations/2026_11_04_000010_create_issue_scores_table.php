<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('analysis_jobs')->cascadeOnDelete();
            $table->decimal('organic_score', 4, 3);
            $table->integer('total_posts');
            $table->integer('coordinated_posts');
            $table->timestampTz('computed_at');

            $table->index('case_id');
            $table->index('job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_scores');
    }
};
