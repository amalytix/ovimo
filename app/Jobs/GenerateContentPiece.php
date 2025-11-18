<?php

namespace App\Jobs;

use App\Events\ContentPieceGenerated;
use App\Events\ContentPieceGenerationFailed;
use App\Models\ContentPiece;
use App\Services\OpenAIService;
use App\Services\WebContentExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateContentPiece implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 120;

    public int $timeout = 300;

    public function __construct(
        public ContentPiece $contentPiece
    ) {}

    public function handle(OpenAIService $openAI, WebContentExtractor $extractor): void
    {
        // Update status to PROCESSING
        $this->contentPiece->update([
            'generation_status' => 'PROCESSING',
            'generation_error' => null,
            'generation_error_occurred_at' => null,
        ]);

        try {
            // 1. Build context from linked posts
            $context = $this->buildContextFromPosts($extractor);

            // 2. Build final prompt with placeholders replaced
            $finalPrompt = $this->buildFinalPrompt($context);

            // 3. Call OpenAI
            $result = $openAI->generateContent($finalPrompt, '');

            // 4. Update content piece - SUCCESS
            $this->contentPiece->update([
                'full_text' => $result['content'],
                'status' => 'DRAFT',
                'generation_status' => 'COMPLETED',
            ]);

            // 5. Track usage
            $team = $this->contentPiece->team;
            $owner = $team->owner;
            $openAI->trackUsage(
                $result['input_tokens'],
                $result['output_tokens'],
                $result['total_tokens'],
                $result['model'],
                $owner,
                $team,
                'content_generation'
            );

            // 6. Dispatch success event
            ContentPieceGenerated::dispatch($this->contentPiece);

            Log::info("Generated content for piece {$this->contentPiece->id}: {$result['total_tokens']} tokens");
        } catch (\Exception $e) {
            Log::error("Failed to generate content for piece {$this->contentPiece->id}: {$e->getMessage()}");
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        // After all retries failed, mark as FAILED
        $this->contentPiece->update([
            'generation_status' => 'FAILED',
            'generation_error' => $this->categorizeError($exception),
            'generation_error_occurred_at' => now(),
        ]);

        // Dispatch failure event
        ContentPieceGenerationFailed::dispatch($this->contentPiece, $exception);

        Log::error("Content generation failed permanently for piece {$this->contentPiece->id}: {$exception->getMessage()}");
    }

    private function buildContextFromPosts(WebContentExtractor $extractor): string
    {
        $context = '';
        $articleCounter = 1;

        foreach ($this->contentPiece->posts as $post) {
            $title = $post->external_title ?? $post->internal_title ?? "Article {$articleCounter}";
            $fullContent = $extractor->extractArticleAsMarkdown($post->uri);
            $context .= "### {$title}\n\nURL: {$post->uri}\nSummary: {$post->summary}\nFull Content:\n{$fullContent}\n\n";
            $articleCounter++;
        }

        if ($this->contentPiece->briefing_text) {
            $context .= "## Additional briefing\n\n{$this->contentPiece->briefing_text}\n\n";
        }

        $context .= "Target channel: {$this->contentPiece->channel}\n";
        $context .= "Target language: {$this->contentPiece->target_language}\n";

        return $context;
    }

    private function buildFinalPrompt(string $context): string
    {
        $promptText = $this->contentPiece->prompt->prompt_text;
        $promptText = str_replace('{{context}}', $context, $promptText);
        $promptText = str_replace('{{channel}}', $this->contentPiece->channel, $promptText);
        $promptText = str_replace('{{language}}', $this->contentPiece->target_language, $promptText);

        return $promptText;
    }

    private function categorizeError(\Throwable $e): string
    {
        $message = $e->getMessage();

        // Check for timeout
        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return 'OpenAI request timed out after 5 minutes. Please try again.';
        }

        // Check for rate limit
        if (str_contains($message, 'rate limit') || str_contains($message, 'Rate limit')) {
            return 'OpenAI rate limit exceeded. Please try again later.';
        }

        // Generic error
        return 'Error: '.$message;
    }
}
