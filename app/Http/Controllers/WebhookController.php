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

        return redirect()->route('webhooks.index')
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

        return redirect()->route('webhooks.index')
            ->with('success', 'Webhook updated successfully.');
    }

    public function destroy(Webhook $webhook): RedirectResponse
    {
        $this->authorize('delete', $webhook);

        $webhook->delete();

        return redirect()->route('webhooks.index')
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
                'source_name' => 'Example Source',
                'post_id' => 123,
                'post_uri' => 'https://example.com/article/test-post',
                'post_external_title' => 'Example Post Title for Testing',
                'post_summary' => 'This is a sample summary of the post content that would normally be generated from the actual article.',
                'post_relevancy_score' => 85,
                'post_created_at' => now()->toIso8601String(),
            ],
        ];

        SendWebhookNotification::dispatch($webhook, $testPayload);

        return back()->with('success', 'Test webhook has been queued.');
    }
}
