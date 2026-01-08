<?php

namespace App\Events;

use App\Models\ContentDerivative;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DerivativeGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ContentDerivative $derivative,
        public ?User $user = null
    ) {}
}
