<?php

use App\Jobs\MonitorSource;
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
