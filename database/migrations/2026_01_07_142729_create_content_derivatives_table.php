<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('content_derivatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_piece_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title', 500)->nullable();
            $table->longText('text')->nullable();

            $table->enum('status', ['NOT_STARTED', 'DRAFT', 'FINAL', 'PUBLISHED', 'NOT_PLANNED'])->default('NOT_STARTED');
            $table->boolean('is_published')->default(false);
            $table->timestamp('planned_publish_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->enum('generation_status', ['IDLE', 'QUEUED', 'PROCESSING', 'COMPLETED', 'FAILED'])->default('IDLE');
            $table->text('generation_error')->nullable();
            $table->timestamp('generation_error_occurred_at')->nullable();

            $table->timestamps();

            $table->unique(['content_piece_id', 'channel_id']);
            $table->index(['content_piece_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_derivatives');
    }
};
