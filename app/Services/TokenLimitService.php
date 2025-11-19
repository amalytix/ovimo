<?php

namespace App\Services;

use App\Events\TokenLimitExceeded;
use App\Exceptions\TokenLimitExceededException;
use App\Models\Team;
use App\Models\TokenUsageLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TokenLimitService
{
    public function currentMonthUsage(Team $team): int
    {
        return TokenUsageLog::where('team_id', $team->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_tokens');
    }

    public function assertWithinLimit(Team $team, int $tokensToAdd, ?User $user, string $operation): void
    {
        $limit = $team->monthly_token_limit;

        // Null or zero means unlimited
        if (! $limit) {
            return;
        }

        DB::transaction(function () use ($team, $limit, $tokensToAdd, $user, $operation) {
            $currentUsage = DB::table('token_usage_logs')
                ->where('team_id', $team->id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->lockForUpdate()
                ->sum('total_tokens');

            $newUsage = $currentUsage + $tokensToAdd;

            if ($newUsage >= $limit) {
                TokenLimitExceeded::dispatch($team, $user, $currentUsage, $limit, $operation);

                throw new TokenLimitExceededException($team, $user, $currentUsage, $limit, $operation);
            }
        });
    }
}
