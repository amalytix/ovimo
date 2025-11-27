<?php

namespace App\Jobs;

use App\Exceptions\TokenLimitExceededException;
use App\Models\ImageGeneration;
use App\Models\Media;
use App\Models\MediaTag;
use App\Services\GeminiService;
use App\Services\TokenLimitService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateAIImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    public int $timeout = 300; // 5 minutes

    public function __construct(
        public ImageGeneration $imageGeneration
    ) {}

    public function handle(GeminiService $gemini, TokenLimitService $tokenLimitService): void
    {
        Log::info("Starting image generation job for {$this->imageGeneration->id}");

        $this->imageGeneration->update([
            'status' => ImageGeneration::STATUS_GENERATING,
            'error_message' => null,
        ]);

        try {
            $contentPiece = $this->imageGeneration->contentPiece;
            $team = $contentPiece->team;

            $tokenLimitService->assertWithinLimit($team, 0, null, 'image_generation');

            Log::info("Calling Gemini API for image generation {$this->imageGeneration->id}");
            $startTime = microtime(true);

            // Generate image with Gemini
            $result = $gemini->generateImage(
                $this->imageGeneration->generated_text_prompt,
                $this->imageGeneration->aspect_ratio
            );

            $elapsed = round(microtime(true) - $startTime, 2);
            Log::info("Gemini API returned image in {$elapsed}s for generation {$this->imageGeneration->id}");

            // Upload to S3 (use team owner as the uploader for AI-generated images)
            $media = $this->uploadImageToS3(
                $result['image_data'],
                $result['mime_type'],
                $team->id,
                $team->owner_id
            );

            // Tag with "AI generated"
            $this->tagMediaAsAIGenerated($media);

            // Attach to content piece
            $contentPiece->media()->syncWithoutDetaching([$media->id]);

            // Update image generation as completed
            $this->imageGeneration->update([
                'status' => ImageGeneration::STATUS_COMPLETED,
                'media_id' => $media->id,
            ]);

            // Track usage
            $gemini->trackUsage(null, $team, 'image_generation');

            Log::info("Generated AI image for content piece {$contentPiece->id}", [
                'image_generation_id' => $this->imageGeneration->id,
                'media_id' => $media->id,
            ]);
        } catch (\Throwable $e) {
            if ($e instanceof TokenLimitExceededException) {
                $this->imageGeneration->update([
                    'status' => ImageGeneration::STATUS_FAILED,
                    'error_message' => 'Monthly token limit exceeded. Please increase the limit or wait until next month.',
                ]);

                Log::warning("Skipping image generation {$this->imageGeneration->id}: token limit exceeded");

                return;
            }

            Log::error("Failed to generate image {$this->imageGeneration->id}: {$e->getMessage()}");
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->imageGeneration->update([
            'status' => ImageGeneration::STATUS_FAILED,
            'error_message' => $this->categorizeError($exception),
        ]);

        Log::error("Image generation failed permanently for {$this->imageGeneration->id}: {$exception->getMessage()}");
    }

    private function uploadImageToS3(string $base64Data, string $mimeType, int $teamId, int $uploadedBy): Media
    {
        $imageData = base64_decode($base64Data);

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'png',
        };

        $uuid = Str::uuid();
        $storedFilename = "{$uuid}.{$extension}";
        $s3Key = "teams/{$teamId}/images/{$storedFilename}";

        Storage::disk('s3')->put($s3Key, $imageData, [
            'ContentType' => $mimeType,
            'ACL' => 'private',
        ]);

        return Media::create([
            'team_id' => $teamId,
            'uploaded_by' => $uploadedBy,
            'filename' => "ai-generated-{$uuid}.{$extension}",
            'stored_filename' => $storedFilename,
            'file_path' => $s3Key,
            'mime_type' => $mimeType,
            'file_size' => strlen($imageData),
            's3_key' => $s3Key,
            'metadata' => [
                'generated_by' => 'gemini',
                'image_generation_id' => $this->imageGeneration->id,
            ],
        ]);
    }

    private function tagMediaAsAIGenerated(Media $media): void
    {
        $tag = MediaTag::firstOrCreate(
            [
                'team_id' => $media->team_id,
                'slug' => 'ai-generated',
            ],
            [
                'name' => 'AI generated',
            ]
        );

        $media->tags()->syncWithoutDetaching([$tag->id]);
    }

    private function categorizeError(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return 'Gemini request timed out. Please try again.';
        }

        if (str_contains($message, 'overloaded') || str_contains($message, 'UNAVAILABLE') || str_contains($message, '503')) {
            return 'Gemini is temporarily overloaded. Please try again shortly.';
        }

        if (str_contains($message, 'rate limit') || str_contains($message, 'Rate limit')) {
            return 'Gemini rate limit exceeded. Please try again later.';
        }

        if (str_contains($message, 'API key')) {
            return 'Gemini API key is not configured or invalid.';
        }

        return 'Error: '.$message;
    }
}
