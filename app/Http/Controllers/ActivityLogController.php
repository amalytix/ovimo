<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ActivityLog::class);

        $team = auth()->user()->currentTeam;

        $query = $team->activityLogs()
            ->with(['user', 'source', 'post']);

        // Filter by event type if provided
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        // Filter by date range (default to last 7 days)
        $from = $request->from ? now()->parse($request->from) : now()->subDays(7);
        $to = $request->to ? now()->parse($request->to) : now();

        $query->whereBetween('created_at', [$from, $to]);

        return Inertia::render('ActivityLogs/Index', [
            'logs' => $query
                ->latest('created_at')
                ->paginate(50)
                ->withQueryString()
                ->through(fn (ActivityLog $log) => [
                    'id' => $log->id,
                    'event_type' => $log->event_type,
                    'event_type_label' => ActivityLog::EVENT_TYPES[$log->event_type] ?? $log->event_type,
                    'level' => $log->level,
                    'description' => $log->description,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'created_at_human' => $log->created_at->diffForHumans(),
                    'user' => $log->user ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                    ] : null,
                    'source' => $log->source ? [
                        'id' => $log->source->id,
                        'internal_name' => $log->source->internal_name,
                    ] : null,
                    'post' => $log->post ? [
                        'id' => $log->post->id,
                        'external_title' => $log->post->external_title,
                    ] : null,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'metadata' => $log->metadata,
                ]),
            'filters' => [
                'event_type' => $request->event_type,
                'from' => $request->from,
                'to' => $request->to,
            ],
            'eventTypes' => ActivityLog::EVENT_TYPES,
        ]);
    }
}
