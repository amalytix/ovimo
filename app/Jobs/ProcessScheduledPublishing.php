<?php

namespace App\Jobs;

use App\Models\ContentPiece;
use App\Models\SocialIntegration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessScheduledPublishing implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $duePieces = ContentPiece::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('published_platforms')->orWhereJsonDoesntContain('published_platforms->linkedin', null), 'and', true)
            ->with('team')
            ->get();

        foreach ($duePieces as $contentPiece) {
            $publishTo = $contentPiece->publish_to_platforms ?? [];
            $integrationId = $publishTo['linkedin'] ?? null;

            if (! $integrationId) {
                continue;
            }

            $integration = SocialIntegration::query()
                ->active()
                ->where('id', $integrationId)
                ->where('team_id', $contentPiece->team_id)
                ->first();

            if (! $integration) {
                continue;
            }

            $contentPiece->update(['published_at' => $contentPiece->published_at]);

            PublishContentToLinkedIn::dispatch($contentPiece, $integration);
        }
    }
}
