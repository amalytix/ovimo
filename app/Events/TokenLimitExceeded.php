<?php

namespace App\Events;

use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TokenLimitExceeded implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Team $team,
        public ?User $user,
        public int $currentUsage,
        public int $limit,
        public string $operation
    ) {}
}
