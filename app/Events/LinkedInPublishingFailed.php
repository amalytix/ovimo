<?php

namespace App\Events;

use App\Models\ContentPiece;
use App\Models\SocialIntegration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class LinkedInPublishingFailed implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SocialIntegration $integration,
        public ContentPiece $contentPiece,
        public Throwable $exception
    ) {}
}
