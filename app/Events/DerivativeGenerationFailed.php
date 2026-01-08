<?php

namespace App\Events;

use App\Models\ContentDerivative;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DerivativeGenerationFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ContentDerivative $derivative,
        public Throwable $exception,
        public ?User $user = null
    ) {}
}
