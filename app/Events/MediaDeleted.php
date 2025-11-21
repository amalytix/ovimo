<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MediaDeleted implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $mediaId,
        public int $teamId,
        public string $filename,
        public User $user
    ) {}
}
