<?php

namespace App\Events;

use App\Models\Webhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebhookDeliveryFailed implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Webhook $webhook,
        public string $errorMessage,
        public array $metadata = []
    ) {}
}
