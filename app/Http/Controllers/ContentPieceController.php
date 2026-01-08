<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContentPiece\BulkActionRequest;
use App\Http\Requests\StoreContentPieceRequest;
use App\Http\Requests\UpdateContentPieceRequest;
use App\Models\Channel;
use App\Models\ContentDerivative;
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
use Inertia\Inertia;
use Inertia\Response;

class ContentPieceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ContentPiece::class);

        $validated = $request->validate([
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
            ->with(['prompt:id,internal_name', 'derivatives:id,content_piece_id,channel_id,status,generation_status']);

        $sortBy = $validated['sort_by'] ?? null;
        $sortDirection = $validated['sort_direction'] ?? 'asc';

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
            $query
                ->orderByRaw('published_at IS NOT NULL')
                ->orderBy('published_at')
                ->orderByDesc('created_at');
        }

        $contentPieces = $query
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ContentPiece $piece) => [
                'id' => $piece->id,
                'internal_name' => $piece->internal_name,
                'created_at' => $piece->created_at->diffForHumans(),
                'derivatives' => $piece->derivatives->map(fn ($d) => [
                    'id' => $d->id,
                    'channel_id' => $d->channel_id,
                    'status' => $d->status,
                    'generation_status' => $d->generation_status,
                ]),
            ]);

        // Get channels for derivative status display
        $channels = Channel::where('team_id', $teamId)->active()->ordered()->get(['id', 'name', 'language', 'icon', 'color']);

        return Inertia::render('ContentPieces/Index', [
            'contentPieces' => $contentPieces,
            'channels' => $channels,
            'filters' => [
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

        $team = auth()->user()->currentTeam;

        return Inertia::render('ContentPieces/Create', [
            'prompts' => $prompts,
            'availablePosts' => $availablePosts,
            'preselectedPostIds' => $preselectedPostIds,
            'initialTitle' => $firstPostTitle,
            'media' => $availableMedia->map(fn (Media $media) => $this->transformMedia($media)),
            'mediaTags' => $mediaTags,
            'ai' => [
                'has_openai' => $team->hasOpenAIConfigured(),
                'has_gemini' => $team->hasGeminiConfigured(),
                'settings_url' => '/team-settings?tab=ai',
            ],
        ]);
    }

    public function store(StoreContentPieceRequest $request): RedirectResponse
    {
        $teamId = auth()->user()->current_team_id;
        $validated = $request->validated();

        $contentPiece = ContentPiece::create([
            'team_id' => $teamId,
            'internal_name' => $validated['internal_name'],
            'published_at' => $validated['published_at'] ?? null,
            // Legacy field - set default for backward compatibility
            'channel' => 'BLOG_POST',
        ]);

        // Create background sources
        if (! empty($validated['sources'])) {
            foreach ($validated['sources'] as $index => $sourceData) {
                $contentPiece->backgroundSources()->create([
                    'type' => $sourceData['type'],
                    'post_id' => $sourceData['post_id'] ?? null,
                    'title' => $sourceData['title'] ?? null,
                    'content' => $sourceData['content'] ?? null,
                    'url' => $sourceData['url'] ?? null,
                    'sort_order' => $sourceData['sort_order'] ?? $index,
                ]);
            }
        }

        return redirect()->route('content-pieces.edit', ['content_piece' => $contentPiece, 'tab' => 'derivatives'])
            ->with('success', 'Content piece created. Add derivatives to generate content for different channels.');
    }

    public function edit(ContentPiece $contentPiece): Response
    {
        $this->authorize('view', $contentPiece);

        $contentPiece->load([
            'prompt:id,internal_name,prompt_text',
            'posts:id,uri,summary,external_title,internal_title',
            'imageGenerations.prompt',
            'imageGenerations.media',
            'team',
            'derivatives.channel',
            'derivatives.media.tags',
            'backgroundSources.post',
        ]);

        $teamId = auth()->user()->current_team_id;

        $channels = Channel::where('team_id', $teamId)
            ->active()
            ->ordered()
            ->get(['id', 'name', 'language', 'icon', 'color']);

        $prompts = Prompt::where('team_id', $teamId)
            ->where('type', Prompt::TYPE_CONTENT)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get(['id', 'internal_name as name', 'channel', 'channel_id']);

        $imagePrompts = Prompt::where('team_id', $teamId)
            ->where('type', Prompt::TYPE_IMAGE)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get(['id', 'internal_name']);

        // Only show posts already associated with this content piece (via contentPiece.posts relationship)
        $availablePosts = collect();

        $availableMedia = Media::query()
            ->where('team_id', $teamId)
            ->with('tags')
            ->latest()
            ->take(24)
            ->get();
        $mediaTags = MediaTag::where('team_id', $teamId)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Get available posts for sources tab
        $availablePostsForSources = \App\Models\Post::query()
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
                'prompt_id' => $contentPiece->prompt_id,
                'prompt' => $contentPiece->prompt,
                'posts' => $contentPiece->posts,
                'published_at' => $contentPiece->published_at?->toIso8601String(),
            ],
            'channels' => $channels,
            'derivatives' => $contentPiece->derivatives->map(fn ($d) => [
                'id' => $d->id,
                'content_piece_id' => $d->content_piece_id,
                'channel_id' => $d->channel_id,
                'prompt_id' => $d->prompt_id,
                'title' => $d->title,
                'text' => $d->text,
                'status' => $d->status,
                'planned_publish_at' => $d->planned_publish_at?->toIso8601String(),
                'generation_status' => $d->generation_status,
                'generation_error' => $d->generation_error,
                'media' => $d->media->map(fn (Media $media) => $this->transformMedia($media)),
            ]),
            'backgroundSources' => $contentPiece->backgroundSources->map(fn ($s) => [
                'id' => $s->id,
                'type' => $s->type,
                'post_id' => $s->post_id,
                'post' => $s->post ? [
                    'id' => $s->post->id,
                    'uri' => $s->post->uri,
                    'summary' => $s->post->summary,
                    'external_title' => $s->post->external_title,
                    'internal_title' => $s->post->internal_title,
                ] : null,
                'title' => $s->title,
                'content' => $s->content,
                'url' => $s->url,
                'sort_order' => $s->sort_order,
            ]),
            'availablePostsForSources' => $availablePostsForSources,
            'prompts' => $prompts,
            'imagePrompts' => $imagePrompts,
            'imageGenerations' => $contentPiece->imageGenerations->map(fn ($gen) => [
                'id' => $gen->id,
                'content_piece_id' => $gen->content_piece_id,
                'prompt_id' => $gen->prompt_id,
                'prompt' => $gen->prompt ? [
                    'id' => $gen->prompt->id,
                    'internal_name' => $gen->prompt->internal_name,
                ] : null,
                'generated_text_prompt' => $gen->generated_text_prompt,
                'aspect_ratio' => $gen->aspect_ratio,
                'status' => $gen->status,
                'media_id' => $gen->media_id,
                'media' => $gen->media ? [
                    'id' => $gen->media->id,
                    'filename' => $gen->media->filename,
                    'mime_type' => $gen->media->mime_type,
                    'temporary_url' => $gen->media->getTemporaryUrl(),
                ] : null,
                'error_message' => $gen->error_message,
                'created_at' => $gen->created_at?->toIso8601String(),
            ]),
            'availablePosts' => $availablePosts,
            'media' => $availableMedia->map(fn (Media $media) => $this->transformMedia($media)),
            'mediaTags' => $mediaTags,
            'ai' => [
                'has_openai' => $contentPiece->team->hasOpenAIConfigured(),
                'has_gemini' => $contentPiece->team->hasGeminiConfigured(),
                'settings_url' => '/team-settings?tab=ai',
            ],
        ]);
    }

    public function update(UpdateContentPieceRequest $request, ContentPiece $contentPiece): RedirectResponse
    {
        $this->authorize('update', $contentPiece);

        $validated = $request->validated();

        $contentPiece->update(Arr::except($validated, ['post_ids']));

        // Sync selected posts
        if (array_key_exists('post_ids', $validated)) {
            $contentPiece->posts()->sync($validated['post_ids']);
        }

        return redirect()->route('content-pieces.edit', [
            'content_piece' => $contentPiece,
            'tab' => $request->query('tab'),
        ])
            ->with('success', 'Content piece updated successfully.');
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

        $derivatives = ContentDerivative::query()
            ->whereHas('contentPiece', fn ($q) => $q->where('team_id', $teamId))
            ->whereNotNull('planned_publish_at')
            ->whereBetween('planned_publish_at', [$startDate, $endDate])
            ->with(['channel:id,name,icon,color', 'contentPiece:id,internal_name'])
            ->orderBy('planned_publish_at')
            ->orderByDesc('created_at')
            ->get();

        $events = $derivatives
            ->groupBy(fn (ContentDerivative $derivative) => $derivative->planned_publish_at?->toDateString())
            ->map(fn ($group) => $group->map(fn (ContentDerivative $derivative) => [
                'id' => $derivative->id,
                'title' => $derivative->title ?: $derivative->contentPiece->internal_name,
                'content_piece_id' => $derivative->content_piece_id,
                'channel_id' => $derivative->channel_id,
                'channel_name' => $derivative->channel->name,
                'channel_icon' => $derivative->channel->icon,
                'channel_color' => $derivative->channel->color,
                'status' => $derivative->status,
                'planned_publish_at' => $derivative->planned_publish_at?->toIso8601String(),
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
