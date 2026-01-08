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
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('content_derivative_id')
                ->nullable()
                ->after('post_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['content_derivative_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\ContentDerivative::class);
            $table->dropIndex(['content_derivative_id', 'created_at']);
            $table->dropColumn('content_derivative_id');
        });
    }
};
