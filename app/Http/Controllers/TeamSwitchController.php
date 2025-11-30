<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamSwitchController extends Controller
{
    public function __invoke(Request $request, Team $team): RedirectResponse
    {
        $user = $request->user();

        if (! $user->teams()->where('teams.id', $team->id)->exists()) {
            abort(403, 'You are not a member of this team.');
        }

        $user->update(['current_team_id' => $team->id]);

        return back()->with('success', "Switched to {$team->name}.");
    }
}
