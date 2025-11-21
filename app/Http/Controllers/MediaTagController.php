<?php

namespace App\Http\Controllers;

use App\Http\Requests\MediaTag\StoreMediaTagRequest;
use App\Http\Requests\MediaTag\UpdateMediaTagRequest;
use App\Models\MediaTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MediaTagController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', MediaTag::class);

        $teamId = $request->user()->current_team_id;

        $tags = MediaTag::where('team_id', $teamId)
            ->withCount('media')
            ->orderBy('name')
            ->get()
            ->map(fn (MediaTag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'media_count' => $tag->media_count,
            ]);

        return Inertia::render('Media/Tags/Index', [
            'tags' => $tags,
        ]);
    }

    public function store(StoreMediaTagRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', MediaTag::class);

        $name = $request->validated('name');
        $tag = MediaTag::create([
            'team_id' => $request->user()->current_team_id,
            'name' => $name,
            'slug' => Str::slug($name),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'tag' => $tag->only(['id', 'name']),
            ], 201);
        }

        return redirect()->back()->with('success', 'Tag created successfully.');
    }

    public function update(UpdateMediaTagRequest $request, MediaTag $mediaTag): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $mediaTag);

        $name = $request->validated('name');

        $mediaTag->update([
            'name' => $name,
            'slug' => Str::slug($name),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Tag updated successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Tag updated successfully.');
    }

    public function destroy(Request $request, MediaTag $mediaTag): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $mediaTag);

        $mediaTag->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Tag deleted successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Tag deleted successfully.');
    }
}
