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
            $table->string('generation_status')->default('NOT_STARTED')->after('status');
            $table->text('generation_error')->nullable()->after('generation_status');
            $table->timestamp('generation_error_occurred_at')->nullable()->after('generation_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_pieces', function (Blueprint $table) {
            $table->dropColumn(['generation_status', 'generation_error', 'generation_error_occurred_at']);
        });
    }
};
