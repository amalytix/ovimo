<?php

namespace App\Http\Controllers;

use App\Exceptions\AINotConfiguredException;
use App\Http\Requests\ImageGeneration\StoreImageGenerationRequest;
use App\Http\Requests\ImageGeneration\UpdateImageGenerationRequest;
use App\Jobs\GenerateAIImage;
use App\Models\ContentPiece;
use App\Models\ImageGeneration;
use App\Models\Prompt;
use App\Services\AIServiceFactory;
use App\Services\WebContentExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ImageGenerationController extends Controller
{
    public function store(
        StoreImageGenerationRequest $request,
        ContentPiece $contentPiece,
        AIServiceFactory $aiFactory,
        WebContentExtractor $extractor
    ): JsonResponse {
        $this->authorize('update', $contentPiece);

        $validated = $request->validated();
        $team = $contentPiece->team;

        // Get the image prompt template
        $prompt = Prompt::where('team_id', $team->id)
            ->where('id', $validated['prompt_id'])
            ->where('type', Prompt::TYPE_IMAGE)
            ->firstOrFail();

        // Build context from sources (fetches article content for POST-type sources)
        $contentText = $this->buildContextFromSources($contentPiece, $extractor);

        if (empty(trim($contentText))) {
            return response()->json([
                'message' => 'Content piece has no sources. Please add sources in the Sources tab first.',
            ], 422);
        }

        try {
            $openAI = $aiFactory->forOpenAI($team);
        } catch (AINotConfiguredException $e) {
            return $this->aiNotConfiguredResponse($e);
        }

        // Generate image prompt using OpenAI
        $result = $openAI->generateImagePrompt($contentText, $prompt->prompt_text);

        // Track token usage
        $openAI->trackUsage(
            $result['input_tokens'],
            $result['output_tokens'],
            $result['total_tokens'],
            $result['model'],
            $request->user(),
            $team,
            'image_prompt_generation'
        );

        // Create the image generation record
        $imageGeneration = ImageGeneration::create([
            'content_piece_id' => $contentPiece->id,
            'prompt_id' => $prompt->id,
            'generated_text_prompt' => $result['prompt'],
            'aspect_ratio' => $validated['aspect_ratio'] ?? ImageGeneration::ASPECT_RATIO_16_9,
            'status' => ImageGeneration::STATUS_DRAFT,
        ]);

        $imageGeneration->load('prompt', 'media');

        return response()->json([
            'image_generation' => $this->formatImageGeneration($imageGeneration),
        ], 201);
    }

    public function update(UpdateImageGenerationRequest $request, ContentPiece $contentPiece, ImageGeneration $imageGeneration): JsonResponse
    {
        $this->authorize('update', $contentPiece);

        if ($imageGeneration->content_piece_id !== $contentPiece->id) {
            abort(404);
        }

        $validated = $request->validated();

        $imageGeneration->update([
            'generated_text_prompt' => $validated['generated_text_prompt'],
            'aspect_ratio' => $validated['aspect_ratio'] ?? $imageGeneration->aspect_ratio,
        ]);

        $imageGeneration->load('prompt', 'media');

        return response()->json([
            'image_generation' => $this->formatImageGeneration($imageGeneration),
        ]);
    }

    public function generate(Request $request, ContentPiece $contentPiece, ImageGeneration $imageGeneration, AIServiceFactory $aiFactory): JsonResponse
    {
        $this->authorize('update', $contentPiece);

        if ($imageGeneration->content_piece_id !== $contentPiece->id) {
            abort(404);
        }

        if (empty($imageGeneration->generated_text_prompt)) {
            return response()->json([
                'message' => 'Image generation has no text prompt. Generate a prompt first.',
            ], 422);
        }

        if ($imageGeneration->status === ImageGeneration::STATUS_GENERATING) {
            return response()->json([
                'message' => 'Image is already being generated.',
            ], 422);
        }

        try {
            $aiFactory->forGemini($contentPiece->team);
        } catch (AINotConfiguredException $e) {
            return $this->aiNotConfiguredResponse($e);
        }

        // Queue the job
        GenerateAIImage::dispatch($imageGeneration);

        $imageGeneration->update([
            'status' => ImageGeneration::STATUS_GENERATING,
        ]);

        return response()->json([
            'image_generation' => $this->formatImageGeneration($imageGeneration),
            'message' => 'Image generation started.',
        ]);
    }

    public function status(Request $request, ContentPiece $contentPiece, ImageGeneration $imageGeneration): JsonResponse
    {
        $this->authorize('view', $contentPiece);

        if ($imageGeneration->content_piece_id !== $contentPiece->id) {
            abort(404);
        }

        $imageGeneration->load('prompt', 'media');

        return response()->json([
            'image_generation' => $this->formatImageGeneration($imageGeneration),
        ]);
    }

    public function destroy(Request $request, ContentPiece $contentPiece, ImageGeneration $imageGeneration): JsonResponse
    {
        $this->authorize('update', $contentPiece);

        if ($imageGeneration->content_piece_id !== $contentPiece->id) {
            abort(404);
        }

        $imageGeneration->delete();

        return response()->json([
            'message' => 'Image generation deleted.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatImageGeneration(ImageGeneration $imageGeneration): array
    {
        return [
            'id' => $imageGeneration->id,
            'content_piece_id' => $imageGeneration->content_piece_id,
            'prompt_id' => $imageGeneration->prompt_id,
            'prompt' => $imageGeneration->prompt ? [
                'id' => $imageGeneration->prompt->id,
                'internal_name' => $imageGeneration->prompt->internal_name,
            ] : null,
            'generated_text_prompt' => $imageGeneration->generated_text_prompt,
            'aspect_ratio' => $imageGeneration->aspect_ratio,
            'status' => $imageGeneration->status,
            'media_id' => $imageGeneration->media_id,
            'media' => $imageGeneration->media ? [
                'id' => $imageGeneration->media->id,
                'filename' => $imageGeneration->media->filename,
                'mime_type' => $imageGeneration->media->mime_type,
                'temporary_url' => $imageGeneration->media->getTemporaryUrl(),
            ] : null,
            'error_message' => $imageGeneration->error_message,
            'created_at' => $imageGeneration->created_at?->toDateTimeString(),
            'updated_at' => $imageGeneration->updated_at?->toDateTimeString(),
        ];
    }

    private function aiNotConfiguredResponse(AINotConfiguredException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'settings_url' => $exception->settingsUrl,
            'provider' => $exception->provider,
        ], 422);
    }

    /**
     * Build context from content piece sources for image prompt generation.
     * For POST-type sources, fetches article content from the URL.
     * For MANUAL-type sources, uses the stored content.
     */
    private function buildContextFromSources(ContentPiece $contentPiece, WebContentExtractor $extractor): string
    {
        $maxWordsPerSource = 3000;
        $maxTotalWords = 10000;
        $combinedParts = [];
        $totalWords = 0;

        // Helper to add content with word limits
        $addContent = function (string $content) use (&$combinedParts, &$totalWords, $maxWordsPerSource, $maxTotalWords): bool {
            if (empty(trim($content))) {
                return true;
            }

            $words = preg_split('/\s+/', trim($content), -1, PREG_SPLIT_NO_EMPTY);
            if (count($words) > $maxWordsPerSource) {
                $words = array_slice($words, 0, $maxWordsPerSource);
            }

            $remainingWords = $maxTotalWords - $totalWords;
            if ($remainingWords <= 0) {
                return false;
            }

            if (count($words) > $remainingWords) {
                $words = array_slice($words, 0, $remainingWords);
            }

            $totalWords += count($words);
            $combinedParts[] = implode(' ', $words);

            return true;
        };

        // Process background sources
        foreach ($contentPiece->backgroundSources as $source) {
            if ($source->isPost() && $source->post) {
                // Fetch article content from URL
                try {
                    $articleContent = $extractor->extractArticleAsMarkdown($source->post->uri);
                    if (! $addContent($articleContent)) {
                        break;
                    }
                } catch (\Throwable $e) {
                    Log::warning("Failed to extract article for image generation: {$e->getMessage()}", [
                        'uri' => $source->post->uri,
                        'content_piece_id' => $contentPiece->id,
                    ]);
                    // Fall back to summary if available
                    if ($source->post->summary && ! $addContent($source->post->summary)) {
                        break;
                    }
                }
            } elseif ($source->isManual() && $source->content) {
                if (! $addContent($source->content)) {
                    break;
                }
            }
        }

        // Also include directly attached posts
        foreach ($contentPiece->posts as $post) {
            if ($post->uri) {
                try {
                    $articleContent = $extractor->extractArticleAsMarkdown($post->uri);
                    if (! $addContent($articleContent)) {
                        break;
                    }
                } catch (\Throwable $e) {
                    Log::warning("Failed to extract article for image generation: {$e->getMessage()}", [
                        'uri' => $post->uri,
                        'content_piece_id' => $contentPiece->id,
                    ]);
                    if ($post->summary && ! $addContent($post->summary)) {
                        break;
                    }
                }
            } elseif ($post->summary) {
                if (! $addContent($post->summary)) {
                    break;
                }
            }
        }

        return implode("\n\n", $combinedParts);
    }
}
