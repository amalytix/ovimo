<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContentDerivative\StoreContentDerivativeRequest;
use App\Http\Requests\ContentDerivative\UpdateContentDerivativeRequest;
use App\Jobs\GenerateContentDerivative;
use App\Models\ContentDerivative;
use App\Models\ContentPiece;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ContentDerivativeController extends Controller
{
    public function store(StoreContentDerivativeRequest $request, ContentPiece $contentPiece): RedirectResponse
    {
        $this->authorize('create', [ContentDerivative::class, $contentPiece]);

        ContentDerivative::create([
            'content_piece_id' => $contentPiece->id,
            'channel_id' => $request->validated('channel_id'),
            'prompt_id' => $request->validated('prompt_id'),
            'title' => $request->validated('title'),
            'text' => $request->validated('text'),
            'status' => $request->validated('status', ContentDerivative::STATUS_NOT_STARTED),
            'planned_publish_at' => $request->validated('planned_publish_at'),
        ]);

        return redirect()->back()->with('success', 'Derivative created successfully.');
    }

    public function update(
        UpdateContentDerivativeRequest $request,
        ContentPiece $contentPiece,
        ContentDerivative $derivative
    ): RedirectResponse {
        $this->authorize('update', $derivative);

        $derivative->update([
            'prompt_id' => $request->validated('prompt_id'),
            'title' => $request->validated('title'),
            'text' => $request->validated('text'),
            'status' => $request->validated('status'),
            'planned_publish_at' => $request->validated('planned_publish_at'),
        ]);

        return redirect()->back()->with('success', 'Derivative updated successfully.');
    }

    public function destroy(ContentPiece $contentPiece, ContentDerivative $derivative): RedirectResponse
    {
        $this->authorize('delete', $derivative);

        $derivative->delete();

        return redirect()->back()->with('success', 'Derivative deleted successfully.');
    }

    public function generate(ContentPiece $contentPiece, ContentDerivative $derivative): RedirectResponse
    {
        $this->authorize('generate', $derivative);

        if (! $derivative->canGenerate()) {
            return redirect()->back()->withErrors(['derivative' => 'Cannot generate content for this derivative.']);
        }

        $derivative->update([
            'generation_status' => ContentDerivative::GENERATION_QUEUED,
            'generation_error' => null,
            'generation_error_occurred_at' => null,
        ]);

        GenerateContentDerivative::dispatch($derivative);

        return redirect()->back()->with('polling', [
            'derivative_id' => $derivative->id,
        ]);
    }

    public function status(ContentPiece $contentPiece, ContentDerivative $derivative): JsonResponse
    {
        $this->authorize('view', $derivative);

        return response()->json([
            'generation_status' => $derivative->generation_status,
            'title' => $derivative->title,
            'text' => $derivative->text,
            'error' => $derivative->generation_error,
        ]);
    }
}
