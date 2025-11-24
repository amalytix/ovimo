<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Move scheduled_publish_at into published_at when published_at is empty
        DB::table('content_pieces')
            ->whereNull('published_at')
            ->whereNotNull('scheduled_publish_at')
            ->update(['published_at' => DB::raw('scheduled_publish_at')]);

        Schema::table('content_pieces', function (Blueprint $table) {
            $table->dropIndex(['publish_status', 'scheduled_publish_at']);
            $table->dropColumn(['scheduled_publish_at', 'publish_status']);
        });
    }

    public function down(): void
    {
        Schema::table('content_pieces', function (Blueprint $table) {
            $table->timestampTz('scheduled_publish_at')->nullable()->after('published_platforms');
            $table->enum('publish_status', ['not_published', 'scheduled', 'publishing', 'published', 'failed'])
                ->default('not_published')
                ->after('scheduled_publish_at');
        });

        DB::table('content_pieces')
            ->whereNotNull('published_at')
            ->update(['scheduled_publish_at' => DB::raw('published_at')]);

        Schema::table('content_pieces', function (Blueprint $table) {
            $table->index(['publish_status', 'scheduled_publish_at']);
        });
    }
};
