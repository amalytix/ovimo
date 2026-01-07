<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $defaultChannels = [
        ['name' => 'Blog Post', 'slug' => 'blog_post', 'icon' => 'file-text', 'color' => 'blue-500'],
        ['name' => 'LinkedIn', 'slug' => 'linkedin_post', 'icon' => 'linkedin', 'color' => 'sky-600'],
        ['name' => 'YouTube Script', 'slug' => 'youtube_script', 'icon' => 'youtube', 'color' => 'red-500'],
        ['name' => 'Reddit', 'slug' => 'reddit_post', 'icon' => 'message-circle', 'color' => 'orange-500'],
    ];

    private array $channelSlugMap = [
        'BLOG_POST' => 'blog_post',
        'LINKEDIN_POST' => 'linkedin_post',
        'YOUTUBE_SCRIPT' => 'youtube_script',
    ];

    public function up(): void
    {
        // 1. Create default channels for each team
        $teams = DB::table('teams')->select('id')->get();

        foreach ($teams as $team) {
            foreach ($this->defaultChannels as $index => $channel) {
                DB::table('channels')->insert([
                    'team_id' => $team->id,
                    'name' => $channel['name'],
                    'slug' => $channel['slug'],
                    'icon' => $channel['icon'],
                    'color' => $channel['color'],
                    'sort_order' => $index,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. Migrate content_piece_post pivot to background_sources (type=POST)
        $pivotRecords = DB::table('content_piece_post')->get();

        foreach ($pivotRecords as $pivot) {
            DB::table('background_sources')->insert([
                'content_piece_id' => $pivot->content_piece_id,
                'type' => 'POST',
                'post_id' => $pivot->post_id,
                'title' => null,
                'content' => null,
                'url' => null,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Migrate briefing_text to background_sources (type=MANUAL) if not empty
        $contentPiecesWithBriefing = DB::table('content_pieces')
            ->whereNotNull('briefing_text')
            ->where('briefing_text', '!=', '')
            ->get(['id', 'briefing_text']);

        foreach ($contentPiecesWithBriefing as $piece) {
            // Get the max sort_order for this content piece
            $maxSortOrder = DB::table('background_sources')
                ->where('content_piece_id', $piece->id)
                ->max('sort_order') ?? -1;

            DB::table('background_sources')->insert([
                'content_piece_id' => $piece->id,
                'type' => 'MANUAL',
                'post_id' => null,
                'title' => 'Briefing',
                'content' => $piece->briefing_text,
                'url' => null,
                'sort_order' => $maxSortOrder + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Create content_derivatives from existing content_pieces
        $contentPieces = DB::table('content_pieces')
            ->join('teams', 'content_pieces.team_id', '=', 'teams.id')
            ->select([
                'content_pieces.id',
                'content_pieces.team_id',
                'content_pieces.channel',
                'content_pieces.prompt_id',
                'content_pieces.internal_name',
                'content_pieces.research_text',
                'content_pieces.edited_text',
                'content_pieces.status',
                'content_pieces.published_at',
                'content_pieces.generation_status',
                'content_pieces.generation_error',
                'content_pieces.generation_error_occurred_at',
            ])
            ->get();

        foreach ($contentPieces as $piece) {
            // Find the matching channel for this content piece
            $channelSlug = $this->channelSlugMap[$piece->channel] ?? null;

            if (! $channelSlug) {
                continue;
            }

            $channel = DB::table('channels')
                ->where('team_id', $piece->team_id)
                ->where('slug', $channelSlug)
                ->first();

            if (! $channel) {
                continue;
            }

            // Map old generation_status to new enum values
            $generationStatus = match ($piece->generation_status) {
                'NOT_STARTED' => 'IDLE',
                'GENERATING' => 'PROCESSING',
                'COMPLETED' => 'COMPLETED',
                'FAILED' => 'FAILED',
                default => 'IDLE',
            };

            // Use edited_text if available, otherwise research_text
            $text = $piece->edited_text ?: $piece->research_text;

            DB::table('content_derivatives')->insert([
                'content_piece_id' => $piece->id,
                'channel_id' => $channel->id,
                'prompt_id' => $piece->prompt_id,
                'title' => $piece->internal_name,
                'text' => $text,
                'status' => $piece->status,
                'is_published' => $piece->published_at !== null,
                'planned_publish_at' => null,
                'published_at' => $piece->published_at,
                'generation_status' => $generationStatus,
                'generation_error' => $piece->generation_error,
                'generation_error_occurred_at' => $piece->generation_error_occurred_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 5. Update existing prompts to reference new channel_id
        $prompts = DB::table('prompts')
            ->whereNotNull('channel')
            ->get(['id', 'team_id', 'channel']);

        foreach ($prompts as $prompt) {
            $channelSlug = $this->channelSlugMap[$prompt->channel] ?? null;

            if (! $channelSlug) {
                continue;
            }

            $channel = DB::table('channels')
                ->where('team_id', $prompt->team_id)
                ->where('slug', $channelSlug)
                ->first();

            if ($channel) {
                DB::table('prompts')
                    ->where('id', $prompt->id)
                    ->update(['channel_id' => $channel->id]);
            }
        }
    }

    public function down(): void
    {
        // Clear the migrated data
        DB::table('content_derivatives')->truncate();
        DB::table('background_sources')->truncate();
        DB::table('channels')->truncate();

        // Clear channel_id from prompts
        DB::table('prompts')->update(['channel_id' => null]);
    }
};
