<?php

namespace App\Jobs;

use App\Exceptions\AINotConfiguredException;
use App\Exceptions\TokenLimitExceededException;
use App\Models\Post;
use App\Services\AIServiceFactory;
use App\Services\TokenLimitService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SummarizePost implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct(
        public Post $post
    ) {}

    public function handle(AIServiceFactory $aiFactory, TokenLimitService $tokenLimitService): void
    {
        // Skip if already summarized
        if ($this->post->summary !== null) {
            return;
        }

        $team = $this->post->source->team;

        try {
            $openAI = $aiFactory->forOpenAI($team);
            $tokenLimitService->assertWithinLimit($team, 0, null, 'post_summarization');

            $result = $openAI->summarizePost($this->post->uri, $team);

            $this->post->update([
                'summary' => $result['summary'],
                'relevancy_score' => $result['relevancy_score'],
                'internal_title' => $result['internal_title'],
            ]);

            // Track token usage
            $openAI->trackUsage(
                $result['input_tokens'],
                $result['output_tokens'],
                $result['total_tokens'],
                $result['model'],
                null,
                $team,
                'post_summarization'
            );

            Log::info("Summarized post {$this->post->id}: score {$result['relevancy_score']}, tokens: {$result['total_tokens']}");
        } catch (AINotConfiguredException $e) {
            Log::warning("Skipping summarization for post {$this->post->id}: {$e->getMessage()}");

            return;
        } catch (\Throwable $e) {
            if ($e instanceof TokenLimitExceededException) {
                Log::warning("Skipping summarization for post {$this->post->id}: token limit exceeded");

                return;
            }

            Log::error("Failed to summarize post {$this->post->id}: {$e->getMessage()}");
            throw $e;
        }
    }
}
