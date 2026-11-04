<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Metadata teknis domain (RDAP). Tidak ada kolom data pemilik.
     */
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('domain', 253);
            $table->timestampTz('registered_at')->nullable();
            $table->string('registrar', 200)->nullable();
            $table->jsonb('nameservers')->default('[]');
            $table->timestampTz('checked_at');

            $table->unique(['case_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
