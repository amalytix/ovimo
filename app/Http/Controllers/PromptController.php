<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePromptRequest;
use App\Http\Requests\UpdatePromptRequest;
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
                ->withCount('contentPieces')
                ->orderBy('internal_name')
                ->paginate(15)
                ->through(fn (Prompt $prompt) => [
                    'id' => $prompt->id,
                    'internal_name' => $prompt->internal_name,
                    'prompt_text' => $prompt->prompt_text,
                    'content_pieces_count' => $prompt->content_pieces_count,
                    'created_at' => $prompt->created_at->diffForHumans(),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Prompts/Create');
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
        $this->authorizeTeam($prompt);

        return Inertia::render('Prompts/Edit', [
            'prompt' => [
                'id' => $prompt->id,
                'internal_name' => $prompt->internal_name,
                'channel' => $prompt->channel,
                'prompt_text' => $prompt->prompt_text,
            ],
        ]);
    }

    public function update(UpdatePromptRequest $request, Prompt $prompt): RedirectResponse
    {
        $this->authorizeTeam($prompt);

        $prompt->update($request->validated());

        return redirect()->route('prompts.index')
            ->with('success', 'Prompt updated successfully.');
    }

    public function destroy(Prompt $prompt): RedirectResponse
    {
        $this->authorizeTeam($prompt);

        $prompt->delete();

        return redirect()->route('prompts.index')
            ->with('success', 'Prompt deleted successfully.');
    }

    private function authorizeTeam(Prompt $prompt): void
    {
        if ($prompt->team_id !== auth()->user()->current_team_id) {
            abort(403);
        }
    }
}
