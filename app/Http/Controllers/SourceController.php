<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSourceRequest;
use App\Http\Requests\UpdateSourceRequest;
use App\Jobs\MonitorSource;
use App\Models\Source;
use App\Models\Tag;
use App\Services\OpenAIService;
use App\Services\WebsiteParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SourceController extends Controller
{
    public function index(): Response
    {
        $teamId = auth()->user()->current_team_id;
        $tagIds = request()->input('tag_ids', []);

        $query = Source::query()
            ->where('team_id', $teamId)
            ->with('tags')
            ->withCount('posts');

        // Filter by tags if provided
        if (! empty($tagIds)) {
            $query->whereHas('tags', function ($q) use ($tagIds) {
                $q->whereIn('tags.id', $tagIds);
            });
        }

        return Inertia::render('Sources/Index', [
            'sources' => $query
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
            'filters' => [
                'tag_ids' => array_map('intval', (array) $tagIds),
            ],
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
        $data = $request->safe()->except('tags');

        // Normalize keywords: trim and lowercase
        if (isset($data['keywords']) && $data['keywords']) {
            $keywords = array_map(
                fn ($keyword) => strtolower(trim($keyword)),
                explode(',', $data['keywords'])
            );
            $keywords = array_filter($keywords, fn ($keyword) => $keyword !== '');
            $data['keywords'] = implode(',', $keywords);
        }

        $source = Source::create([
            'team_id' => auth()->user()->current_team_id,
            ...$data,
        ]);

        if ($request->has('tags')) {
            $this->syncTagsByName($source, $request->tags);
        }

        // Auto-trigger check if source is active
        if ($source->is_active) {
            MonitorSource::dispatch($source);
        }

        $message = 'Source created successfully.';
        if ($source->is_active) {
            $message .= ' Initial check has been queued.';
        }

        return redirect()->route('sources.index')
            ->with('success', $message);
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
                'css_selector_title' => $source->css_selector_title,
                'css_selector_link' => $source->css_selector_link,
                'keywords' => $source->keywords,
                'monitoring_interval' => $source->monitoring_interval,
                'is_active' => $source->is_active,
                'should_notify' => $source->should_notify,
                'auto_summarize' => $source->auto_summarize,
                'tags' => $source->tags->pluck('name')->toArray(),
            ],
            'tags' => Tag::where('team_id', $teamId)->get(['id', 'name']),
        ]);
    }

    public function update(UpdateSourceRequest $request, Source $source): RedirectResponse
    {
        $this->authorizeTeam($source);

        $data = $request->safe()->except('tags');

        // Normalize keywords: trim and lowercase
        if (isset($data['keywords']) && $data['keywords']) {
            $keywords = array_map(
                fn ($keyword) => strtolower(trim($keyword)),
                explode(',', $data['keywords'])
            );
            $keywords = array_filter($keywords, fn ($keyword) => $keyword !== '');
            $data['keywords'] = implode(',', $keywords);
        }

        $source->update($data);

        if ($request->has('tags')) {
            $this->syncTagsByName($source, $request->tags);
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

    public function check(Source $source): RedirectResponse
    {
        $this->authorizeTeam($source);

        MonitorSource::dispatch($source);

        return redirect()->route('sources.index')
            ->with('success', 'Source check has been queued.');
    }

    public function analyzeWebpage(Request $request, OpenAIService $openAIService, WebsiteParserService $parserService): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $url = $request->input('url');

        $html = $parserService->fetchHtml($url);

        // Truncate HTML if too large (to avoid token limits)
        $maxHtmlLength = 50000;
        if (strlen($html) > $maxHtmlLength) {
            $html = substr($html, 0, $maxHtmlLength);
        }

        $result = $openAIService->analyzeWebpage($html);

        // Track token usage
        $openAIService->trackUsage(
            $result['input_tokens'],
            $result['output_tokens'],
            $result['total_tokens'],
            $result['model'],
            auth()->user(),
            auth()->user()->currentTeam,
            'analyze_webpage'
        );

        return response()->json([
            'css_selector_title' => $result['css_selector_title'],
            'css_selector_link' => $result['css_selector_link'],
        ]);
    }

    public function testExtraction(Request $request, WebsiteParserService $parserService): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'css_selector_title' => ['required', 'string', 'max:500'],
            'css_selector_link' => ['required', 'string', 'max:500'],
            'keywords' => ['nullable', 'string', 'max:1000'],
        ]);

        $posts = $parserService->parse(
            $request->input('url'),
            $request->input('css_selector_title'),
            $request->input('css_selector_link'),
            $request->input('keywords'),
            5 // Return first 5 posts
        );

        return response()->json([
            'posts' => $posts,
        ]);
    }

    private function authorizeTeam(Source $source): void
    {
        if ($source->team_id !== auth()->user()->current_team_id) {
            abort(403);
        }
    }

    private function syncTagsByName(Source $source, array $tagNames): void
    {
        $teamId = auth()->user()->current_team_id;
        $tagIds = [];

        foreach ($tagNames as $tagName) {
            $tag = Tag::firstOrCreate(
                ['team_id' => $teamId, 'name' => $tagName],
                ['team_id' => $teamId, 'name' => $tagName]
            );
            $tagIds[] = $tag->id;
        }

        $source->tags()->sync($tagIds);
    }
}
