<?php

namespace App\Events;

use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeamMemberRemoved implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Team $team,
        public User $removedBy,
        public int $removedUserId,
        public string $removedUserName,
        public string $removedUserEmail
    ) {}
}
