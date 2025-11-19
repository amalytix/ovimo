<?php

namespace App\Http\Middleware;

use App\Exceptions\TokenLimitExceededException;
use App\Services\TokenLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenLimitNotExceeded
{
    public function __construct(private TokenLimitService $tokenLimitService) {}

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

        try {
            $this->tokenLimitService->assertWithinLimit($team, 0, $user, 'http_request');
        } catch (TokenLimitExceededException $e) {
            $remaining = max(0, $e->limit - $e->currentUsage);
            $percentUsed = round(($e->currentUsage / $e->limit) * 100, 1);

            abort(429, "Monthly token limit exceeded. You have used {$e->currentUsage} of {$e->limit} tokens ({$percentUsed}%). Remaining: {$remaining}. Limit resets next month.");
        }

        return $next($request);
    }
}
