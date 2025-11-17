<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTeamSettingsRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $team = Team::find(auth()->user()->current_team_id);

        return Inertia::render('settings/Index', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'notifications_enabled' => $team->notifications_enabled,
                'webhook_url' => $team->webhook_url,
                'post_auto_hide_days' => $team->post_auto_hide_days,
                'monthly_token_limit' => $team->monthly_token_limit,
                'relevancy_prompt' => $team->relevancy_prompt,
                'positive_keywords' => $team->positive_keywords,
                'negative_keywords' => $team->negative_keywords,
            ],
        ]);
    }

    public function update(UpdateTeamSettingsRequest $request): RedirectResponse
    {
        $team = Team::find(auth()->user()->current_team_id);

        // Only team owner can update settings
        if ($team->owner_id !== auth()->id()) {
            abort(403, 'Only the team owner can update settings.');
        }

        $team->update($request->validated());

        return back()->with('success', 'Team settings updated successfully.');
    }
}
