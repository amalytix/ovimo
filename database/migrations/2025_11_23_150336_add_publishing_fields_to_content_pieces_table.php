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
            $table->json('publish_to_platforms')->nullable()->after('published_at');
            $table->json('published_platforms')->nullable()->after('publish_to_platforms');
            $table->timestampTz('scheduled_publish_at')->nullable()->after('published_platforms');
            $table->enum('publish_status', ['not_published', 'scheduled', 'publishing', 'published', 'failed'])
                ->default('not_published')
                ->after('scheduled_publish_at');
            $table->index(['publish_status', 'scheduled_publish_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_pieces', function (Blueprint $table) {
            $table->dropIndex(['publish_status', 'scheduled_publish_at']);
            $table->dropColumn([
                'publish_to_platforms',
                'published_platforms',
                'scheduled_publish_at',
                'publish_status',
            ]);
        });
    }
};
