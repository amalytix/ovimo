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
        Schema::create('background_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_piece_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['POST', 'MANUAL']);

            // For type=POST
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();

            // For type=MANUAL
            $table->string('title', 500)->nullable();
            $table->text('content')->nullable();
            $table->string('url', 2000)->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['content_piece_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('background_sources');
    }
};
