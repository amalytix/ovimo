<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePromptRequest;
use App\Http\Requests\UpdatePromptRequest;
use App\Models\Channel;
use App\Models\Prompt;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PromptController extends Controller
{
    public function index(): Response
    {
        $teamId = auth()->user()->current_team_id;

        return Inertia::render('Prompts/Index', [
            'prompts' => Prompt::query()
                ->where('team_id', $teamId)
                ->with('channel')
                ->withCount('contentPieces')
                ->orderByDesc('is_default')
                ->orderBy('internal_name')
                ->paginate(15)
                ->through(function (Prompt $prompt) {
                    $channelRelation = $prompt->getRelation('channel');

                    return [
                        'id' => $prompt->id,
                        'internal_name' => $prompt->internal_name,
                        'type' => $prompt->type,
                        'channel' => $channelRelation instanceof Channel ? [
                            'id' => $channelRelation->id,
                            'name' => $channelRelation->name,
                            'icon' => $channelRelation->icon,
                            'color' => $channelRelation->color,
                        ] : null,
                        'prompt_text' => $prompt->prompt_text,
                        'content_pieces_count' => $prompt->content_pieces_count,
                        'created_at' => $prompt->created_at->diffForHumans(),
                        'is_default' => $prompt->is_default,
                    ];
                }),
        ]);
    }

    public function create(): Response
    {
        $teamId = auth()->user()->current_team_id;

        return Inertia::render('Prompts/Create', [
            'channels' => Channel::query()
                ->where('team_id', $teamId)
                ->active()
                ->ordered()
                ->get()
                ->map(fn (Channel $channel) => [
                    'id' => $channel->id,
                    'name' => $channel->name,
                    'icon' => $channel->icon,
                    'color' => $channel->color,
                ]),
        ]);
    }

    public function store(StorePromptRequest $request): RedirectResponse
    {
        Prompt::create([
            'team_id' => auth()->user()->current_team_id,
            ...$request->validated(),
        ]);

        return redirect()->route('prompts.index')
            ->with('success', 'Prompt created successfully.');
    }

    public function edit(Prompt $prompt): Response
    {
        $this->authorize('view', $prompt);

        $teamId = auth()->user()->current_team_id;

        return Inertia::render('Prompts/Edit', [
            'prompt' => [
                'id' => $prompt->id,
                'internal_name' => $prompt->internal_name,
                'type' => $prompt->type,
                'channel_id' => $prompt->channel_id,
                'prompt_text' => $prompt->prompt_text,
            ],
            'channels' => Channel::query()
                ->where('team_id', $teamId)
                ->active()
                ->ordered()
                ->get()
                ->map(fn (Channel $channel) => [
                    'id' => $channel->id,
                    'name' => $channel->name,
                    'icon' => $channel->icon,
                    'color' => $channel->color,
                ]),
        ]);
    }

    public function update(UpdatePromptRequest $request, Prompt $prompt): RedirectResponse
    {
        $this->authorize('update', $prompt);

        $prompt->update($request->validated());

        return redirect()->route('prompts.index')
            ->with('success', 'Prompt updated successfully.');
    }

    public function destroy(Prompt $prompt): RedirectResponse
    {
        $this->authorize('delete', $prompt);

        $prompt->delete();

        return redirect()->route('prompts.index')
            ->with('success', 'Prompt deleted successfully.');
    }

    public function setDefault(Prompt $prompt): RedirectResponse
    {
        $this->authorize('setDefault', $prompt);

        $teamId = auth()->user()->current_team_id;

        // Remove is_default flag from all other prompts in this team
        Prompt::where('team_id', $teamId)
            ->where('id', '!=', $prompt->id)
            ->update(['is_default' => false]);

        // Set this prompt as default
        $prompt->update(['is_default' => true]);

        return redirect()->route('prompts.index')
            ->with('success', 'Default prompt updated successfully.');
    }
}
