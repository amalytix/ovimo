<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ContentDerivative;
use App\Models\ContentPiece;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DerivativeActivityController extends Controller
{
    public function index(ContentPiece $contentPiece, ContentDerivative $derivative): JsonResponse
    {
        $this->authorize('view', $derivative);

        $activities = $derivative->activities()
            ->with('user')
            ->take(50)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'event_type' => $log->event_type,
                'event_type_label' => ActivityLog::EVENT_TYPES[$log->event_type] ?? $log->event_type,
                'level' => $log->level,
                'description' => $log->description,
                'created_at' => $log->created_at->toISOString(),
                'created_at_human' => $log->created_at->diffForHumans(),
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                ] : null,
                'is_comment' => $log->event_type === 'derivative.comment',
            ]);

        return response()->json(['activities' => $activities]);
    }

    public function store(Request $request, ContentPiece $contentPiece, ContentDerivative $derivative): JsonResponse
    {
        $this->authorize('update', $derivative);

        $validated = $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        $activity = ActivityLog::create([
            'team_id' => $contentPiece->team_id,
            'user_id' => auth()->id(),
            'content_derivative_id' => $derivative->id,
            'event_type' => 'derivative.comment',
            'level' => 'info',
            'description' => $validated['comment'],
        ]);

        return response()->json([
            'activity' => [
                'id' => $activity->id,
                'event_type' => 'derivative.comment',
                'event_type_label' => 'Comment',
                'level' => 'info',
                'description' => $activity->description,
                'created_at' => $activity->created_at->toISOString(),
                'created_at_human' => $activity->created_at->diffForHumans(),
                'user' => [
                    'id' => auth()->id(),
                    'name' => auth()->user()->name,
                ],
                'is_comment' => true,
            ],
        ], 201);
    }
}
