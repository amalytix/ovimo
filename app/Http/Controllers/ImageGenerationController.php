<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImageGeneration\StoreImageGenerationRequest;
use App\Http\Requests\ImageGeneration\UpdateImageGenerationRequest;
use App\Jobs\GenerateAIImage;
use App\Models\ContentPiece;
use App\Models\ImageGeneration;
use App\Models\Prompt;
use App\Services\OpenAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageGenerationController extends Controller
{
    public function store(StoreImageGenerationRequest $request, ContentPiece $contentPiece, OpenAIService $openAI): JsonResponse
    {
        $this->authorize('update', $contentPiece);

        $validated = $request->validated();
        $team = $contentPiece->team;

        // Get the image prompt template
        $prompt = Prompt::where('team_id', $team->id)
            ->where('id', $validated['prompt_id'])
            ->where('type', Prompt::TYPE_IMAGE)
            ->firstOrFail();

        // Get the content to use as context
        $contentText = $contentPiece->edited_text ?? '';

        if (empty(trim($contentText))) {
            return response()->json([
                'message' => 'Content piece has no edited text. Please add content in the Editing tab first.',
            ], 422);
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

    public function generate(Request $request, ContentPiece $contentPiece, ImageGeneration $imageGeneration): JsonResponse
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

        // Optionally detach media from content piece if requested
        if ($request->boolean('detach_media') && $imageGeneration->media_id) {
            $contentPiece->media()->detach($imageGeneration->media_id);
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
}
