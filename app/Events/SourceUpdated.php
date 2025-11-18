<?php

namespace App\Events;

use App\Models\Source;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SourceUpdated implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Source $source,
        public User $user
    ) {}
}
