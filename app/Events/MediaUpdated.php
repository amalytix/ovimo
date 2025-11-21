<?php

namespace App\Events;

use App\Models\Media;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MediaUpdated implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Media $media,
        public User $user,
        public array $changes = []
    ) {}
}
