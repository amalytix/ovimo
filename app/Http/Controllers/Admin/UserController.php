<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::query()
            ->select('users.*')
            ->selectSub(
                fn ($q) => $q->from('team_user')->whereColumn('team_user.user_id', 'users.id')->selectRaw('count(*)'),
                'teams_count'
            )
            ->selectSub(
                fn ($q) => $q->from('sources')
                    ->join('team_user', 'sources.team_id', '=', 'team_user.team_id')
                    ->whereColumn('team_user.user_id', 'users.id')
                    ->selectRaw('count(*)'),
                'sources_count'
            )
            ->selectSub(
                fn ($q) => $q->from('posts')
                    ->join('sources', 'posts.source_id', '=', 'sources.id')
                    ->join('team_user', 'sources.team_id', '=', 'team_user.team_id')
                    ->whereColumn('team_user.user_id', 'users.id')
                    ->selectRaw('count(*)'),
                'posts_count'
            )
            ->selectSub(
                fn ($q) => $q->from('token_usage_logs')
                    ->whereColumn('token_usage_logs.user_id', 'users.id')
                    ->where('token_usage_logs.created_at', '>=', now()->subDays(7))
                    ->selectRaw('COALESCE(sum(total_tokens), 0)'),
                'tokens_7d'
            )
            ->selectSub(
                fn ($q) => $q->from('sessions')
                    ->whereColumn('sessions.user_id', 'users.id')
                    ->selectRaw('MAX(last_activity)'),
                'last_login_at'
            );

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filters
        if ($request->input('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('status') === 'inactive') {
            $query->where('is_active', false);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'last_login_at');
        $sortDir = $request->input('sort_dir', 'desc');

        match ($sortBy) {
            'email' => $query->orderBy('email', $sortDir),
            'name' => $query->orderBy('name', $sortDir),
            'sources_count' => $query->orderBy('sources_count', $sortDir),
            'posts_count' => $query->orderBy('posts_count', $sortDir),
            'tokens_7d' => $query->orderBy('tokens_7d', $sortDir),
            default => $query->orderByRaw("last_login_at IS NULL, last_login_at {$sortDir}"),
        };

        return Inertia::render('Admin/Users/Index', [
            'users' => $query->paginate(20)->withQueryString()->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'is_admin' => $user->is_admin,
                'teams_count' => (int) $user->teams_count,
                'sources_count' => (int) $user->sources_count,
                'posts_count' => (int) $user->posts_count,
                'tokens_7d' => (int) $user->tokens_7d,
                'last_login_at' => $user->last_login_at
                    ? now()->setTimestamp($user->last_login_at)->diffForHumans()
                    : 'Never',
            ]),
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }
}
