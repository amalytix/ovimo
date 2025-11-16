<?php

namespace App\Http\Controllers;

use App\Models\TokenUsageLog;
use Inertia\Inertia;
use Inertia\Response;

class UsageController extends Controller
{
    public function index(): Response
    {
        $teamId = auth()->user()->current_team_id;

        // Total usage stats
        $totalStats = TokenUsageLog::where('team_id', $teamId)
            ->selectRaw('
                SUM(input_tokens) as total_input,
                SUM(output_tokens) as total_output,
                SUM(total_tokens) as total_tokens,
                COUNT(*) as total_requests
            ')
            ->first();

        // Usage by operation
        $byOperation = TokenUsageLog::where('team_id', $teamId)
            ->selectRaw('
                operation,
                SUM(total_tokens) as tokens,
                COUNT(*) as requests
            ')
            ->groupBy('operation')
            ->orderByDesc('tokens')
            ->get();

        // Usage by model
        $byModel = TokenUsageLog::where('team_id', $teamId)
            ->selectRaw('
                model,
                SUM(total_tokens) as tokens,
                COUNT(*) as requests
            ')
            ->groupBy('model')
            ->orderByDesc('tokens')
            ->get();

        // Daily usage for last 30 days
        $dailyUsage = TokenUsageLog::where('team_id', $teamId)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('
                DATE(created_at) as date,
                SUM(total_tokens) as tokens,
                COUNT(*) as requests
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Recent logs
        $recentLogs = TokenUsageLog::where('team_id', $teamId)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->take(20)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'user_name' => $log->user?->name ?? 'System',
                'operation' => $log->operation,
                'model' => $log->model,
                'input_tokens' => $log->input_tokens,
                'output_tokens' => $log->output_tokens,
                'total_tokens' => $log->total_tokens,
                'created_at' => $log->created_at->diffForHumans(),
            ]);

        return Inertia::render('Usage/Index', [
            'totalStats' => [
                'total_input' => (int) ($totalStats->total_input ?? 0),
                'total_output' => (int) ($totalStats->total_output ?? 0),
                'total_tokens' => (int) ($totalStats->total_tokens ?? 0),
                'total_requests' => (int) ($totalStats->total_requests ?? 0),
            ],
            'byOperation' => $byOperation,
            'byModel' => $byModel,
            'dailyUsage' => $dailyUsage,
            'recentLogs' => $recentLogs,
        ]);
    }
}
