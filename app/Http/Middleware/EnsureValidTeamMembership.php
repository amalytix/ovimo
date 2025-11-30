<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidTeamMembership
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

        $currentTeamId = $user->current_team_id;

        // If no team is set, let other middleware/routes handle it
        if (! $currentTeamId) {
            return $next($request);
        }

        // Check if user belongs to the current team
        $belongsToTeam = $user->teams()
            ->where('teams.id', $currentTeamId)
            ->exists();

        // Also check if user owns the team
        $ownsTeam = $user->ownedTeams()
            ->where('id', $currentTeamId)
            ->exists();

        if (! $belongsToTeam && ! $ownsTeam) {
            // User doesn't belong to this team - reset to their first available team
            $firstTeam = $user->teams()->where('is_active', true)->first()
                ?? $user->ownedTeams()->where('is_active', true)->first();

            if ($firstTeam) {
                $user->update(['current_team_id' => $firstTeam->id]);

                return redirect()->route('dashboard')
                    ->with('warning', 'Your team selection was invalid. You have been switched to your default team.');
            }

            // User has no teams - critical error
            abort(403, 'You do not have access to any team. Please contact support.');
        }

        // Check if the current team is active
        $currentTeam = $user->currentTeam;
        if ($currentTeam && ! $currentTeam->is_active) {
            // Try to switch to an active team
            $activeTeam = $user->teams()->where('is_active', true)->first()
                ?? $user->ownedTeams()->where('is_active', true)->first();

            if ($activeTeam) {
                $user->update(['current_team_id' => $activeTeam->id]);

                return redirect()->route('dashboard')
                    ->with('warning', 'Your team has been deactivated. You have been switched to another team.');
            }

            // All teams are inactive
            return redirect()->route('dashboard')
                ->with('error', 'Your team has been deactivated. Please contact support for assistance.');
        }

        return $next($request);
    }
}
