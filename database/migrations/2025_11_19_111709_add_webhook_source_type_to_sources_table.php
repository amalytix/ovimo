<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite, we need to recreate the type column to add WEBHOOK to the enum
        // This is necessary because SQLite doesn't support ALTER COLUMN for CHECK constraints
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite approach: recreate table with new CHECK constraint
            DB::statement('PRAGMA foreign_keys=off');

            // Create new table with updated type enum
            DB::statement('
                CREATE TABLE sources_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    team_id INTEGER NOT NULL,
                    internal_name TEXT NOT NULL,
                    type TEXT CHECK(type IN (\'RSS\', \'XML_SITEMAP\', \'WEBSITE\', \'WEBHOOK\')) NOT NULL,
                    url TEXT NOT NULL,
                    css_selector_title TEXT,
                    css_selector_link TEXT,
                    keywords TEXT,
                    monitoring_interval TEXT CHECK(monitoring_interval IN (\'EVERY_10_MIN\', \'EVERY_30_MIN\', \'HOURLY\', \'EVERY_6_HOURS\', \'DAILY\', \'WEEKLY\')) NOT NULL,
                    is_active INTEGER DEFAULT 1 NOT NULL,
                    should_notify INTEGER DEFAULT 0 NOT NULL,
                    auto_summarize INTEGER DEFAULT 1 NOT NULL,
                    last_checked_at TEXT,
                    consecutive_failures INTEGER DEFAULT 0 NOT NULL,
                    failed_at TEXT,
                    next_check_at TEXT,
                    created_at TEXT,
                    updated_at TEXT,
                    deleted_at TEXT,
                    bypass_keyword_filter INTEGER DEFAULT 0 NOT NULL,
                    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
                )
            ');

            // Copy data
            DB::statement('
                INSERT INTO sources_new
                SELECT id, team_id, internal_name, type, url, css_selector_title, css_selector_link, keywords,
                       monitoring_interval, is_active, should_notify, auto_summarize,
                       last_checked_at, consecutive_failures, failed_at, next_check_at, created_at, updated_at, deleted_at, bypass_keyword_filter
                FROM sources
            ');

            // Drop old table and rename new one
            DB::statement('DROP TABLE sources');
            DB::statement('ALTER TABLE sources_new RENAME TO sources');

            // Recreate indexes
            DB::statement('CREATE INDEX sources_team_id_is_active_next_check_at_index ON sources (team_id, is_active, next_check_at)');

            DB::statement('PRAGMA foreign_keys=on');
        } else {
            // For MySQL/PostgreSQL, modify the enum column directly
            DB::statement("ALTER TABLE sources MODIFY COLUMN type ENUM('RSS', 'XML_SITEMAP', 'WEBSITE', 'WEBHOOK') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: We don't remove WEBHOOK from enum in down() to avoid data loss
    }
};
