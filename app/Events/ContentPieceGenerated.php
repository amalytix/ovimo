<?php

namespace App\Events;

use App\Models\ContentPiece;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContentPieceGenerated implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ContentPiece $contentPiece
    ) {}
}
