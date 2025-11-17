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
        DB::table('posts')
            ->whereNull('uri_hash')
            ->orWhere('uri_hash', '')
            ->orderBy('id')
            ->chunk(1000, function ($posts) {
                foreach ($posts as $post) {
                    DB::table('posts')
                        ->where('id', $post->id)
                        ->update(['uri_hash' => hash('sha256', $post->uri)]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse - the hashes are derived from uri
    }
};
