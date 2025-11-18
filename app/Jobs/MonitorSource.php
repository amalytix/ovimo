<?php

namespace App\Jobs;

use App\Models\Source;
use App\Services\KeywordFilterService;
use App\Services\SourceParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class MonitorSource implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public Source $source
    ) {}

    public function handle(SourceParser $parser, KeywordFilterService $keywordFilter): void
    {
        if (! $this->source->is_active) {
            return;
        }

        try {
            $items = $parser->parse($this->source->url, $this->source->type, null, $this->source);

            // Apply team-level keyword filtering (unless bypassed for this source)
            if (! $this->source->bypass_keyword_filter) {
                $team = $this->source->team;
                $items = $keywordFilter->filterSourceItems($items, $team);
            }

            $newPostsCount = 0;
            $newPosts = [];

            foreach ($items as $item) {
                $post = $this->source->posts()->firstOrCreate(
                    ['uri' => $item['uri']],
                    [
                        'external_title' => $item['title'] ?? null,
                        'internal_title' => null,
                        'summary' => null,
                        'relevancy_score' => null,
                        'is_read' => false,
                        'is_hidden' => false,
                        'status' => 'NOT_RELEVANT',
                        'found_at' => now(),
                    ]
                );

                if ($post->wasRecentlyCreated) {
                    $newPostsCount++;

                    // Collect new post data for webhook notification
                    $newPosts[] = [
                        'title' => $post->external_title,
                        'url' => $post->uri,
                    ];

                    // Queue summarization if enabled
                    if ($this->source->auto_summarize) {
                        SummarizePost::dispatch($post);
                    }
                }
            }

            // Update source timestamps
            $this->source->update([
                'last_checked_at' => now(),
                'next_check_at' => $this->source->calculateNextCheckTime(),
            ]);

            Log::info("Monitored source {$this->source->id}: found {$newPostsCount} new posts");

            // Send webhook notification if enabled and new posts found
            if ($this->source->should_notify && $newPostsCount > 0) {
                SendWebhookNotification::dispatchForEvent(
                    'NEW_POSTS',
                    $this->source->team_id,
                    [
                        'source_id' => $this->source->id,
                        'source_name' => $this->source->internal_name,
                        'new_posts_count' => $newPostsCount,
                        'posts' => $newPosts,
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error("Failed to monitor source {$this->source->id}: {$e->getMessage()}");
            throw $e;
        }
    }
}
