<?php

namespace App\Jobs;

use App\Exceptions\AINotConfiguredException;
use App\Exceptions\TokenLimitExceededException;
use App\Models\ContentDerivative;
use App\Services\AIServiceFactory;
use App\Services\TokenLimitService;
use App\Services\WebContentExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateContentDerivative implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 120;

    public int $timeout = 300;

    public function __construct(
        public ContentDerivative $derivative
    ) {}

    public function handle(AIServiceFactory $aiFactory, WebContentExtractor $extractor, TokenLimitService $tokenLimitService): void
    {
        $this->derivative->update([
            'generation_status' => ContentDerivative::GENERATION_PROCESSING,
            'generation_error' => null,
            'generation_error_occurred_at' => null,
        ]);

        try {
            $contentPiece = $this->derivative->contentPiece;
            $team = $contentPiece->team;
            $channel = $this->derivative->channel;

            $openAI = $aiFactory->forOpenAI($team);

            $tokenLimitService->assertWithinLimit($team, 0, null, 'content_generation');

            // 1. Build context from background sources
            $context = $this->buildContextFromSources($extractor);

            // 2. Build final prompt with placeholders replaced
            $finalPrompt = $this->buildFinalPrompt($context);

            // 3. Call OpenAI
            $result = $openAI->generateContent($finalPrompt, '');

            // 4. Parse title from generated content (first line if starts with #)
            $content = $result['content'];
            $title = $this->extractTitle($content);

            // 5. Update derivative - SUCCESS
            $this->derivative->update([
                'title' => $title ?? $this->derivative->title,
                'text' => $content,
                'status' => ContentDerivative::STATUS_DRAFT,
                'generation_status' => ContentDerivative::GENERATION_COMPLETED,
            ]);

            // 6. Track usage
            $openAI->trackUsage(
                $result['input_tokens'],
                $result['output_tokens'],
                $result['total_tokens'],
                $result['model'],
                null,
                $team,
                'content_generation'
            );

            Log::info("Generated derivative {$this->derivative->id} for piece {$contentPiece->id}: {$result['total_tokens']} tokens");
        } catch (AINotConfiguredException $e) {
            $this->derivative->update([
                'generation_status' => ContentDerivative::GENERATION_FAILED,
                'generation_error' => 'OpenAI is not configured for this team. Add an API key in the AI tab of Team Settings.',
                'generation_error_occurred_at' => now(),
            ]);

            Log::warning("Skipping derivative generation for {$this->derivative->id}: {$e->getMessage()}");

            return;
        } catch (\Throwable $e) {
            if ($e instanceof TokenLimitExceededException) {
                $this->derivative->update([
                    'generation_status' => ContentDerivative::GENERATION_FAILED,
                    'generation_error' => 'Monthly token limit exceeded. Please increase the limit or wait until next month.',
                    'generation_error_occurred_at' => now(),
                ]);

                Log::warning("Skipping derivative generation for {$this->derivative->id}: token limit exceeded");

                return;
            }

            Log::error("Failed to generate derivative {$this->derivative->id}: {$e->getMessage()}");
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->derivative->update([
            'generation_status' => ContentDerivative::GENERATION_FAILED,
            'generation_error' => $this->categorizeError($exception),
            'generation_error_occurred_at' => now(),
        ]);

        Log::error("Derivative generation failed permanently for {$this->derivative->id}: {$exception->getMessage()}");
    }

    private function buildContextFromSources(WebContentExtractor $extractor): string
    {
        $context = '';
        $sourceCounter = 1;

        $contentPiece = $this->derivative->contentPiece;

        foreach ($contentPiece->backgroundSources as $source) {
            if ($source->isPost() && $source->post) {
                $post = $source->post;
                $title = $post->external_title ?? $post->internal_title ?? "Source {$sourceCounter}";
                $fullContent = $extractor->extractArticleAsMarkdown($post->uri);
                $context .= "### {$title}\n\nURL: {$post->uri}\nSummary: {$post->summary}\nFull Content:\n{$fullContent}\n\n";
            } elseif ($source->isManual()) {
                $title = $source->title ?? "Source {$sourceCounter}";
                $context .= "### {$title}\n\n";
                if ($source->url) {
                    $context .= "URL: {$source->url}\n";
                }
                $context .= "Content:\n{$source->content}\n\n";
            }
            $sourceCounter++;
        }

        $channel = $this->derivative->channel;
        $context .= "Target channel: {$channel->name}\n";
        $context .= "Target language: {$contentPiece->target_language}\n";

        return $context;
    }

    private function buildFinalPrompt(string $context): string
    {
        // Use the derivative's prompt, or channel's default prompt, or content piece's prompt
        $prompt = $this->derivative->prompt
            ?? $this->derivative->channel->prompts()->where('is_default', true)->first()
            ?? $this->derivative->contentPiece->prompt;

        if (! $prompt) {
            throw new \RuntimeException('No prompt found for derivative generation');
        }

        $promptText = $prompt->prompt_text;
        $promptText = str_replace('{{context}}', $context, $promptText);
        $promptText = str_replace('{{channel}}', $this->derivative->channel->name, $promptText);
        $promptText = str_replace('{{language}}', $this->derivative->contentPiece->target_language, $promptText);

        return $promptText;
    }

    private function extractTitle(string $content): ?string
    {
        $lines = explode("\n", $content);
        $firstLine = trim($lines[0] ?? '');

        if (str_starts_with($firstLine, '# ')) {
            return trim(substr($firstLine, 2));
        }

        if (str_starts_with($firstLine, '## ')) {
            return trim(substr($firstLine, 3));
        }

        return null;
    }

    private function categorizeError(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return 'OpenAI request timed out after 5 minutes. Please try again.';
        }

        if (str_contains($message, 'rate limit') || str_contains($message, 'Rate limit')) {
            return 'OpenAI rate limit exceeded. Please try again later.';
        }

        return 'Error: '.$message;
    }
}
