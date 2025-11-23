<?php

namespace App\Events;

use App\Models\SocialIntegration;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LinkedInIntegrationConnected implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SocialIntegration $integration,
        public User $user
    ) {}
}
