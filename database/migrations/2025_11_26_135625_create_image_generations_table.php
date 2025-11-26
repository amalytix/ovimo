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
        Schema::create('image_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_piece_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_id')->nullable()->constrained()->nullOnDelete();
            $table->text('generated_text_prompt')->nullable();
            $table->string('aspect_ratio')->default('16:9');
            $table->string('status')->default('DRAFT');
            $table->foreignId('media_id')->nullable()->constrained()->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_generations');
    }
};
