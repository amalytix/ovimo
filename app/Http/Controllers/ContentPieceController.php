<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContentPieceRequest;
use App\Http\Requests\UpdateContentPieceRequest;
use App\Jobs\GenerateContentPiece;
use App\Models\ContentPiece;
use App\Models\Prompt;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
        $this->authorize('viewAny', ContentPiece::class);

        $validated = $request->validate([
            'status' => ['nullable', 'in:NOT_STARTED,DRAFT,FINAL'],
            'channel' => ['nullable', 'in:BLOG_POST,LINKEDIN_POST,YOUTUBE_SCRIPT'],
            'search' => ['nullable', 'string'],
            'view' => ['nullable', 'in:list,week,month'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'sort_by' => ['nullable', 'in:published_at'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'date' => ['nullable', 'date'],
        ]);

        $teamId = auth()->user()->current_team_id;

        $query = ContentPiece::query()
            ->where('team_id', $teamId)
            ->with('prompt:id,internal_name');

        $sortBy = $validated['sort_by'] ?? null;
        $sortDirection = $validated['sort_direction'] ?? 'asc';

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['channel'])) {
            $query->where('channel', $validated['channel']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('internal_name', 'like', "%{$search}%")
                    ->orWhere('briefing_text', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['start_date'])) {
            $query->whereDate('published_at', '>=', $validated['start_date']);
        }

        if (! empty($validated['end_date'])) {
            $query->whereDate('published_at', '<=', $validated['end_date']);
        }

        if ($sortBy === 'published_at') {
            $query
                ->orderByRaw('published_at IS NOT NULL')
                ->orderBy('published_at', $sortDirection)
                ->orderByDesc('created_at');
        } else {
            $query->orderedForPublishing();
        }

        $contentPieces = $query
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
                'published_at' => $piece->published_at?->toIso8601String(),
                'published_at_human' => $piece->published_at?->diffForHumans(),
            ]);

        return Inertia::render('ContentPieces/Index', [
            'contentPieces' => $contentPieces,
            'filters' => [
                'status' => $validated['status'] ?? null,
                'channel' => $validated['channel'] ?? null,
                'search' => $validated['search'] ?? null,
                'view' => $validated['view'] ?? 'list',
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'sort_by' => $validated['sort_by'] ?? null,
                'sort_direction' => $validated['sort_direction'] ?? null,
                'date' => $validated['date'] ?? null,
            ],
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
            ->where('status', 'CREATE_CONTENT')
            ->whereNotNull('summary')
            ->orderByDesc('found_at')
            ->take(100)
            ->get(['id', 'uri', 'summary', 'external_title', 'internal_title']);

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
                'published_at' => $contentPiece->published_at?->toIso8601String(),
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

    public function calendar(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ContentPiece::class);

        $validated = $request->validate([
            'view' => ['required', 'in:week,month'],
            'date' => ['required', 'date'],
        ]);

        $teamId = auth()->user()->current_team_id;
        $currentDate = CarbonImmutable::parse($validated['date']);

        if ($validated['view'] === 'week') {
            $startDate = $currentDate->startOfWeek(CarbonInterface::MONDAY);
            $endDate = $startDate->endOfWeek(CarbonInterface::SUNDAY);
        } else {
            $startDate = $currentDate->startOfMonth()->startOfWeek(CarbonInterface::MONDAY);
            $endDate = $currentDate->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);
        }

        $contentPieces = ContentPiece::query()
            ->where('team_id', $teamId)
            ->whereNotNull('published_at')
            ->whereBetween('published_at', [$startDate, $endDate])
            ->with('prompt:id,internal_name')
            ->orderedForPublishing()
            ->get();

        $events = $contentPieces
            ->groupBy(fn (ContentPiece $piece) => $piece->published_at?->toDateString())
            ->map(fn ($group) => $group->map(fn (ContentPiece $piece) => [
                'id' => $piece->id,
                'internal_name' => $piece->internal_name,
                'channel' => $piece->channel,
                'target_language' => $piece->target_language,
                'status' => $piece->status,
                'prompt_name' => $piece->prompt?->internal_name,
                'published_at' => $piece->published_at?->toIso8601String(),
                'published_at_human' => $piece->published_at?->diffForHumans(),
            ])->values())
            ->toArray();

        return response()->json([
            'events' => $events,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
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
