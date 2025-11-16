<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Source;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function index(Request $request): Response
    {
        $teamId = auth()->user()->current_team_id;

        $query = Post::query()
            ->whereHas('source', fn ($q) => $q->where('team_id', $teamId))
            ->with('source:id,internal_name');

        // Filter by source
        if ($request->filled('source_id')) {
            $query->where('source_id', $request->source_id);
        }

        // Filter by tags (through source)
        if ($request->filled('tag_ids')) {
            $tagIds = is_array($request->tag_ids) ? $request->tag_ids : [$request->tag_ids];
            $query->whereHas('source.tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
        }

        // Filter by keyword in URI or summary
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('uri', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        // Filter by relevancy score
        if ($request->filled('min_relevancy')) {
            $query->where('relevancy_score', '>=', $request->min_relevancy);
        }

        // Filter by read status
        if ($request->has('is_read') && $request->is_read !== null && $request->is_read !== '') {
            $query->where('is_read', filter_var($request->is_read, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by hidden status (default: show non-hidden)
        if ($request->has('show_hidden') && filter_var($request->show_hidden, FILTER_VALIDATE_BOOLEAN)) {
            // Show all including hidden
        } else {
            $query->where('is_hidden', false);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $posts = $query
            ->orderByDesc('found_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Post $post) => [
                'id' => $post->id,
                'uri' => $post->uri,
                'external_title' => $post->external_title,
                'internal_title' => $post->internal_title,
                'summary' => $post->summary,
                'relevancy_score' => $post->relevancy_score,
                'is_read' => $post->is_read,
                'is_hidden' => $post->is_hidden,
                'status' => $post->status,
                'found_at' => $post->found_at->diffForHumans(),
                'source' => [
                    'id' => $post->source->id,
                    'internal_name' => $post->source->internal_name,
                ],
            ]);

        return Inertia::render('Posts/Index', [
            'posts' => $posts,
            'sources' => Source::where('team_id', $teamId)->get(['id', 'internal_name']),
            'tags' => Tag::where('team_id', $teamId)->get(['id', 'name']),
            'filters' => [
                'source_id' => $request->source_id,
                'tag_ids' => $request->tag_ids ?? [],
                'search' => $request->search,
                'min_relevancy' => $request->min_relevancy,
                'is_read' => $request->is_read,
                'show_hidden' => $request->show_hidden,
                'status' => $request->status,
            ],
        ]);
    }

    public function toggleRead(Post $post): RedirectResponse
    {
        $this->authorizePost($post);

        $post->update(['is_read' => ! $post->is_read]);

        return back();
    }

    public function toggleHidden(Post $post): RedirectResponse
    {
        $this->authorizePost($post);

        $post->update(['is_hidden' => ! $post->is_hidden]);

        return back();
    }

    public function updateStatus(Request $request, Post $post): RedirectResponse
    {
        $this->authorizePost($post);

        $request->validate([
            'status' => ['required', 'in:NOT_RELEVANT,CREATE_CONTENT'],
        ]);

        $post->update(['status' => $request->status]);

        return back();
    }

    public function bulkToggleRead(Request $request): RedirectResponse
    {
        $request->validate([
            'post_ids' => ['required', 'array'],
            'post_ids.*' => ['exists:posts,id'],
            'is_read' => ['required', 'boolean'],
        ]);

        $teamId = auth()->user()->current_team_id;

        Post::whereIn('id', $request->post_ids)
            ->whereHas('source', fn ($q) => $q->where('team_id', $teamId))
            ->update(['is_read' => $request->is_read]);

        return back();
    }

    public function bulkHide(Request $request): RedirectResponse
    {
        $request->validate([
            'post_ids' => ['required', 'array'],
            'post_ids.*' => ['exists:posts,id'],
        ]);

        $teamId = auth()->user()->current_team_id;

        Post::whereIn('id', $request->post_ids)
            ->whereHas('source', fn ($q) => $q->where('team_id', $teamId))
            ->update(['is_hidden' => true]);

        return back();
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $request->validate([
            'post_ids' => ['required', 'array'],
            'post_ids.*' => ['exists:posts,id'],
        ]);

        $teamId = auth()->user()->current_team_id;

        Post::whereIn('id', $request->post_ids)
            ->whereHas('source', fn ($q) => $q->where('team_id', $teamId))
            ->delete();

        return back();
    }

    private function authorizePost(Post $post): void
    {
        if ($post->source->team_id !== auth()->user()->current_team_id) {
            abort(403);
        }
    }
}
