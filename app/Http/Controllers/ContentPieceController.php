<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContentPieceRequest;
use App\Http\Requests\UpdateContentPieceRequest;
use App\Jobs\GenerateContentPiece;
use App\Models\ContentPiece;
use App\Models\Prompt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ContentPieceController extends Controller
{
    public function index(Request $request): Response
    {
        $teamId = auth()->user()->current_team_id;

        $query = ContentPiece::query()
            ->where('team_id', $teamId)
            ->with('prompt:id,internal_name');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by channel
        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        // Search by name or briefing
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('internal_name', 'like', "%{$search}%")
                    ->orWhere('briefing_text', 'like', "%{$search}%");
            });
        }

        $contentPieces = $query
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ContentPiece $piece) => [
                'id' => $piece->id,
                'internal_name' => $piece->internal_name,
                'channel' => $piece->channel,
                'target_language' => $piece->target_language,
                'status' => $piece->status,
                'prompt_name' => $piece->prompt?->internal_name,
                'created_at' => $piece->created_at->diffForHumans(),
            ]);

        return Inertia::render('ContentPieces/Index', [
            'contentPieces' => $contentPieces,
            'filters' => $request->only(['status', 'channel', 'search']),
        ]);
    }

    public function create(Request $request): Response
    {
        $teamId = auth()->user()->current_team_id;

        $prompts = Prompt::where('team_id', $teamId)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get(['id', 'internal_name as name', 'channel']);

        // Pre-selected post IDs from query params
        $preselectedPostIds = $request->input('post_ids', []);
        if (! is_array($preselectedPostIds)) {
            $preselectedPostIds = [$preselectedPostIds];
        }
        $preselectedPostIds = array_map('intval', $preselectedPostIds);

        // If post_ids are provided, only show those specific posts
        // Otherwise, show all available posts for selection
        if (! empty($preselectedPostIds)) {
            $availablePosts = \App\Models\Post::query()
                ->whereHas('source', fn ($q) => $q->where('team_id', $teamId))
                ->whereIn('id', $preselectedPostIds)
                ->get(['id', 'uri', 'summary', 'external_title', 'internal_title']);
        } else {
            $availablePosts = \App\Models\Post::query()
                ->whereHas('source', fn ($q) => $q->where('team_id', $teamId))
                ->where('status', 'CREATE_CONTENT')
                ->whereNotNull('summary')
                ->orderByDesc('found_at')
                ->take(100)
                ->get(['id', 'uri', 'summary', 'external_title', 'internal_title']);
        }

        // Get the first selected post's title for pre-populating the name
        $firstPostTitle = null;
        if (! empty($preselectedPostIds)) {
            $firstPost = $availablePosts->firstWhere('id', $preselectedPostIds[0]);
            if ($firstPost) {
                $firstPostTitle = $firstPost->external_title ?? $firstPost->internal_title ?? $firstPost->uri;
            }
        }

        return Inertia::render('ContentPieces/Create', [
            'prompts' => $prompts,
            'availablePosts' => $availablePosts,
            'preselectedPostIds' => $preselectedPostIds,
            'initialTitle' => $firstPostTitle,
        ]);
    }

    public function store(StoreContentPieceRequest $request): RedirectResponse
    {
        $teamId = auth()->user()->current_team_id;

        $contentPiece = ContentPiece::create([
            'team_id' => $teamId,
            ...$request->validated(),
            'status' => 'NOT_STARTED',
            'full_text' => null,
        ]);

        // Attach selected posts
        if ($request->has('post_ids')) {
            $contentPiece->posts()->attach($request->post_ids);
        }

        // Check if we should generate content immediately
        if ($request->boolean('generate_content') && $contentPiece->prompt_id) {
            $contentPiece->load('prompt', 'posts');

            return $this->generateContentForPiece($contentPiece);
        }

        return redirect()->route('content-pieces.edit', $contentPiece)
            ->with('success', 'Content piece created. You can now generate the content.');
    }

    public function edit(ContentPiece $contentPiece): Response
    {
        $this->authorize('view', $contentPiece);

        $contentPiece->load(['prompt:id,internal_name,prompt_text', 'posts:id,uri,summary']);

        $teamId = auth()->user()->current_team_id;

        $prompts = Prompt::where('team_id', $teamId)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get(['id', 'internal_name as name', 'channel']);

        $availablePosts = \App\Models\Post::query()
            ->whereHas('source', fn ($q) => $q->where('team_id', $teamId))
            ->where('status', 'TO_REPURPOSE')
            ->whereNotNull('summary')
            ->orderByDesc('found_at')
            ->take(100)
            ->get(['id', 'uri', 'summary']);

        return Inertia::render('ContentPieces/Edit', [
            'contentPiece' => [
                'id' => $contentPiece->id,
                'internal_name' => $contentPiece->internal_name,
                'briefing_text' => $contentPiece->briefing_text,
                'channel' => $contentPiece->channel,
                'target_language' => $contentPiece->target_language,
                'status' => $contentPiece->status,
                'full_text' => $contentPiece->full_text,
                'prompt_id' => $contentPiece->prompt_id,
                'prompt' => $contentPiece->prompt,
                'posts' => $contentPiece->posts,
            ],
            'prompts' => $prompts,
            'availablePosts' => $availablePosts,
        ]);
    }

    public function update(UpdateContentPieceRequest $request, ContentPiece $contentPiece): RedirectResponse
    {
        $this->authorize('update', $contentPiece);

        $contentPiece->update($request->validated());

        // Sync selected posts
        if ($request->has('post_ids')) {
            $contentPiece->posts()->sync($request->post_ids);
        }

        return redirect()->route('content-pieces.edit', $contentPiece)
            ->with('success', 'Content piece updated successfully.');
    }

    public function generate(Request $request, ContentPiece $contentPiece): RedirectResponse
    {
        $this->authorize('generate', $contentPiece);

        if (! $contentPiece->prompt) {
            return back()->with('error', 'Please select a prompt template first.');
        }

        return $this->generateContentForPiece($contentPiece);
    }

    private function generateContentForPiece(ContentPiece $contentPiece): RedirectResponse
    {
        // Use database transaction with pessimistic locking for concurrency protection
        return DB::transaction(function () use ($contentPiece) {
            $locked = ContentPiece::lockForUpdate()->find($contentPiece->id);

            // Check if generation is already in progress
            if (in_array($locked->generation_status, ['QUEUED', 'PROCESSING'])) {
                return back()->with('error', 'Generation already in progress. Please wait for it to complete.');
            }

            // Update status and dispatch job
            $locked->update(['generation_status' => 'QUEUED']);
            GenerateContentPiece::dispatch($locked);

            // Return with polling metadata
            return back()->with([
                'success' => 'Content generation started. This may take 1-3 minutes.',
                'polling' => [
                    'content_piece_id' => $locked->id,
                    'status' => 'QUEUED',
                ],
            ]);
        });
    }

    public function updateStatus(Request $request, ContentPiece $contentPiece): RedirectResponse
    {
        $this->authorize('update', $contentPiece);

        $request->validate([
            'status' => ['required', 'in:NOT_STARTED,DRAFT,FINAL'],
        ]);

        $contentPiece->update(['status' => $request->status]);

        return back()->with('success', 'Status updated successfully.');
    }

    public function status(ContentPiece $contentPiece): JsonResponse
    {
        $this->authorize('view', $contentPiece);

        return response()->json([
            'generation_status' => $contentPiece->generation_status,
            'full_text' => $contentPiece->full_text,
            'status' => $contentPiece->status,
            'error' => $contentPiece->generation_error,
            'error_occurred_at' => $contentPiece->generation_error_occurred_at,
        ]);
    }

    public function destroy(ContentPiece $contentPiece): RedirectResponse
    {
        $this->authorize('delete', $contentPiece);

        $contentPiece->posts()->detach();
        $contentPiece->delete();

        return redirect()->route('content-pieces.index')
            ->with('success', 'Content piece deleted successfully.');
    }
}
