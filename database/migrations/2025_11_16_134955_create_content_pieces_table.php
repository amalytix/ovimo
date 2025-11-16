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
        Schema::create('content_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('internal_name');
            $table->text('briefing_text')->nullable();
            $table->enum('channel', ['BLOG_POST', 'LINKEDIN_POST', 'YOUTUBE_SCRIPT']);
            $table->enum('target_language', ['GERMAN', 'ENGLISH']);
            $table->enum('status', ['NOT_STARTED', 'DRAFT', 'FINAL'])->default('NOT_STARTED');
            $table->longText('full_text')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_pieces');
    }
};
