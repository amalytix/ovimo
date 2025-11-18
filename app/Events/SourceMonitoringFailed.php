<?php

namespace App\Events;

use App\Models\Source;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SourceMonitoringFailed implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Source $source,
        public string $errorMessage
    ) {}
}
