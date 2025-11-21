<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MediaBulkDeleted implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $mediaIds,
        public int $teamId,
        public User $user
    ) {}
}
