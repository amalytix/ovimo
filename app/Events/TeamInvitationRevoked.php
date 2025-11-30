<?php

namespace App\Events;

use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeamInvitationRevoked implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Team $team,
        public User $revokedBy,
        public string $email
    ) {}
}
