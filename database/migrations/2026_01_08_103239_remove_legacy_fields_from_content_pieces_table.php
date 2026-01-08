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
        // Drop index separately with error handling for different database drivers
        if (Schema::hasColumn('content_pieces', 'publish_status')) {
            try {
                Schema::table('content_pieces', function (Blueprint $table) {
                    $table->dropIndex(['publish_status', 'scheduled_publish_at']);
                });
            } catch (\Exception $e) {
                // Index may not exist or have different name in SQLite
            }
        }

        // Drop columns that exist
        $columnsToDrop = [
            'research_text',
            'edited_text',
            'generation_status',
            'generation_error',
            'generation_error_occurred_at',
            'publish_to_platforms',
            'published_platforms',
            'scheduled_publish_at',
            'publish_status',
        ];

        $existingColumns = array_filter($columnsToDrop, fn ($col) => Schema::hasColumn('content_pieces', $col));

        if (! empty($existingColumns)) {
            Schema::table('content_pieces', function (Blueprint $table) use ($existingColumns) {
                $table->dropColumn($existingColumns);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_pieces', function (Blueprint $table) {
            $table->longText('research_text')->nullable();
            $table->longText('edited_text')->nullable();
            $table->string('generation_status')->default('NOT_STARTED');
            $table->text('generation_error')->nullable();
            $table->timestamp('generation_error_occurred_at')->nullable();
            $table->json('publish_to_platforms')->nullable();
            $table->json('published_platforms')->nullable();
            $table->timestampTz('scheduled_publish_at')->nullable();
            $table->enum('publish_status', ['not_published', 'scheduled', 'publishing', 'published', 'failed'])
                ->default('not_published');
            $table->index(['publish_status', 'scheduled_publish_at']);
        });
    }
};
