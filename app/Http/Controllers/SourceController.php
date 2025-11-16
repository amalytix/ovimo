<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSourceRequest;
use App\Http\Requests\UpdateSourceRequest;
use App\Models\Source;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SourceController extends Controller
{
    public function index(): Response
    {
        $teamId = auth()->user()->current_team_id;

        return Inertia::render('Sources/Index', [
            'sources' => Source::query()
                ->where('team_id', $teamId)
                ->with('tags')
                ->withCount('posts')
                ->orderBy('internal_name')
                ->paginate(15)
                ->through(fn (Source $source) => [
                    'id' => $source->id,
                    'internal_name' => $source->internal_name,
                    'type' => $source->type,
                    'url' => $source->url,
                    'monitoring_interval' => $source->monitoring_interval,
                    'is_active' => $source->is_active,
                    'should_notify' => $source->should_notify,
                    'auto_summarize' => $source->auto_summarize,
                    'last_checked_at' => $source->last_checked_at?->diffForHumans(),
                    'posts_count' => $source->posts_count,
                    'tags' => $source->tags->map(fn (Tag $tag) => [
                        'id' => $tag->id,
                        'name' => $tag->name,
                    ]),
                ]),
            'tags' => Tag::where('team_id', $teamId)->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        $teamId = auth()->user()->current_team_id;

        return Inertia::render('Sources/Create', [
            'tags' => Tag::where('team_id', $teamId)->get(['id', 'name']),
        ]);
    }

    public function store(StoreSourceRequest $request): RedirectResponse
    {
        $source = Source::create([
            'team_id' => auth()->user()->current_team_id,
            ...$request->safe()->except('tag_ids'),
        ]);

        if ($request->has('tag_ids')) {
            $source->tags()->sync($request->tag_ids);
        }

        return redirect()->route('sources.index')
            ->with('success', 'Source created successfully.');
    }

    public function show(Source $source): Response
    {
        $this->authorizeTeam($source);

        return Inertia::render('Sources/Show', [
            'source' => [
                'id' => $source->id,
                'internal_name' => $source->internal_name,
                'type' => $source->type,
                'url' => $source->url,
                'monitoring_interval' => $source->monitoring_interval,
                'is_active' => $source->is_active,
                'should_notify' => $source->should_notify,
                'auto_summarize' => $source->auto_summarize,
                'last_checked_at' => $source->last_checked_at?->diffForHumans(),
                'tags' => $source->tags->map(fn (Tag $tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                ]),
            ],
        ]);
    }

    public function edit(Source $source): Response
    {
        $this->authorizeTeam($source);

        $teamId = auth()->user()->current_team_id;

        return Inertia::render('Sources/Edit', [
            'source' => [
                'id' => $source->id,
                'internal_name' => $source->internal_name,
                'type' => $source->type,
                'url' => $source->url,
                'monitoring_interval' => $source->monitoring_interval,
                'is_active' => $source->is_active,
                'should_notify' => $source->should_notify,
                'auto_summarize' => $source->auto_summarize,
                'tag_ids' => $source->tags->pluck('id'),
            ],
            'tags' => Tag::where('team_id', $teamId)->get(['id', 'name']),
        ]);
    }

    public function update(UpdateSourceRequest $request, Source $source): RedirectResponse
    {
        $this->authorizeTeam($source);

        $source->update($request->safe()->except('tag_ids'));

        if ($request->has('tag_ids')) {
            $source->tags()->sync($request->tag_ids);
        } else {
            $source->tags()->detach();
        }

        return redirect()->route('sources.index')
            ->with('success', 'Source updated successfully.');
    }

    public function destroy(Source $source): RedirectResponse
    {
        $this->authorizeTeam($source);

        $source->delete();

        return redirect()->route('sources.index')
            ->with('success', 'Source deleted successfully.');
    }

    private function authorizeTeam(Source $source): void
    {
        if ($source->team_id !== auth()->user()->current_team_id) {
            abort(403);
        }
    }
}
