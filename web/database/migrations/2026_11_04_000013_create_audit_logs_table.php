<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak audit. Tetap ada walau kasus atau pengguna dihapus (SET NULL).
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->string('ip', 45);
            $table->timestampTz('created_at')->useCurrent();

            $table->index('created_at');
            $table->index('user_id');
            $table->index('case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
