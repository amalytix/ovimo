<?php

namespace App\Http\Controllers;

use App\Models\ContentPiece;
use App\Models\Post;
use App\Models\Source;
use App\Models\TokenUsageLog;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $teamId = auth()->user()->current_team_id;

        // Source statistics
        $totalSources = Source::where('team_id', $teamId)->count();
        $activeSources = Source::where('team_id', $teamId)->where('is_active', true)->count();

        // Post statistics
        $totalPosts = Post::whereHas('source', fn ($q) => $q->where('team_id', $teamId))->count();
        $postsToday = Post::whereHas('source', fn ($q) => $q->where('team_id', $teamId))
            ->whereDate('found_at', today())
            ->count();
        $postsThisWeek = Post::whereHas('source', fn ($q) => $q->where('team_id', $teamId))
            ->where('found_at', '>=', now()->subDays(7))
            ->count();

        // Post status breakdown
        $createContentPosts = Post::whereHas('source', fn ($q) => $q->where('team_id', $teamId))
            ->where('status', 'CREATE_CONTENT')
            ->count();

        // Average relevancy score
        $avgRelevancy = Post::whereHas('source', fn ($q) => $q->where('team_id', $teamId))
            ->whereNotNull('relevancy_score')
            ->avg('relevancy_score');

        // Token usage statistics
        $tokensToday = TokenUsageLog::where('team_id', $teamId)
            ->whereDate('created_at', today())
            ->sum('total_tokens');
        $tokensLast7Days = TokenUsageLog::where('team_id', $teamId)
            ->where('created_at', '>=', now()->subDays(7))
            ->sum('total_tokens');
        $tokensLast30Days = TokenUsageLog::where('team_id', $teamId)
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('total_tokens');

        $totalContentPieces = ContentPiece::where('team_id', $teamId)->count();
        $contentPiecesThisMonth = ContentPiece::where('team_id', $teamId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        /** @var Collection<int, array{id:int,internal_name:string,channel:string,published_at:?\DateTimeInterface,status:string,published_platforms:?array}> */
        $contentPiecesToday = ContentPiece::query()
            ->where('team_id', $teamId)
            ->whereDate('published_at', today())
            ->where(function ($query) {
                $query->whereNull('published_platforms')
                    ->orWhereRaw("json_extract(published_platforms, '$.linkedin') IS NULL");
            })
            ->orderBy('published_at')
            ->orderBy('created_at')
            ->get(['id', 'internal_name', 'channel', 'published_at', 'status', 'published_platforms']);

        return Inertia::render('Dashboard', [
            'stats' => [
                'sources' => [
                    'total' => $totalSources,
                    'active' => $activeSources,
                ],
                'posts' => [
                    'total' => $totalPosts,
                    'today' => $postsToday,
                    'this_week' => $postsThisWeek,
                    'create_content' => $createContentPosts,
                    'avg_relevancy' => $avgRelevancy ? round($avgRelevancy, 1) : null,
                ],
                'tokens' => [
                    'today' => $tokensToday,
                    'last_7_days' => $tokensLast7Days,
                    'last_30_days' => $tokensLast30Days,
                ],
                'content_pieces' => [
                    'total' => $totalContentPieces,
                    'this_month' => $contentPiecesThisMonth,
                ],
            ],
            'content_pieces_today' => $contentPiecesToday,
        ]);
    }
}
