<?php

namespace App\Events;

use App\Models\Post;
use App\Models\Source;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostFound implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Post $post,
        public Source $source
    ) {}
}
