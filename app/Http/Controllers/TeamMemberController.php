<?php

namespace App\Http\Controllers;

use App\Events\TeamMemberLeft;
use App\Events\TeamMemberRemoved;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamMemberController extends Controller
{
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();
        $team = $currentUser->currentTeam;

        if (! $team) {
            abort(404, 'No current team selected.');
        }

        if ($team->owner_id !== $currentUser->id) {
            abort(403, 'Only the team owner can remove members.');
        }

        if ($user->id === $currentUser->id) {
            return back()->withErrors(['user' => 'You cannot remove yourself. Use the leave option instead.']);
        }

        if ($user->id === $team->owner_id) {
            return back()->withErrors(['user' => 'The team owner cannot be removed.']);
        }

        if (! $team->users()->where('user_id', $user->id)->exists()) {
            return back()->withErrors(['user' => 'This user is not a member of the team.']);
        }

        $removedUserName = $user->name;
        $removedUserEmail = $user->email;
        $removedUserId = $user->id;

        $team->users()->detach($user->id);

        if ($user->current_team_id === $team->id) {
            $nextTeam = $user->teams()->first();
            $user->update(['current_team_id' => $nextTeam?->id]);
        }

        TeamMemberRemoved::dispatch($team, $currentUser, $removedUserId, $removedUserName, $removedUserEmail);

        return back()->with('success', "{$removedUserName} has been removed from the team.");
    }

    public function leave(Request $request): RedirectResponse
    {
        $user = $request->user();
        $team = $user->currentTeam;

        if (! $team) {
            abort(404, 'No current team selected.');
        }

        if ($team->users()->count() === 1) {
            return back()->withErrors(['team' => 'You cannot leave the team because you are the only member. Delete the team instead.']);
        }

        if ($team->owner_id === $user->id) {
            return back()->withErrors(['team' => 'As the team owner, you cannot leave. Transfer ownership first or delete the team.']);
        }

        $teamName = $team->name;

        $team->users()->detach($user->id);

        TeamMemberLeft::dispatch($team, $user);

        $nextTeam = $user->teams()->first();
        $user->update(['current_team_id' => $nextTeam?->id]);

        return redirect()->route('dashboard')
            ->with('success', "You have left {$teamName}.");
    }
}
