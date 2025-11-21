<?php

use App\Jobs\MonitorSource;
use App\Models\Post;
use App\Models\Source;
use Illuminate\Support\Facades\Queue;

test('guests cannot access sources', function () {
    $this->get('/sources')->assertRedirect('/login');
});

test('authenticated users can view sources index', function () {
    [$user, $team] = createUserWithTeam();

    $this->actingAs($user)
        ->get('/sources')
        ->assertSuccessful();
});

test('sources index only shows team sources', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

    $teamSource = Source::factory()->create(['team_id' => $team->id]);
    $otherSource = Source::factory()->create(['team_id' => $otherTeam->id]);

    $response = $this->actingAs($user)->get('/sources');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Sources/Index')
        ->has('sources.data', 1)
        ->where('sources.data.0.id', $teamSource->id)
    );
});

test('sources index includes posts created in last 7 days', function () {
    [$user, $team] = createUserWithTeam();
    $source = Source::factory()->create(['team_id' => $team->id]);

    Post::factory()->for($source)->create(['created_at' => now()->subDays(2)]);
    Post::factory()->for($source)->create(['created_at' => now()->subDays(6)]);
    Post::factory()->for($source)->create(['created_at' => now()->subDays(10)]);

    $response = $this->actingAs($user)->get('/sources');

    $response->assertInertia(fn ($page) => $page
        ->component('Sources/Index')
        ->where('sources.data.0.posts_count', 3)
        ->where('sources.data.0.posts_last_7_days_count', 2)
    );
});

test('authenticated users can view create source form', function () {
    [$user, $team] = createUserWithTeam();

    $this->actingAs($user)
        ->get('/sources/create')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Sources/Create'));
});

test('authenticated users can create a source', function () {
    Queue::fake();

    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/sources', [
        'internal_name' => 'Test Source',
        'type' => 'RSS',
        'url' => 'https://example.com/feed.xml',
        'monitoring_interval' => 'HOURLY',
        'is_active' => true,
        'should_notify' => true,
        'auto_summarize' => false,
    ]);

    $response->assertRedirect('/sources');

    $this->assertDatabaseHas('sources', [
        'team_id' => $team->id,
        'internal_name' => 'Test Source',
        'type' => 'RSS',
        'url' => 'https://example.com/feed.xml',
        'monitoring_interval' => 'HOURLY',
    ]);

    // Verify that MonitorSource job was dispatched for active source
    Queue::assertPushed(MonitorSource::class);
});

test('creating inactive source does not dispatch check job', function () {
    Queue::fake();

    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/sources', [
        'internal_name' => 'Inactive Source',
        'type' => 'RSS',
        'url' => 'https://example.com/feed.xml',
        'monitoring_interval' => 'HOURLY',
        'is_active' => false,
        'should_notify' => true,
        'auto_summarize' => false,
    ]);

    $response->assertRedirect('/sources');

    // Verify that MonitorSource job was NOT dispatched for inactive source
    Queue::assertNotPushed(MonitorSource::class);
});

test('source creation validates required fields', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/sources', []);

    $response->assertSessionHasErrors(['internal_name', 'type', 'url', 'monitoring_interval']);
});

test('source creation validates url format', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/sources', [
        'internal_name' => 'Test Source',
        'type' => 'RSS',
        'url' => 'not-a-valid-url',
        'monitoring_interval' => 'HOURLY',
        'is_active' => true,
        'should_notify' => true,
        'auto_summarize' => false,
    ]);

    $response->assertSessionHasErrors(['url']);
});

test('authenticated users can view edit source form', function () {
    [$user, $team] = createUserWithTeam();
    $source = Source::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->get("/sources/{$source->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Sources/Edit')
            ->where('source.id', $source->id)
        );
});

test('users cannot view edit form for other teams sources', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

    $otherSource = Source::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->get("/sources/{$otherSource->id}/edit")
        ->assertForbidden();
});

test('authenticated users can update a source', function () {
    [$user, $team] = createUserWithTeam();
    $source = Source::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->put("/sources/{$source->id}", [
        'internal_name' => 'Updated Source',
        'type' => 'XML_SITEMAP',
        'url' => 'https://updated.com/sitemap.xml',
        'monitoring_interval' => 'DAILY',
        'is_active' => false,
        'should_notify' => false,
        'auto_summarize' => true,
    ]);

    $response->assertRedirect('/sources');

    $this->assertDatabaseHas('sources', [
        'id' => $source->id,
        'internal_name' => 'Updated Source',
        'type' => 'XML_SITEMAP',
        'url' => 'https://updated.com/sitemap.xml',
        'monitoring_interval' => 'DAILY',
        'is_active' => false,
    ]);
});

test('users cannot update other teams sources', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

    $otherSource = Source::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->put("/sources/{$otherSource->id}", [
            'internal_name' => 'Hacked',
            'type' => 'RSS',
            'url' => 'https://example.com',
            'monitoring_interval' => 'HOURLY',
            'is_active' => true,
            'should_notify' => true,
            'auto_summarize' => false,
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('sources', [
        'id' => $otherSource->id,
        'internal_name' => 'Hacked',
    ]);
});

test('authenticated users can delete a source', function () {
    [$user, $team] = createUserWithTeam();
    $source = Source::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->delete("/sources/{$source->id}");

    $response->assertRedirect('/sources');

    $this->assertSoftDeleted('sources', ['id' => $source->id]);
});

test('users cannot delete other teams sources', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

    $otherSource = Source::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->delete("/sources/{$otherSource->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('sources', ['id' => $otherSource->id]);
});

test('authenticated users can trigger source check', function () {
    Queue::fake();

    [$user, $team] = createUserWithTeam();
    $source = Source::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post("/sources/{$source->id}/check");

    $response->assertRedirect('/sources');

    Queue::assertPushed(MonitorSource::class, function ($job) use ($source) {
        return $job->source->id === $source->id;
    });
});

test('users cannot trigger check for other teams sources', function () {
    Queue::fake();

    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

    $otherSource = Source::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->post("/sources/{$otherSource->id}/check")
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('guests cannot trigger source check', function () {
    [$user, $team] = createUserWithTeam();
    $source = Source::factory()->create(['team_id' => $team->id]);

    $this->post("/sources/{$source->id}/check")
        ->assertRedirect('/login');
});

test('authenticated users can create source with bypass keyword filter', function () {
    Queue::fake();

    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/sources', [
        'internal_name' => 'Bypass Source',
        'type' => 'RSS',
        'url' => 'https://example.com/feed.xml',
        'monitoring_interval' => 'DAILY',
        'is_active' => true,
        'should_notify' => false,
        'auto_summarize' => false,
        'bypass_keyword_filter' => true,
    ]);

    $response->assertRedirect('/sources');

    $this->assertDatabaseHas('sources', [
        'team_id' => $team->id,
        'internal_name' => 'Bypass Source',
        'bypass_keyword_filter' => true,
    ]);
});

test('source bypass keyword filter defaults to false', function () {
    Queue::fake();

    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/sources', [
        'internal_name' => 'Default Source',
        'type' => 'RSS',
        'url' => 'https://example.com/feed.xml',
        'monitoring_interval' => 'DAILY',
        'is_active' => true,
        'should_notify' => false,
        'auto_summarize' => false,
    ]);

    $response->assertRedirect('/sources');

    $this->assertDatabaseHas('sources', [
        'team_id' => $team->id,
        'internal_name' => 'Default Source',
        'bypass_keyword_filter' => false,
    ]);
});

test('authenticated users can update source bypass keyword filter', function () {
    [$user, $team] = createUserWithTeam();
    $source = Source::factory()->create([
        'team_id' => $team->id,
        'bypass_keyword_filter' => false,
    ]);

    $response = $this->actingAs($user)->put("/sources/{$source->id}", [
        'internal_name' => $source->internal_name,
        'type' => $source->type,
        'url' => $source->url,
        'monitoring_interval' => $source->monitoring_interval,
        'is_active' => $source->is_active,
        'should_notify' => $source->should_notify,
        'auto_summarize' => $source->auto_summarize,
        'bypass_keyword_filter' => true,
    ]);

    $response->assertRedirect('/sources');

    $this->assertDatabaseHas('sources', [
        'id' => $source->id,
        'bypass_keyword_filter' => true,
    ]);
});

test('edit source form includes bypass keyword filter field', function () {
    [$user, $team] = createUserWithTeam();
    $source = Source::factory()->create([
        'team_id' => $team->id,
        'bypass_keyword_filter' => true,
    ]);

    $this->actingAs($user)
        ->get("/sources/{$source->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Sources/Edit')
            ->where('source.bypass_keyword_filter', true)
        );
});

test('changing monitoring interval recalculates next_check_at', function () {
    [$user, $team] = createUserWithTeam();

    // Create a source with DAILY interval and next_check_at set to tomorrow
    $source = Source::factory()->create([
        'team_id' => $team->id,
        'monitoring_interval' => 'DAILY',
        'next_check_at' => now()->addDay(),
    ]);

    $originalNextCheckAt = $source->next_check_at;

    // Change the interval to EVERY_30_MIN
    $this->actingAs($user)->put("/sources/{$source->id}", [
        'internal_name' => $source->internal_name,
        'type' => $source->type,
        'url' => $source->url,
        'monitoring_interval' => 'EVERY_30_MIN',
        'is_active' => $source->is_active,
        'should_notify' => $source->should_notify,
        'auto_summarize' => $source->auto_summarize,
    ]);

    $source->refresh();

    // next_check_at should now be approximately 30 minutes from now, not tomorrow
    expect($source->next_check_at)
        ->not->toEqual($originalNextCheckAt)
        ->toBeBetween(now()->addMinutes(29), now()->addMinutes(31));
});

test('not changing monitoring interval preserves next_check_at', function () {
    [$user, $team] = createUserWithTeam();

    // Create a source with specific next_check_at
    $nextCheckAt = now()->addHours(3);
    $source = Source::factory()->create([
        'team_id' => $team->id,
        'monitoring_interval' => 'HOURLY',
        'next_check_at' => $nextCheckAt,
    ]);

    // Update other fields but keep the same monitoring_interval
    $this->actingAs($user)->put("/sources/{$source->id}", [
        'internal_name' => 'Updated Name',
        'type' => $source->type,
        'url' => $source->url,
        'monitoring_interval' => 'HOURLY', // Same interval
        'is_active' => $source->is_active,
        'should_notify' => $source->should_notify,
        'auto_summarize' => $source->auto_summarize,
    ]);

    $source->refresh();

    // next_check_at should remain unchanged
    expect($source->next_check_at->timestamp)->toEqual($nextCheckAt->timestamp);
});

test('calculateNextCheckTime method works correctly', function () {
    $source = Source::factory()->make();

    // Test EVERY_10_MIN
    $source->monitoring_interval = 'EVERY_10_MIN';
    $nextCheck = $source->calculateNextCheckTime();
    expect($nextCheck)->toBeBetween(now()->addMinutes(9), now()->addMinutes(11));

    // Test EVERY_30_MIN
    $source->monitoring_interval = 'EVERY_30_MIN';
    $nextCheck = $source->calculateNextCheckTime();
    expect($nextCheck)->toBeBetween(now()->addMinutes(29), now()->addMinutes(31));

    // Test HOURLY
    $source->monitoring_interval = 'HOURLY';
    $nextCheck = $source->calculateNextCheckTime();
    expect($nextCheck)->toBeBetween(now()->addMinutes(59), now()->addMinutes(61));

    // Test EVERY_6_HOURS
    $source->monitoring_interval = 'EVERY_6_HOURS';
    $nextCheck = $source->calculateNextCheckTime();
    expect($nextCheck)->toBeBetween(now()->addHours(5)->addMinutes(59), now()->addHours(6)->addMinutes(1));

    // Test DAILY
    $source->monitoring_interval = 'DAILY';
    $nextCheck = $source->calculateNextCheckTime();
    expect($nextCheck)->toBeBetween(now()->addDay()->subMinute(), now()->addDay()->addMinute());

    // Test WEEKLY
    $source->monitoring_interval = 'WEEKLY';
    $nextCheck = $source->calculateNextCheckTime();
    expect($nextCheck)->toBeBetween(now()->addWeek()->subMinute(), now()->addWeek()->addMinute());
});
