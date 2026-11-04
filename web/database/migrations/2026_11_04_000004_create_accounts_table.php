<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Akun terpseudonimisasi, per kasus. Ditulis oleh Python.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->enum('platform', ['x', 'youtube', 'csv', 'synthetic']);
            $table->char('account_key', 64);
            $table->string('display_label', 16);
            $table->timestampTz('account_created_at')->nullable();
            $table->integer('followers')->nullable();
            $table->integer('following')->nullable();

            $table->unique(['case_id', 'platform', 'account_key']);
            $table->unique(['case_id', 'display_label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
