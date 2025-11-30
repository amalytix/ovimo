<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTeamRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Team::query()
            ->select('teams.*')
            ->selectSub(
                fn ($q) => $q->from('team_user')->whereColumn('team_user.team_id', 'teams.id')->selectRaw('count(*)'),
                'users_count'
            )
            ->selectSub(
                fn ($q) => $q->from('sources')->whereColumn('sources.team_id', 'teams.id')->selectRaw('count(*)'),
                'sources_count'
            )
            ->selectSub(
                fn ($q) => $q->from('posts')
                    ->join('sources', 'posts.source_id', '=', 'sources.id')
                    ->whereColumn('sources.team_id', 'teams.id')
                    ->selectRaw('count(*)'),
                'posts_count'
            )
            ->selectSub(
                fn ($q) => $q->from('token_usage_logs')
                    ->join('team_user', 'token_usage_logs.user_id', '=', 'team_user.user_id')
                    ->whereColumn('team_user.team_id', 'teams.id')
                    ->where('token_usage_logs.created_at', '>=', now()->subDays(7))
                    ->selectRaw('COALESCE(sum(total_tokens), 0)'),
                'tokens_7d'
            );

        // Search
        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Filters
        if ($request->input('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('status') === 'inactive') {
            $query->where('is_active', false);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'name');
        $sortDir = $request->input('sort_dir', 'asc');

        match ($sortBy) {
            'users_count' => $query->orderBy('users_count', $sortDir),
            'sources_count' => $query->orderBy('sources_count', $sortDir),
            'posts_count' => $query->orderBy('posts_count', $sortDir),
            'tokens_7d' => $query->orderBy('tokens_7d', $sortDir),
            'created_at' => $query->orderBy('created_at', $sortDir),
            default => $query->orderBy('name', $sortDir),
        };

        return Inertia::render('Admin/Teams/Index', [
            'teams' => $query->paginate(20)->withQueryString()->through(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'is_active' => $team->is_active,
                'users_count' => (int) $team->users_count,
                'sources_count' => (int) $team->sources_count,
                'posts_count' => (int) $team->posts_count,
                'tokens_7d' => (int) $team->tokens_7d,
                'created_at' => $team->created_at->format('M j, Y'),
            ]),
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    public function edit(Team $team): Response
    {
        return Inertia::render('Admin/Teams/Edit', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'is_active' => $team->is_active,
                'created_at' => $team->created_at->format('M j, Y'),
            ],
        ]);
    }

    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->validated());

        return redirect()->route('admin.teams.index')
            ->with('success', 'Team updated successfully.');
    }
}
