<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContentPieceRequest;
use App\Http\Requests\UpdateContentPieceRequest;
use App\Models\ContentPiece;
use App\Models\Prompt;
use App\Services\OpenAIService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentPieceController extends Controller
{
    public function index(Request $request): Response
    {
        $teamId = auth()->user()->current_team_id;

        $query = ContentPiece::query()
            ->where('team_id', $teamId)
            ->with('prompt:id,name');

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
                'prompt_name' => $piece->prompt?->name,
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
            ->orderBy('internal_name')
            ->get(['id', 'internal_name as name']);

        $availablePosts = \App\Models\Post::query()
            ->whereHas('source', fn ($q) => $q->where('team_id', $teamId))
            ->where('status', 'CREATE_CONTENT')
            ->whereNotNull('summary')
            ->orderByDesc('found_at')
            ->take(100)
            ->get(['id', 'uri', 'summary']);

        // Pre-selected post IDs from query params
        $preselectedPostIds = $request->input('post_ids', []);
        if (! is_array($preselectedPostIds)) {
            $preselectedPostIds = [$preselectedPostIds];
        }
        $preselectedPostIds = array_map('intval', $preselectedPostIds);

        // If there are pre-selected posts not in availablePosts, add them
        if (! empty($preselectedPostIds)) {
            $missingPostIds = array_diff($preselectedPostIds, $availablePosts->pluck('id')->toArray());
            if (! empty($missingPostIds)) {
                $missingPosts = \App\Models\Post::query()
                    ->whereHas('source', fn ($q) => $q->where('team_id', $teamId))
                    ->whereIn('id', $missingPostIds)
                    ->get(['id', 'uri', 'summary']);
                $availablePosts = $availablePosts->merge($missingPosts);
            }
        }

        return Inertia::render('ContentPieces/Create', [
            'prompts' => $prompts,
            'availablePosts' => $availablePosts,
            'preselectedPostIds' => $preselectedPostIds,
        ]);
    }

    public function store(StoreContentPieceRequest $request, OpenAIService $openAI): RedirectResponse
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

            return $this->generateContentForPiece($contentPiece, $openAI);
        }

        return redirect()->route('content-pieces.edit', $contentPiece)
            ->with('success', 'Content piece created. You can now generate the content.');
    }

    public function edit(ContentPiece $contentPiece): Response
    {
        $this->authorizeTeam($contentPiece);

        $contentPiece->load(['prompt:id,internal_name,prompt_text', 'posts:id,uri,summary']);

        $teamId = auth()->user()->current_team_id;

        $prompts = Prompt::where('team_id', $teamId)
            ->orderBy('internal_name')
            ->get(['id', 'internal_name as name']);

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
        $this->authorizeTeam($contentPiece);

        $contentPiece->update($request->validated());

        // Sync selected posts
        if ($request->has('post_ids')) {
            $contentPiece->posts()->sync($request->post_ids);
        }

        return redirect()->route('content-pieces.edit', $contentPiece)
            ->with('success', 'Content piece updated successfully.');
    }

    public function generate(Request $request, ContentPiece $contentPiece, OpenAIService $openAI): RedirectResponse
    {
        $this->authorizeTeam($contentPiece);

        if (! $contentPiece->prompt) {
            return back()->with('error', 'Please select a prompt template first.');
        }

        return $this->generateContentForPiece($contentPiece, $openAI);
    }

    private function generateContentForPiece(ContentPiece $contentPiece, OpenAIService $openAI): RedirectResponse
    {
        // Build context from linked posts
        $context = '';
        foreach ($contentPiece->posts as $post) {
            $context .= "Post: {$post->uri}\nSummary: {$post->summary}\n\n";
        }

        if ($contentPiece->briefing_text) {
            $context .= "Additional briefing: {$contentPiece->briefing_text}\n\n";
        }

        $context .= "Target channel: {$contentPiece->channel}\n";
        $context .= "Target language: {$contentPiece->target_language}\n";

        // Replace placeholders in prompt
        $promptText = $contentPiece->prompt->prompt_text;
        $promptText = str_replace('{{context}}', $context, $promptText);
        $promptText = str_replace('{{channel}}', $contentPiece->channel, $promptText);
        $promptText = str_replace('{{language}}', $contentPiece->target_language, $promptText);

        try {
            $result = $openAI->generateContent($promptText, '');

            $contentPiece->update([
                'full_text' => $result['content'],
                'status' => 'DRAFT',
            ]);

            // Track usage
            $team = $contentPiece->team;
            $openAI->trackUsage(
                $result['input_tokens'],
                $result['output_tokens'],
                $result['total_tokens'],
                $result['model'],
                auth()->user(),
                $team,
                'content_generation'
            );

            return redirect()->route('content-pieces.edit', $contentPiece)
                ->with('success', "Content generated successfully. Used {$result['total_tokens']} tokens.");
        } catch (\Exception $e) {
            return redirect()->route('content-pieces.edit', $contentPiece)
                ->with('error', 'Failed to generate content: '.$e->getMessage());
        }
    }

    public function updateStatus(Request $request, ContentPiece $contentPiece): RedirectResponse
    {
        $this->authorizeTeam($contentPiece);

        $request->validate([
            'status' => ['required', 'in:NOT_STARTED,DRAFT,FINAL'],
        ]);

        $contentPiece->update(['status' => $request->status]);

        return back()->with('success', 'Status updated successfully.');
    }

    public function destroy(ContentPiece $contentPiece): RedirectResponse
    {
        $this->authorizeTeam($contentPiece);

        $contentPiece->posts()->detach();
        $contentPiece->delete();

        return redirect()->route('content-pieces.index')
            ->with('success', 'Content piece deleted successfully.');
    }

    private function authorizeTeam(ContentPiece $contentPiece): void
    {
        if ($contentPiece->team_id !== auth()->user()->current_team_id) {
            abort(403);
        }
    }
}
