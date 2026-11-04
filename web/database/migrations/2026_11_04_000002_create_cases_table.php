<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->enum('mode', ['live', 'demo', 'csv']);
            $table->jsonb('sources')->default('[]');
            $table->jsonb('query')->nullable();
            $table->enum('status', ['draft', 'collecting', 'analyzing', 'done', 'failed'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->index('created_by');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
