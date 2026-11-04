<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Post terpseudonimisasi. Tidak pernah memuat ID asli atau username.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->enum('platform', ['x', 'youtube', 'csv', 'synthetic']);
            $table->char('post_key', 64);
            $table->text('text');
            $table->timestampTz('posted_at');
            $table->char('reply_to_key', 64)->nullable();
            $table->char('conversation_key', 64)->nullable();
            $table->jsonb('urls')->default('[]');
            $table->jsonb('media_phash')->default('[]');

            $table->unique(['case_id', 'platform', 'post_key']);
            $table->index(['case_id', 'posted_at']);
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
