<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWebhookRequest;
use App\Http\Requests\UpdateWebhookRequest;
use App\Jobs\SendWebhookNotification;
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

        return redirect('/team-settings?tab=webhooks')
            ->with('success', 'Webhook created successfully.');
    }

    public function edit(Webhook $webhook): Response
    {
        $this->authorize('view', $webhook);

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
        $this->authorize('update', $webhook);

        $webhook->update($request->validated());

        return redirect('/team-settings?tab=webhooks')
            ->with('success', 'Webhook updated successfully.');
    }

    public function destroy(Webhook $webhook): RedirectResponse
    {
        $this->authorize('delete', $webhook);

        $webhook->delete();

        return redirect('/team-settings?tab=webhooks')
            ->with('success', 'Webhook deleted successfully.');
    }

    public function test(Webhook $webhook): RedirectResponse
    {
        $this->authorize('test', $webhook);

        $testPayload = [
            'event' => $webhook->event,
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'test' => true,
                'webhook_id' => $webhook->id,
                'webhook_name' => $webhook->name,
                'source_id' => 1,
                'source_name' => 'Example Test Source',
                'new_posts_count' => 3,
                'posts' => [
                    [
                        'title' => 'Revolutionary AI Model Achieves Human-Level Performance',
                        'url' => 'https://example.com/articles/ai-breakthrough-2024',
                    ],
                    [
                        'title' => 'Climate Tech Startup Raises $50M Series B Funding',
                        'url' => 'https://example.com/articles/climate-tech-funding',
                    ],
                    [
                        'title' => 'New Study Reveals Impact of Remote Work on Productivity',
                        'url' => 'https://example.com/articles/remote-work-study',
                    ],
                ],
            ],
        ];

        SendWebhookNotification::dispatch($webhook, $testPayload);

        return back()->with('success', 'Test webhook has been queued.');
    }
}
