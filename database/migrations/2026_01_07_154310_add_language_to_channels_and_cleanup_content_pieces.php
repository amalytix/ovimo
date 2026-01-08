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
        // Add language field to channels table
        Schema::table('channels', function (Blueprint $table) {
            $table->enum('language', ['ENGLISH', 'GERMAN'])->default('ENGLISH')->after('name');
        });

        // Remove status and target_language from content_pieces table
        Schema::table('content_pieces', function (Blueprint $table) {
            $table->dropColumn(['status', 'target_language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove language from channels
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('language');
        });

        // Re-add status and target_language to content_pieces
        Schema::table('content_pieces', function (Blueprint $table) {
            $table->enum('target_language', ['GERMAN', 'ENGLISH'])->default('ENGLISH')->after('channel');
            $table->enum('status', ['NOT_STARTED', 'DRAFT', 'FINAL'])->default('NOT_STARTED')->after('target_language');
        });
    }
};
