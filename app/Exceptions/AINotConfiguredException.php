<?php

namespace App\Exceptions;

use Exception;

class AINotConfiguredException extends Exception
{
    public function __construct(
        string $message,
        public string $provider,
        public string $settingsUrl = '/team-settings?tab=ai'
    ) {
        parent::__construct($message);
    }
}
