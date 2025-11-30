<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite doesn't support modifying columns directly, so we need to recreate the table
        if (DB::connection()->getDriverName() === 'sqlite') {
            // For SQLite, we'll need to recreate the table
            Schema::create('activity_logs_new', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('event_type', 50);
                $table->enum('level', ['info', 'warning', 'error'])->default('info');
                $table->text('description');
                $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at');
            });

            // Copy data
            DB::statement('INSERT INTO activity_logs_new SELECT * FROM activity_logs');

            // Drop old table and rename new one
            Schema::drop('activity_logs');
            Schema::rename('activity_logs_new', 'activity_logs');

            // Recreate indexes
            DB::statement('CREATE INDEX activity_logs_team_id_created_at_index ON activity_logs (team_id, created_at)');
            DB::statement('CREATE INDEX activity_logs_team_id_event_type_created_at_index ON activity_logs (team_id, event_type, created_at)');
            DB::statement('CREATE INDEX activity_logs_user_id_created_at_index ON activity_logs (user_id, created_at)');
            DB::statement('CREATE INDEX activity_logs_level_created_at_index ON activity_logs (level, created_at)');
        } else {
            // For MySQL/PostgreSQL
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->foreignId('team_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            // For SQLite, recreate with NOT NULL
            Schema::create('activity_logs_new', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('event_type', 50);
                $table->enum('level', ['info', 'warning', 'error'])->default('info');
                $table->text('description');
                $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at');
            });

            DB::statement('INSERT INTO activity_logs_new SELECT * FROM activity_logs WHERE team_id IS NOT NULL');
            Schema::drop('activity_logs');
            Schema::rename('activity_logs_new', 'activity_logs');

            DB::statement('CREATE INDEX activity_logs_team_id_created_at_index ON activity_logs (team_id, created_at)');
            DB::statement('CREATE INDEX activity_logs_team_id_event_type_created_at_index ON activity_logs (team_id, event_type, created_at)');
            DB::statement('CREATE INDEX activity_logs_user_id_created_at_index ON activity_logs (user_id, created_at)');
            DB::statement('CREATE INDEX activity_logs_level_created_at_index ON activity_logs (level, created_at)');
        } else {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->foreignId('team_id')->nullable(false)->change();
            });
        }
    }
};
