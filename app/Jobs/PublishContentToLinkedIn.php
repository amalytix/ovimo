<?php

namespace App\Jobs;

use App\Models\ContentPiece;
use App\Models\SocialIntegration;
use App\Services\LinkedIn\LinkedInPublishingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishContentToLinkedIn implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 300;

    public int $timeout = 600;

    public function __construct(
        public ContentPiece $contentPiece,
        public SocialIntegration $integration
    ) {}

    /**
     * Execute the job.
     */
    public function handle(LinkedInPublishingService $service): void
    {
        $contentPiece = $this->contentPiece->fresh(['media']);
        $integration = $this->integration->fresh();

        if (! $contentPiece || ! $integration || $integration->team_id !== $contentPiece->team_id) {
            return;
        }

        $result = $service->publishPost($integration, $contentPiece);

        $publishedPlatforms = $contentPiece->published_platforms ?? [];
        $publishedPlatforms['linkedin'] = $result;

        $contentPiece->update([
            'published_platforms' => $publishedPlatforms,
            'published_at' => $contentPiece->published_at ?? now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('LinkedIn publishing job failed', [
            'content_piece_id' => $this->contentPiece->id,
            'integration_id' => $this->integration->id,
            'error' => $exception->getMessage(),
        ]);

        $this->contentPiece->update([
            'published_at' => now()->addMinutes(5),
        ]);
    }
}
