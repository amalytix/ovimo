<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\OpenAIService;
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

    public function handle(OpenAIService $openAI): void
    {
        // Skip if already summarized
        if ($this->post->summary !== null) {
            return;
        }

        $team = $this->post->source->team;
        $owner = $team->owner;

        try {
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
                $owner,
                $team,
                'post_summarization'
            );

            Log::info("Summarized post {$this->post->id}: score {$result['relevancy_score']}, tokens: {$result['total_tokens']}");
        } catch (\Exception $e) {
            Log::error("Failed to summarize post {$this->post->id}: {$e->getMessage()}");
            throw $e;
        }
    }
}
