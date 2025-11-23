<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContentPiece\BulkActionRequest;
use App\Http\Requests\StoreContentPieceRequest;
use App\Http\Requests\UpdateContentPieceRequest;
use App\Jobs\GenerateContentPiece;
use App\Models\ContentPiece;
use App\Models\Media;
use App\Models\MediaTag;
use App\Models\Prompt;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
                ->orderByRaw('published_at IS NULL')
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

        $availableMedia = Media::query()
            ->where('team_id', $teamId)
            ->with('tags')
            ->latest()
            ->take(24)
            ->get();
        $mediaTags = MediaTag::where('team_id', $teamId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('ContentPieces/Create', [
            'prompts' => $prompts,
            'availablePosts' => $availablePosts,
            'preselectedPostIds' => $preselectedPostIds,
            'initialTitle' => $firstPostTitle,
            'media' => $availableMedia->map(fn (Media $media) => $this->transformMedia($media)),
            'mediaTags' => $mediaTags,
        ]);
    }

    public function store(StoreContentPieceRequest $request): RedirectResponse
    {
        $teamId = auth()->user()->current_team_id;
        $validated = $request->validated();

        $contentPiece = ContentPiece::create([
            'team_id' => $teamId,
            ...Arr::except($validated, ['post_ids', 'media_ids']),
            'status' => 'NOT_STARTED',
            'research_text' => $validated['research_text'] ?? null,
            'edited_text' => $validated['edited_text'] ?? null,
        ]);

        // Attach selected posts
        if (array_key_exists('post_ids', $validated)) {
            $contentPiece->posts()->attach($validated['post_ids']);
        }

        if (array_key_exists('media_ids', $validated)) {
            $contentPiece->media()->sync($validated['media_ids']);
        }

        // Check if we should generate content immediately
        if ($request->boolean('generate_content') && $contentPiece->prompt_id) {
            $contentPiece->load('prompt', 'posts', 'media');

            return $this->generateContentForPiece($contentPiece);
        }

        return redirect()->route('content-pieces.edit', $contentPiece)
            ->with('success', 'Content piece created. You can now generate the content.');
    }

    public function edit(ContentPiece $contentPiece): Response
    {
        $this->authorize('view', $contentPiece);

        $contentPiece->load(['prompt:id,internal_name,prompt_text', 'posts:id,uri,summary,external_title,internal_title', 'media.tags']);

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

        $availableMedia = Media::query()
            ->where('team_id', $teamId)
            ->with('tags')
            ->latest()
            ->take(24)
            ->get();
        $mediaTags = MediaTag::where('team_id', $teamId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('ContentPieces/Edit', [
            'contentPiece' => [
                'id' => $contentPiece->id,
                'internal_name' => $contentPiece->internal_name,
                'briefing_text' => $contentPiece->briefing_text,
                'channel' => $contentPiece->channel,
                'target_language' => $contentPiece->target_language,
                'status' => $contentPiece->status,
                'research_text' => $contentPiece->research_text,
                'edited_text' => $contentPiece->edited_text,
                'prompt_id' => $contentPiece->prompt_id,
                'prompt' => $contentPiece->prompt,
                'posts' => $contentPiece->posts,
                'media' => $contentPiece->media->map(fn (Media $media) => [
                    ...$this->transformMedia($media),
                    'pivot' => [
                        'sort_order' => $media->pivot->sort_order,
                    ],
                ]),
                'published_at' => $contentPiece->published_at?->toIso8601String(),
            ],
            'prompts' => $prompts,
            'availablePosts' => $availablePosts,
            'media' => $availableMedia->map(fn (Media $media) => $this->transformMedia($media)),
            'mediaTags' => $mediaTags,
        ]);
    }

    public function update(UpdateContentPieceRequest $request, ContentPiece $contentPiece): RedirectResponse
    {
        $this->authorize('update', $contentPiece);

        $validated = $request->validated();

        $contentPiece->update(Arr::except($validated, ['post_ids', 'media_ids']));

        // Sync selected posts
        if (array_key_exists('post_ids', $validated)) {
            $contentPiece->posts()->sync($validated['post_ids']);
        }

        if (array_key_exists('media_ids', $validated)) {
            $contentPiece->media()->sync($validated['media_ids']);
        }

        return redirect()->route('content-pieces.edit', [
            'content_piece' => $contentPiece,
            'tab' => $request->query('tab'),
        ])
            ->with('success', 'Content piece updated successfully.');
    }

    public function generate(Request $request, ContentPiece $contentPiece): RedirectResponse
    {
        $this->authorize('generate', $contentPiece);

        if (! $contentPiece->prompt) {
            return back()->with('error', 'Please select a prompt template first.');
        }

        return $this->generateContentForPiece($contentPiece, $request->query('tab'));
    }

    private function generateContentForPiece(ContentPiece $contentPiece, ?string $tab = null): RedirectResponse
    {
        // Use database transaction with pessimistic locking for concurrency protection
        return DB::transaction(function () use ($contentPiece, $tab) {
            $locked = ContentPiece::lockForUpdate()->find($contentPiece->id);

            // Check if generation is already in progress
            if (in_array($locked->generation_status, ['QUEUED', 'PROCESSING'])) {
                return redirect()->route('content-pieces.edit', [
                    'content_piece' => $locked,
                    'tab' => $tab,
                ])->with('error', 'Generation already in progress. Please wait for it to complete.');
            }

            // Update status and dispatch job
            $locked->update(['generation_status' => 'QUEUED']);
            GenerateContentPiece::dispatch($locked);

            // Return with polling metadata
            return redirect()->route('content-pieces.edit', [
                'content_piece' => $locked,
                'tab' => $tab,
            ])->with([
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

        return redirect()->route('content-pieces.edit', [
            'content_piece' => $contentPiece,
            'tab' => $request->query('tab'),
        ])->with('success', 'Status updated successfully.');
    }

    public function status(ContentPiece $contentPiece): JsonResponse
    {
        $this->authorize('view', $contentPiece);

        return response()->json([
            'generation_status' => $contentPiece->generation_status,
            'research_text' => $contentPiece->research_text,
            'edited_text' => $contentPiece->edited_text,
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

    public function bulkDelete(BulkActionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $teamId = auth()->user()->current_team_id;

        $contentPieces = ContentPiece::where('team_id', $teamId)
            ->whereIn('id', $validated['content_piece_ids'])
            ->get();

        foreach ($contentPieces as $contentPiece) {
            $this->authorize('delete', $contentPiece);
            $contentPiece->posts()->detach();
            $contentPiece->delete();
        }

        return response()->json(['message' => 'Content pieces deleted successfully.']);
    }

    public function bulkUnsetPublishDate(BulkActionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $teamId = auth()->user()->current_team_id;

        $contentPieces = ContentPiece::where('team_id', $teamId)
            ->whereIn('id', $validated['content_piece_ids'])
            ->get();

        foreach ($contentPieces as $contentPiece) {
            $this->authorize('update', $contentPiece);
            $contentPiece->update(['published_at' => null]);
        }

        return response()->json(['message' => 'Publish dates removed successfully.']);
    }

    public function bulkUpdateStatus(BulkActionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $teamId = auth()->user()->current_team_id;

        $contentPieces = ContentPiece::where('team_id', $teamId)
            ->whereIn('id', $validated['content_piece_ids'])
            ->get();

        foreach ($contentPieces as $contentPiece) {
            $this->authorize('update', $contentPiece);
            $contentPiece->update(['status' => $validated['status']]);
        }

        return response()->json(['message' => 'Status updated successfully.']);
    }

    private function transformMedia(Media $media): array
    {
        return [
            'id' => $media->id,
            'filename' => $media->filename,
            'mime_type' => $media->mime_type,
            'file_size' => $media->file_size,
            'created_at' => $media->created_at?->toDateTimeString(),
            'metadata' => $media->metadata,
            'tags' => $media->tags->map(fn (MediaTag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ]),
            'temporary_url' => $media->getTemporaryUrl(),
            'download_url' => route('media.download', $media),
        ];
    }
}
