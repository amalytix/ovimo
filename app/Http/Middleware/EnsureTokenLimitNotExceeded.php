<?php

namespace App\Http\Middleware;

use App\Models\TokenUsageLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenLimitNotExceeded
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $team = $user->currentTeam;

        if (! $team) {
            return $next($request);
        }

        $monthlyLimit = $team->monthly_token_limit;

        // If no limit is set (null or 0), allow the request
        if (! $monthlyLimit) {
            return $next($request);
        }

        // Calculate tokens used this month
        $currentMonthUsage = TokenUsageLog::where('team_id', $team->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_tokens');

        if ($currentMonthUsage >= $monthlyLimit) {
            $remaining = max(0, $monthlyLimit - $currentMonthUsage);
            $percentUsed = round(($currentMonthUsage / $monthlyLimit) * 100, 1);

            abort(429, "Monthly token limit exceeded. You have used {$currentMonthUsage} of {$monthlyLimit} tokens ({$percentUsed}%). Limit resets next month.");
        }

        return $next($request);
    }
}
