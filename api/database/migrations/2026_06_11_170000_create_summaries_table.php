<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('source_type');          // 'url' | 'text'
            $table->string('source_url')->nullable();
            $table->text('original_text')->nullable(); // pasted text / extracted content
            $table->string('title')->nullable();       // page title or first line

            $table->string('style');                 // 'tldr' | 'bullets' | 'short'
            $table->string('status')->default('pending'); // pending|processing|completed|failed

            $table->text('result_text')->nullable();
            $table->text('error_message')->nullable();

            $table->string('model')->nullable();     // e.g. claude-haiku-4-5-20251001
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();

            $table->json('metadata')->nullable();    // source domain, char count, truncation, etc.

            $table->timestamps();
            $table->timestamp('completed_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summaries');
    }
};
