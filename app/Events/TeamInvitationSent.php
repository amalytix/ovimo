<?php

namespace App\Events;

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeamInvitationSent implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TeamInvitation $invitation,
        public User $invitedBy
    ) {}
}
