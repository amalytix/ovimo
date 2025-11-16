<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWebhookRequest;
use App\Http\Requests\UpdateWebhookRequest;
use App\Models\Webhook;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WebhookController extends Controller
{
    public function index(): Response
    {
        $teamId = auth()->user()->current_team_id;

        return Inertia::render('Webhooks/Index', [
            'webhooks' => Webhook::query()
                ->where('team_id', $teamId)
                ->orderBy('name')
                ->paginate(15)
                ->through(fn (Webhook $webhook) => [
                    'id' => $webhook->id,
                    'name' => $webhook->name,
                    'url' => $webhook->url,
                    'event' => $webhook->event,
                    'is_active' => $webhook->is_active,
                    'last_triggered_at' => $webhook->last_triggered_at?->diffForHumans(),
                    'failure_count' => $webhook->failure_count,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Webhooks/Create');
    }

    public function store(StoreWebhookRequest $request): RedirectResponse
    {
        Webhook::create([
            'team_id' => auth()->user()->current_team_id,
            ...$request->validated(),
        ]);

        return redirect()->route('webhooks.index')
            ->with('success', 'Webhook created successfully.');
    }

    public function edit(Webhook $webhook): Response
    {
        $this->authorizeTeam($webhook);

        return Inertia::render('Webhooks/Edit', [
            'webhook' => [
                'id' => $webhook->id,
                'name' => $webhook->name,
                'url' => $webhook->url,
                'event' => $webhook->event,
                'is_active' => $webhook->is_active,
                'secret' => $webhook->secret,
            ],
        ]);
    }

    public function update(UpdateWebhookRequest $request, Webhook $webhook): RedirectResponse
    {
        $this->authorizeTeam($webhook);

        $webhook->update($request->validated());

        return redirect()->route('webhooks.index')
            ->with('success', 'Webhook updated successfully.');
    }

    public function destroy(Webhook $webhook): RedirectResponse
    {
        $this->authorizeTeam($webhook);

        $webhook->delete();

        return redirect()->route('webhooks.index')
            ->with('success', 'Webhook deleted successfully.');
    }

    private function authorizeTeam(Webhook $webhook): void
    {
        if ($webhook->team_id !== auth()->user()->current_team_id) {
            abort(403);
        }
    }
}
