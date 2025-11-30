<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Source;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SystemHealthController extends Controller
{
    public function index(): Response
    {
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs24h = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDay())
            ->count();
        $totalFailedJobs = DB::table('failed_jobs')->count();

        $failingSources = Source::where('consecutive_failures', '>', 0)->count();
        $totalSources = Source::count();
        $activeSources = Source::where('is_active', true)->count();

        $errors24h = ActivityLog::where('level', 'error')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $jobsByQueue = DB::table('jobs')
            ->select('queue', DB::raw('count(*) as count'))
            ->groupBy('queue')
            ->get();

        return Inertia::render('Admin/System/Index', [
            'overview' => [
                'pending_jobs' => $pendingJobs,
                'failed_jobs_24h' => $failedJobs24h,
                'total_failed_jobs' => $totalFailedJobs,
                'failing_sources' => $failingSources,
                'total_sources' => $totalSources,
                'active_sources' => $activeSources,
                'errors_24h' => $errors24h,
            ],
            'jobsByQueue' => $jobsByQueue,
        ]);
    }

    public function jobs(Request $request): Response
    {
        $pendingQuery = DB::table('jobs')
            ->select('id', 'queue', 'payload', 'attempts', 'created_at', 'available_at', 'reserved_at')
            ->orderBy('created_at', 'desc');

        $failedQuery = DB::table('failed_jobs')
            ->select('id', 'uuid', 'queue', 'payload', 'exception', 'failed_at')
            ->orderBy('failed_at', 'desc');

        if ($queue = $request->input('queue')) {
            $pendingQuery->where('queue', $queue);
            $failedQuery->where('queue', $queue);
        }

        $pendingJobs = $pendingQuery->paginate(20, ['*'], 'pending_page')
            ->through(function ($job) {
                $payload = json_decode($job->payload, true);

                return [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'job_name' => $payload['displayName'] ?? 'Unknown',
                    'attempts' => $job->attempts,
                    'created_at' => now()->setTimestamp($job->created_at)->diffForHumans(),
                    'available_at' => now()->setTimestamp($job->available_at)->diffForHumans(),
                    'is_reserved' => $job->reserved_at !== null,
                ];
            });

        $failedJobs = $failedQuery->paginate(20, ['*'], 'failed_page')
            ->through(function ($job) {
                $payload = json_decode($job->payload, true);
                $exceptionLines = explode("\n", $job->exception);

                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'queue' => $job->queue,
                    'job_name' => $payload['displayName'] ?? 'Unknown',
                    'exception_message' => $exceptionLines[0] ?? 'Unknown error',
                    'failed_at' => $job->failed_at,
                ];
            });

        $queues = DB::table('jobs')
            ->select('queue')
            ->distinct()
            ->pluck('queue')
            ->merge(
                DB::table('failed_jobs')
                    ->select('queue')
                    ->distinct()
                    ->pluck('queue')
            )
            ->unique()
            ->values();

        return Inertia::render('Admin/System/Jobs', [
            'pendingJobs' => $pendingJobs,
            'failedJobs' => $failedJobs,
            'queues' => $queues,
            'filters' => [
                'queue' => $request->input('queue', ''),
            ],
        ]);
    }

    public function sources(Request $request): Response
    {
        $query = Source::query()
            ->with('team:id,name')
            ->select('sources.*');

        // Filter by status
        $status = $request->input('status', 'failing');
        if ($status === 'failing') {
            $query->where('consecutive_failures', '>', 0);
        } elseif ($status === 'healthy') {
            $query->where('consecutive_failures', 0)->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        // Search
        if ($search = $request->input('search')) {
            $query->where('internal_name', 'like', "%{$search}%");
        }

        $sources = $query->orderByDesc('consecutive_failures')
            ->orderByDesc('failed_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Source $source) => [
                'id' => $source->id,
                'internal_name' => $source->internal_name,
                'type' => $source->type,
                'url' => $source->url,
                'team_name' => $source->team?->name ?? 'Unknown',
                'team_id' => $source->team_id,
                'is_active' => $source->is_active,
                'consecutive_failures' => $source->consecutive_failures,
                'last_run_status' => $source->last_run_status,
                'last_run_error' => $source->last_run_error,
                'failed_at' => $source->failed_at?->diffForHumans(),
                'last_checked_at' => $source->last_checked_at?->diffForHumans(),
            ]);

        $stats = [
            'total' => Source::count(),
            'active' => Source::where('is_active', true)->count(),
            'failing' => Source::where('consecutive_failures', '>', 0)->count(),
            'inactive' => Source::where('is_active', false)->count(),
        ];

        return Inertia::render('Admin/System/Sources', [
            'sources' => $sources,
            'stats' => $stats,
            'filters' => [
                'status' => $status,
                'search' => $request->input('search', ''),
            ],
        ]);
    }

    public function errors(Request $request): Response
    {
        $query = ActivityLog::query()
            ->with(['user:id,name,email', 'team:id,name'])
            ->where('level', 'error');

        // Time filter
        $period = $request->input('period', '24h');
        match ($period) {
            '1h' => $query->where('created_at', '>=', now()->subHour()),
            '24h' => $query->where('created_at', '>=', now()->subDay()),
            '7d' => $query->where('created_at', '>=', now()->subDays(7)),
            '30d' => $query->where('created_at', '>=', now()->subDays(30)),
            default => $query->where('created_at', '>=', now()->subDay()),
        };

        // Search
        if ($search = $request->input('search')) {
            $query->where('description', 'like', "%{$search}%");
        }

        $errors = $query->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => [
                'id' => $log->id,
                'event_type' => $log->event_type,
                'description' => $log->description,
                'user_name' => $log->user?->name,
                'user_email' => $log->user?->email,
                'team_name' => $log->team?->name,
                'source_id' => $log->source_id,
                'post_id' => $log->post_id,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at->format('M j, Y H:i:s'),
                'metadata' => $log->metadata,
            ]);

        $errorCounts = [
            '1h' => ActivityLog::where('level', 'error')->where('created_at', '>=', now()->subHour())->count(),
            '24h' => ActivityLog::where('level', 'error')->where('created_at', '>=', now()->subDay())->count(),
            '7d' => ActivityLog::where('level', 'error')->where('created_at', '>=', now()->subDays(7))->count(),
        ];

        return Inertia::render('Admin/System/Errors', [
            'errors' => $errors,
            'errorCounts' => $errorCounts,
            'filters' => [
                'period' => $period,
                'search' => $request->input('search', ''),
            ],
        ]);
    }

    public function retryJob(int $id): \Illuminate\Http\RedirectResponse
    {
        $failedJob = DB::table('failed_jobs')->where('id', $id)->first();

        if (! $failedJob) {
            return back()->with('error', 'Failed job not found.');
        }

        // Re-queue the job
        DB::table('jobs')->insert([
            'queue' => $failedJob->queue,
            'payload' => $failedJob->payload,
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        // Delete from failed jobs
        DB::table('failed_jobs')->where('id', $id)->delete();

        return back()->with('success', 'Job has been queued for retry.');
    }

    public function deleteFailedJob(int $id): \Illuminate\Http\RedirectResponse
    {
        DB::table('failed_jobs')->where('id', $id)->delete();

        return back()->with('success', 'Failed job deleted.');
    }

    public function flushFailedJobs(): \Illuminate\Http\RedirectResponse
    {
        DB::table('failed_jobs')->truncate();

        return back()->with('success', 'All failed jobs have been deleted.');
    }
}
