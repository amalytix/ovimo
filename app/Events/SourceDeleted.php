<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SourceDeleted implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $sourceId,
        public int $teamId,
        public string $sourceName,
        public User $user
    ) {}
}
