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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('uri', 2048);
            $table->text('summary')->nullable();
            $table->unsignedTinyInteger('relevancy_score')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->enum('status', ['NOT_RELEVANT', 'CREATE_CONTENT'])->default('NOT_RELEVANT');
            $table->timestamp('found_at');
            $table->timestamps();

            $table->unique(['source_id', 'uri']);
            $table->index(['source_id', 'is_hidden', 'found_at']);
            $table->index(['source_id', 'is_read']);
            $table->index(['source_id', 'relevancy_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
