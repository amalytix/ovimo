<?php

namespace App\Http\Controllers;

use App\Http\Requests\BackgroundSource\StoreBackgroundSourceRequest;
use App\Http\Requests\BackgroundSource\UpdateBackgroundSourceRequest;
use App\Models\BackgroundSource;
use App\Models\ContentPiece;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BackgroundSourceController extends Controller
{
    public function store(StoreBackgroundSourceRequest $request, ContentPiece $contentPiece): RedirectResponse
    {
        $this->authorize('create', [BackgroundSource::class, $contentPiece]);

        $maxSortOrder = BackgroundSource::query()
            ->where('content_piece_id', $contentPiece->id)
            ->max('sort_order') ?? -1;

        BackgroundSource::create([
            'content_piece_id' => $contentPiece->id,
            'type' => $request->validated('type'),
            'post_id' => $request->validated('post_id'),
            'title' => $request->validated('title'),
            'content' => $request->validated('content'),
            'url' => $request->validated('url'),
            'sort_order' => $maxSortOrder + 1,
        ]);

        return redirect()->back()->with('success', 'Source added successfully.');
    }

    public function update(
        UpdateBackgroundSourceRequest $request,
        ContentPiece $contentPiece,
        BackgroundSource $source
    ): RedirectResponse {
        $this->authorize('update', $source);

        $source->update([
            'title' => $request->validated('title'),
            'content' => $request->validated('content'),
            'url' => $request->validated('url'),
        ]);

        return redirect()->back()->with('success', 'Source updated successfully.');
    }

    public function destroy(ContentPiece $contentPiece, BackgroundSource $source): RedirectResponse
    {
        $this->authorize('delete', $source);

        $source->delete();

        return redirect()->back()->with('success', 'Source removed successfully.');
    }

    public function reorder(Request $request, ContentPiece $contentPiece): JsonResponse
    {
        $this->authorize('reorder', [BackgroundSource::class, $contentPiece]);

        $validated = $request->validate([
            'source_ids' => ['required', 'array'],
            'source_ids.*' => ['required', 'integer', 'exists:background_sources,id'],
        ]);

        foreach ($validated['source_ids'] as $index => $sourceId) {
            BackgroundSource::query()
                ->where('id', $sourceId)
                ->where('content_piece_id', $contentPiece->id)
                ->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }
}
