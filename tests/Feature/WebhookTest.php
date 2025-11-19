<?php

use App\Jobs\SendWebhookNotification;
use App\Models\Webhook;
use Illuminate\Support\Facades\Queue;

test('guests cannot access webhooks', function () {
    $this->get('/webhooks')->assertRedirect('/login');
});

test('authenticated users can view webhooks index', function () {
    [$user, $team] = createUserWithTeam();

    $this->actingAs($user)
        ->get('/webhooks')
        ->assertSuccessful();
});

test('webhooks index only shows team webhooks', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

    $teamWebhook = Webhook::factory()->create(['team_id' => $team->id]);
    $otherWebhook = Webhook::factory()->create(['team_id' => $otherTeam->id]);

    $response = $this->actingAs($user)->get('/webhooks');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Webhooks/Index')
        ->has('webhooks.data', 1)
        ->where('webhooks.data.0.id', $teamWebhook->id)
    );
});

test('authenticated users can view create webhook form', function () {
    [$user, $team] = createUserWithTeam();

    $this->actingAs($user)
        ->get('/webhooks/create')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Webhooks/Create'));
});

test('authenticated users can create a webhook', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/webhooks', [
        'name' => 'Test Webhook',
        'url' => 'https://example.com/webhook',
        'event' => 'NEW_POSTS',
        'is_active' => true,
        'secret' => 'test-secret-123',
    ]);

    $response->assertRedirect('/team-settings?tab=webhooks');

    $this->assertDatabaseHas('webhooks', [
        'team_id' => $team->id,
        'name' => 'Test Webhook',
        'url' => 'https://example.com/webhook',
        'event' => 'NEW_POSTS',
        'is_active' => true,
        'secret' => 'test-secret-123',
    ]);
});

test('webhook creation validates required fields', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/webhooks', []);

    $response->assertSessionHasErrors(['name', 'url', 'event']);
});

test('webhook creation validates url format', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/webhooks', [
        'name' => 'Test',
        'url' => 'not-a-valid-url',
        'event' => 'NEW_POSTS',
    ]);

    $response->assertSessionHasErrors(['url']);
});

test('webhook creation validates event type', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/webhooks', [
        'name' => 'Test',
        'url' => 'https://example.com/webhook',
        'event' => 'INVALID_EVENT',
    ]);

    $response->assertSessionHasErrors(['event']);
});

test('authenticated users can edit their own team webhooks', function () {
    [$user, $team] = createUserWithTeam();
    $webhook = Webhook::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->get("/webhooks/{$webhook->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Webhooks/Edit')
            ->where('webhook.id', $webhook->id)
        );
});

test('users cannot edit webhooks from other teams', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $otherWebhook = Webhook::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->get("/webhooks/{$otherWebhook->id}/edit")
        ->assertForbidden();
});

test('authenticated users can update their webhooks', function () {
    [$user, $team] = createUserWithTeam();
    $webhook = Webhook::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->put("/webhooks/{$webhook->id}", [
        'name' => 'Updated Webhook',
        'url' => 'https://updated.com/webhook',
        'event' => 'HIGH_RELEVANCY_POST',
        'is_active' => false,
        'secret' => 'new-secret',
    ]);

    $response->assertRedirect('/team-settings?tab=webhooks');

    $webhook->refresh();
    expect($webhook->name)->toBe('Updated Webhook');
    expect($webhook->url)->toBe('https://updated.com/webhook');
    expect($webhook->event)->toBe('HIGH_RELEVANCY_POST');
    expect($webhook->is_active)->toBeFalse();
});

test('users cannot update webhooks from other teams', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $otherWebhook = Webhook::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->put("/webhooks/{$otherWebhook->id}", [
            'name' => 'Updated',
            'url' => 'https://example.com/webhook',
            'event' => 'NEW_POSTS',
        ])
        ->assertForbidden();
});

test('authenticated users can delete their webhooks', function () {
    [$user, $team] = createUserWithTeam();
    $webhook = Webhook::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->delete("/webhooks/{$webhook->id}");

    $response->assertRedirect('/team-settings?tab=webhooks');
    $this->assertDatabaseMissing('webhooks', ['id' => $webhook->id]);
});

test('users cannot delete webhooks from other teams', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $otherWebhook = Webhook::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->delete("/webhooks/{$otherWebhook->id}")
        ->assertForbidden();
});

test('authenticated users can test their webhooks', function () {
    Queue::fake();

    [$user, $team] = createUserWithTeam();
    $webhook = Webhook::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post("/webhooks/{$webhook->id}/test");

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Test webhook has been queued.');

    Queue::assertPushed(SendWebhookNotification::class, function ($job) use ($webhook) {
        return $job->webhook->id === $webhook->id;
    });
});

test('users cannot test webhooks from other teams', function () {
    Queue::fake();

    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $otherWebhook = Webhook::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->post("/webhooks/{$otherWebhook->id}/test")
        ->assertForbidden();

    Queue::assertNotPushed(SendWebhookNotification::class);
});

test('webhook notification includes posts array with title and url', function () {
    Queue::fake();

    [$user, $team] = createUserWithTeam();

    // Create a source with notifications enabled
    $source = \App\Models\Source::factory()->create([
        'team_id' => $team->id,
        'should_notify' => true,
        'is_active' => true,
        'type' => 'RSS',
        'url' => 'https://example.com/feed.xml',
    ]);

    // Create a webhook for NEW_POSTS event
    $webhook = Webhook::factory()->create([
        'team_id' => $team->id,
        'event' => 'NEW_POSTS',
        'is_active' => true,
    ]);

    // Mock the parser to return some items
    $parser = Mockery::mock(\App\Services\SourceParser::class);
    $parser->shouldReceive('parse')
        ->andReturn([
            [
                'uri' => 'https://example.com/post1',
                'title' => 'First Post Title',
            ],
            [
                'uri' => 'https://example.com/post2',
                'title' => 'Second Post Title',
            ],
        ]);

    $keywordFilter = Mockery::mock(\App\Services\KeywordFilterService::class);
    $keywordFilter->shouldReceive('filterSourceItems')
        ->andReturnUsing(fn ($items) => $items);

    // Dispatch the MonitorSource job
    $tokenLimit = Mockery::mock(\App\Services\TokenLimitService::class);
    $tokenLimit->shouldReceive('assertWithinLimit')->andReturnTrue();

    $job = new \App\Jobs\MonitorSource($source);
    $job->handle($parser, $keywordFilter, $tokenLimit);

    // Assert webhook notification was dispatched
    Queue::assertPushed(SendWebhookNotification::class, function ($job) use ($webhook) {
        $payload = $job->payload;

        // Verify event and basic structure
        expect($payload['event'])->toBe('NEW_POSTS');
        expect($payload['data']['new_posts_count'])->toBe(2);

        // Verify posts array exists and has correct structure
        expect($payload['data'])->toHaveKey('posts');
        expect($payload['data']['posts'])->toBeArray();
        expect($payload['data']['posts'])->toHaveCount(2);

        // Verify first post
        expect($payload['data']['posts'][0])->toHaveKey('title');
        expect($payload['data']['posts'][0])->toHaveKey('url');
        expect($payload['data']['posts'][0]['title'])->toBe('First Post Title');
        expect($payload['data']['posts'][0]['url'])->toBe('https://example.com/post1');

        // Verify second post
        expect($payload['data']['posts'][1]['title'])->toBe('Second Post Title');
        expect($payload['data']['posts'][1]['url'])->toBe('https://example.com/post2');

        return $job->webhook->id === $webhook->id;
    });
});
