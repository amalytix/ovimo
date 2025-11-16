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
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('internal_name');
            $table->enum('type', ['RSS', 'XML_SITEMAP']);
            $table->string('url', 2048);
            $table->enum('monitoring_interval', [
                'EVERY_10_MIN', 'EVERY_30_MIN', 'HOURLY',
                'EVERY_6_HOURS', 'DAILY', 'WEEKLY',
            ]);
            $table->boolean('is_active')->default(true);
            $table->boolean('should_notify')->default(false);
            $table->boolean('auto_summarize')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'is_active', 'next_check_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
