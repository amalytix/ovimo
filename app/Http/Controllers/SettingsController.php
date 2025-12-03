<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportSourcesRequest;
use App\Http\Requests\UpdateTeamSettingsRequest;
use App\Models\SocialIntegration;
use App\Models\Source;
use App\Models\Tag;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $team = Team::find($user->current_team_id);

        $team->load(['users' => function ($query) {
            $query->select('users.id', 'name', 'email', 'two_factor_confirmed_at');
        }]);

        return Inertia::render('settings/Index', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'owner_id' => $team->owner_id,
                'post_auto_hide_days' => $team->post_auto_hide_days,
                'monthly_token_limit' => $team->monthly_token_limit,
                'relevancy_prompt' => $team->relevancy_prompt,
                'positive_keywords' => $team->positive_keywords,
                'negative_keywords' => $team->negative_keywords,
                'openai_api_key_masked' => $team->getMaskedOpenAIKey(),
                'openai_model' => $team->openai_model,
                'gemini_api_key_masked' => $team->getMaskedGeminiKey(),
                'gemini_image_model' => $team->gemini_image_model,
                'gemini_image_size' => $team->gemini_image_size,
                'has_openai' => $team->hasOpenAIConfigured(),
                'has_gemini' => $team->hasGeminiConfigured(),
                'users' => $team->users->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'two_factor_enabled' => $u->two_factor_confirmed_at !== null,
                ]),
            ],
            'pendingInvitations' => $team->pendingInvitations()
                ->select('id', 'email', 'created_at', 'expires_at')
                ->get(),
            'isOwner' => $team->owner_id === $user->id,
            'webhooks' => [
                'data' => $team->webhooks,
            ],
            'integrations' => [
                'linkedin' => SocialIntegration::query()
                    ->where('team_id', $team->id)
                    ->orderByDesc('created_at')
                    ->get([
                        'id',
                        'platform',
                        'platform_user_id',
                        'platform_username',
                        'profile_data',
                        'scopes',
                        'is_active',
                        'created_at',
                        'token_expires_at',
                    ]),
            ],
        ]);
    }

    public function update(UpdateTeamSettingsRequest $request): RedirectResponse
    {
        $team = Team::find(auth()->user()->current_team_id);

        // Only team owner can update settings
        if ($team->owner_id !== auth()->id()) {
            abort(403, 'Only the team owner can update settings.');
        }

        $payload = $request->validated();

        foreach (['openai_api_key', 'gemini_api_key'] as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] === '') {
                $payload[$key] = null;
            }
        }

        $team->update($payload);

        return back()->with('success', 'Team settings updated successfully.');
    }

    public function exportSources(): JsonResponse
    {
        $team = Team::find(auth()->user()->current_team_id);

        // Only team owner can export sources
        if ($team->owner_id !== auth()->id()) {
            abort(403, 'Only the team owner can export sources.');
        }

        $sources = Source::query()
            ->where('team_id', $team->id)
            ->with('tags')
            ->limit(1000)
            ->get()
            ->map(function (Source $source) {
                return [
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
                    'bypass_keyword_filter' => $source->bypass_keyword_filter,
                    'tags' => $source->tags->pluck('name')->toArray(),
                ];
            });

        $filename = 'sources-export-'.now()->format('Y-m-d-His').'.json';

        return response()->json($sources, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ], JSON_PRETTY_PRINT);
    }

    public function importSources(ImportSourcesRequest $request): RedirectResponse
    {
        $team = Team::find(auth()->user()->current_team_id);

        // Only team owner can import sources
        if ($team->owner_id !== auth()->id()) {
            abort(403, 'Only the team owner can import sources.');
        }

        // Parse the JSON file
        $fileContents = file_get_contents($request->file('file')->getRealPath());
        $sourcesData = json_decode($fileContents, true);

        // Check if JSON is valid
        if (json_last_error() !== JSON_ERROR_NONE) {
            return redirect('/team-settings?tab=import-export')->with('error', 'Invalid JSON file: '.json_last_error_msg());
        }

        // Check if it's an array
        if (! is_array($sourcesData)) {
            return redirect('/team-settings?tab=import-export')->with('error', 'The JSON file must contain an array of sources.');
        }

        // Validate the structure
        $validator = validator(['sources' => $sourcesData], [
            'sources' => ['required', 'array', 'max:1000'],
            'sources.*.internal_name' => ['required', 'string', 'max:255'],
            'sources.*.type' => ['required', 'in:RSS,XML_SITEMAP,WEBSITE,WEBHOOK'],
            'sources.*.url' => ['required', 'url', 'max:2048'],
            'sources.*.css_selector_title' => ['nullable', 'string', 'max:500'],
            'sources.*.css_selector_link' => ['nullable', 'string', 'max:500'],
            'sources.*.keywords' => ['nullable', 'string', 'max:1000'],
            'sources.*.monitoring_interval' => ['required', 'in:EVERY_10_MIN,EVERY_30_MIN,HOURLY,EVERY_6_HOURS,DAILY,WEEKLY'],
            'sources.*.is_active' => ['boolean'],
            'sources.*.should_notify' => ['boolean'],
            'sources.*.auto_summarize' => ['boolean'],
            'sources.*.bypass_keyword_filter' => ['boolean'],
            'sources.*.tags' => ['array'],
            'sources.*.tags.*' => ['string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return redirect('/team-settings?tab=import-export')->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $importedCount = 0;
        $skippedCount = 0;

        foreach ($validated['sources'] as $sourceData) {
            // Check if duplicate exists
            if ($this->isDuplicateSource($team->id, $sourceData['url'])) {
                $skippedCount++;

                continue;
            }

            // Create the source
            $source = Source::create([
                'team_id' => $team->id,
                'internal_name' => $sourceData['internal_name'],
                'type' => $sourceData['type'],
                'url' => $sourceData['url'],
                'css_selector_title' => $sourceData['css_selector_title'] ?? null,
                'css_selector_link' => $sourceData['css_selector_link'] ?? null,
                'keywords' => $sourceData['keywords'] ?? null,
                'monitoring_interval' => $sourceData['monitoring_interval'],
                'is_active' => $sourceData['is_active'] ?? true,
                'should_notify' => $sourceData['should_notify'] ?? false,
                'auto_summarize' => $sourceData['auto_summarize'] ?? true,
                'bypass_keyword_filter' => $sourceData['bypass_keyword_filter'] ?? false,
                'next_check_at' => now(),
            ]);

            // Sync tags if provided
            if (! empty($sourceData['tags'])) {
                $this->syncTagsByName($source, $sourceData['tags']);
            }

            $importedCount++;
        }

        $message = "Import completed: {$importedCount} source(s) imported";
        if ($skippedCount > 0) {
            $message .= ", {$skippedCount} duplicate(s) skipped";
        }
        $message .= '.';

        return redirect('/team-settings?tab=import-export')->with('success', $message);
    }

    private function isDuplicateSource(int $teamId, string $url): bool
    {
        return Source::query()
            ->where('team_id', $teamId)
            ->where('url', $url)
            ->exists();
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
