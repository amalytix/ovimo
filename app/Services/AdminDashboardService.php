<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Source;
use App\Models\Team;
use App\Models\TokenUsageLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    public function getPlatformOverview(): array
    {
        return [
            'total_users' => User::count(),
            'total_teams' => Team::count(),
            'new_signups_7d' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'logins_7d' => DB::table('sessions')
                ->where('last_activity', '>=', now()->subDays(7)->timestamp)
                ->distinct('user_id')
                ->count('user_id'),
        ];
    }

    public function getSystemHealth(): array
    {
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs24h = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDay())
            ->count();
        $totalSources = Source::where('is_active', true)->count();
        $failingSources = Source::where('consecutive_failures', '>', 0)->count();
        $errors24h = ActivityLog::where('level', 'error')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return [
            'pending_jobs' => $pendingJobs,
            'pending_jobs_status' => $this->getHealthStatus($pendingJobs, 100, 500),
            'failed_jobs_24h' => $failedJobs24h,
            'failed_jobs_status' => $this->getHealthStatus($failedJobs24h, 10, 50),
            'failing_sources' => $failingSources,
            'failing_sources_percentage' => $totalSources > 0 ? round(($failingSources / $totalSources) * 100, 1) : 0,
            'failing_sources_status' => $this->getHealthStatus(
                $totalSources > 0 ? ($failingSources / $totalSources) * 100 : 0,
                5,
                10
            ),
            'errors_24h' => $errors24h,
            'errors_status' => $this->getHealthStatus($errors24h, 50, 200),
        ];
    }

    public function getUsageStats(): array
    {
        $tokensToday = TokenUsageLog::where('created_at', '>=', now()->startOfDay())
            ->sum('total_tokens');
        $tokens7d = TokenUsageLog::where('created_at', '>=', now()->subDays(7))
            ->sum('total_tokens');
        $sourceChecksToday = ActivityLog::where('event_type', 'source.checked')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
        $sourceChecks7d = ActivityLog::where('event_type', 'source.checked')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            'tokens_today' => $tokensToday,
            'tokens_7d' => $tokens7d,
            'source_checks_today' => $sourceChecksToday,
            'source_checks_7d' => $sourceChecks7d,
        ];
    }

    public function getTopTeamsByTokenUsage(int $limit = 5): array
    {
        $totalTokens7d = TokenUsageLog::where('created_at', '>=', now()->subDays(7))
            ->sum('total_tokens');

        return Team::query()
            ->select('teams.id', 'teams.name')
            ->selectRaw('COALESCE(SUM(token_usage_logs.total_tokens), 0) as tokens_used')
            ->leftJoin('token_usage_logs', function ($join) {
                $join->on('teams.id', '=', 'token_usage_logs.team_id')
                    ->where('token_usage_logs.created_at', '>=', now()->subDays(7));
            })
            ->groupBy('teams.id', 'teams.name')
            ->orderByDesc('tokens_used')
            ->limit($limit)
            ->get()
            ->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'tokens_used' => (int) $team->tokens_used,
                'percentage' => $totalTokens7d > 0 ? round(($team->tokens_used / $totalTokens7d) * 100, 1) : 0,
            ])
            ->toArray();
    }

    public function getTeamsApproachingLimit(): array
    {
        return Team::query()
            ->select('teams.id', 'teams.name', 'teams.monthly_token_limit')
            ->selectRaw('COALESCE(SUM(token_usage_logs.total_tokens), 0) as tokens_used')
            ->leftJoin('token_usage_logs', function ($join) {
                $join->on('teams.id', '=', 'token_usage_logs.team_id')
                    ->where('token_usage_logs.created_at', '>=', now()->startOfMonth());
            })
            ->whereNotNull('teams.monthly_token_limit')
            ->groupBy('teams.id', 'teams.name', 'teams.monthly_token_limit')
            ->havingRaw('COALESCE(SUM(token_usage_logs.total_tokens), 0) >= teams.monthly_token_limit * 0.8')
            ->orderByDesc('tokens_used')
            ->get()
            ->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'percentage' => $team->monthly_token_limit > 0
                    ? round(($team->tokens_used / $team->monthly_token_limit) * 100, 0)
                    : 0,
            ])
            ->toArray();
    }

    public function getDailyTokenUsage(int $days = 28): array
    {
        return TokenUsageLog::query()
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('SUM(total_tokens) as tokens')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'tokens' => (int) $row->tokens,
            ])
            ->toArray();
    }

    public function getRecentRegistrations(int $limit = 5): array
    {
        return User::query()
            ->select('id', 'name', 'email', 'created_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'email' => $user->email,
                'created_at' => $user->created_at->diffForHumans(),
            ])
            ->toArray();
    }

    private function getHealthStatus(float $value, float $warningThreshold, float $criticalThreshold): string
    {
        if ($value >= $criticalThreshold) {
            return 'critical';
        }
        if ($value >= $warningThreshold) {
            return 'warning';
        }

        return 'healthy';
    }
}
