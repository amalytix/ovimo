<?php

namespace App\Events;

use App\Models\Media;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MediaUploaded implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Media $media,
        public User $user
    ) {}
}
