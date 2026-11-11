<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data graf koordinasi per kasus untuk ditampilkan (simpul, garis, tata letak).
     * Ditulis worker Python saat analisis; Laravel hanya membaca. Lihat docs/kontrak.md.
     */
    public function up(): void
    {
        Schema::create('case_graphs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->unique()->constrained('cases')->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('analysis_jobs')->cascadeOnDelete();
            $table->jsonb('graph');
            $table->timestampTz('computed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_graphs');
    }
};
