<?php

namespace App\Jobs;

use App\Models\Source;
use App\Services\KeywordFilterService;
use App\Services\SourceParser;
use App\Services\TokenLimitService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class MonitorSource implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 300; // 5 minutes between retries

    public int $timeout = 300;

    public function __construct(
        public Source $source
    ) {}

    public function handle(SourceParser $parser, KeywordFilterService $keywordFilter, TokenLimitService $tokenLimitService): void
    {
        // Acquire exclusive lock on this source to prevent duplicate processing
        // This works even with database cache driver and multiple queue workers
        $locked = \DB::transaction(function () {
            $source = Source::where('id', $this->source->id)
                ->lockForUpdate()
                ->first();

            if (! $source) {
                return false;
            }

            // Check if another worker already processed this in the last 30 seconds
            if ($source->last_checked_at && $source->last_checked_at->gt(now()->subSeconds(30))) {
                Log::info("MonitorSource job skipped: source {$source->id} was recently checked by another worker", [
                    'last_checked_at' => $source->last_checked_at->toDateTimeString(),
                ]);

                return false;
            }

            // Mark as being checked now to prevent other workers from processing
            $source->update(['last_checked_at' => now()]);

            return true;
        });

        if (! $locked) {
            return;
        }

        // Reload source to get fresh data after transaction commit
        $this->source->refresh();

        if (! $this->source->is_active) {
            Log::info("MonitorSource job skipped: source {$this->source->id} is inactive");

            return;
        }

        Log::info("MonitorSource job started for source {$this->source->id}", [
            'current_next_check_at' => $this->source->next_check_at?->toDateTimeString(),
        ]);

        $canSummarize = true;

        try {
            $tokenLimitService->assertWithinLimit($this->source->team, 0, null, 'post_summarization');
        } catch (\Throwable $e) {
            $canSummarize = false;
            Log::warning("Skipping auto-summarization for source {$this->source->id}: token limit exceeded");
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
                        'metadata' => $item['metadata'] ?? null,
                        'is_hidden' => false,
                        'status' => 'NOT_RELEVANT',
                        'found_at' => now(),
                    ]
                );

                if ($post->wasRecentlyCreated) {
                    $newPostsCount++;

                    // Dispatch post found event
                    event(new \App\Events\PostFound($post, $this->source));

                    // Collect new post data for webhook notification
                    $newPosts[] = [
                        'title' => $post->external_title,
                        'url' => $post->uri,
                    ];

                    // Queue summarization if enabled
                    if ($this->source->auto_summarize && $canSummarize) {
                        SummarizePost::dispatch($post);
                    }
                }
            }

            // Calculate next check time once
            $nextCheck = $this->source->calculateNextCheckTime();

            // Update source timestamps and reset failure tracking
            $this->source->update([
                'last_checked_at' => now(),
                'next_check_at' => $nextCheck,
                'consecutive_failures' => 0,
                'failed_at' => null,
                'last_run_status' => 'success',
                'last_run_error' => null,
            ]);

            Log::info("Monitored source {$this->source->id}: found {$newPostsCount} new posts", [
                'next_check_at' => $nextCheck->toDateTimeString(),
            ]);

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
            // Increment failure counter
            $consecutiveFailures = $this->source->consecutive_failures + 1;

            // Auto-disable source after 3 consecutive failures
            $isActive = $this->source->is_active;
            if ($consecutiveFailures >= 3) {
                $isActive = false;
                Log::warning("Source {$this->source->id} disabled after {$consecutiveFailures} consecutive failures");
            }

            // Calculate next check time even on failure to prevent infinite retries
            $nextCheck = $this->source->calculateNextCheckTime();

            $this->source->update([
                'consecutive_failures' => $consecutiveFailures,
                'failed_at' => now(),
                'is_active' => $isActive,
                'next_check_at' => $nextCheck,
                'last_run_status' => 'error',
                'last_run_error' => $e->getMessage(),
            ]);

            Log::error("Failed to monitor source {$this->source->id}: {$e->getMessage()}", [
                'next_check_at' => $nextCheck->toDateTimeString(),
            ]);

            // Dispatch source monitoring failed event
            event(new \App\Events\SourceMonitoringFailed($this->source, $e->getMessage()));

            throw $e;
        }
    }
}
