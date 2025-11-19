<?php

namespace App\Exceptions;

use App\Models\Team;
use App\Models\User;
use Exception;

class TokenLimitExceededException extends Exception
{
    public function __construct(
        public Team $team,
        public ?User $user,
        public int $currentUsage,
        public int $limit,
        public string $operation
    ) {
        parent::__construct('Monthly token limit exceeded.');
    }
}
