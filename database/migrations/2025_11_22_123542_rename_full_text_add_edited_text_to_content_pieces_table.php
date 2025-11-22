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
        Schema::table('content_pieces', function (Blueprint $table) {
            $table->renameColumn('full_text', 'research_text');
            $table->longText('edited_text')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_pieces', function (Blueprint $table) {
            $table->dropColumn('edited_text');
            $table->renameColumn('research_text', 'full_text');
        });
    }
};
