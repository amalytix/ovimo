<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DerivativeActivitiesController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ActivityLog::class);

        $team = auth()->user()->currentTeam;

        $query = $team->activityLogs()
            ->with(['user', 'contentDerivative.channel', 'contentDerivative.contentPiece'])
            ->whereNotNull('content_derivative_id');

        // Filter by event type if provided
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        // Filter by date range (default to last 7 days)
        $from = $request->from ? now()->parse($request->from) : now()->subDays(7);
        $to = $request->to ? now()->parse($request->to) : now();

        $query->whereBetween('created_at', [$from, $to]);

        return Inertia::render('DerivativeActivities/Index', [
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
                    'content_derivative' => $log->contentDerivative ? [
                        'id' => $log->contentDerivative->id,
                        'title' => $log->contentDerivative->title,
                        'channel' => $log->contentDerivative->channel ? [
                            'id' => $log->contentDerivative->channel->id,
                            'name' => $log->contentDerivative->channel->name,
                        ] : null,
                        'content_piece' => $log->contentDerivative->contentPiece ? [
                            'id' => $log->contentDerivative->contentPiece->id,
                            'internal_name' => $log->contentDerivative->contentPiece->internal_name,
                        ] : null,
                    ] : null,
                    'is_comment' => $log->event_type === 'derivative.comment',
                    'metadata' => $log->metadata,
                ]),
            'filters' => [
                'event_type' => $request->event_type,
                'from' => $request->from,
                'to' => $request->to,
            ],
            'eventTypes' => array_filter(
                ActivityLog::EVENT_TYPES,
                fn ($key) => str_starts_with($key, 'derivative.'),
                ARRAY_FILTER_USE_KEY
            ),
        ]);
    }
}
