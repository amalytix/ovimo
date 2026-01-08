<?php

use App\Events\DerivativeStatusChanged;
use App\Models\ActivityLog;
use App\Models\Channel;
use App\Models\ContentDerivative;
use App\Models\ContentPiece;
use Illuminate\Support\Facades\Event;

test('can fetch activities for a derivative', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $channel = Channel::factory()->create(['team_id' => $team->id]);
    $derivative = ContentDerivative::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'channel_id' => $channel->id,
    ]);

    // Create some activities
    ActivityLog::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'content_derivative_id' => $derivative->id,
        'event_type' => 'derivative.generated',
        'level' => 'info',
        'description' => 'Content generated',
    ]);

    ActivityLog::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'content_derivative_id' => $derivative->id,
        'event_type' => 'derivative.comment',
        'level' => 'info',
        'description' => 'Test comment',
    ]);

    $response = $this->actingAs($user)->getJson("/content-pieces/{$contentPiece->id}/derivatives/{$derivative->id}/activities");

    $response->assertSuccessful()
        ->assertJsonCount(2, 'activities')
        ->assertJsonPath('activities.0.event_type', 'derivative.comment')
        ->assertJsonPath('activities.1.event_type', 'derivative.generated');
});

test('can add a comment to a derivative', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $channel = Channel::factory()->create(['team_id' => $team->id]);
    $derivative = ContentDerivative::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'channel_id' => $channel->id,
    ]);

    $response = $this->actingAs($user)->postJson("/content-pieces/{$contentPiece->id}/derivatives/{$derivative->id}/activities", [
        'comment' => 'This is a test comment',
    ]);

    $response->assertCreated()
        ->assertJsonPath('activity.event_type', 'derivative.comment')
        ->assertJsonPath('activity.description', 'This is a test comment')
        ->assertJsonPath('activity.is_comment', true)
        ->assertJsonPath('activity.user.name', $user->name);

    $this->assertDatabaseHas('activity_logs', [
        'team_id' => $team->id,
        'user_id' => $user->id,
        'content_derivative_id' => $derivative->id,
        'event_type' => 'derivative.comment',
        'description' => 'This is a test comment',
    ]);
});

test('comment is required when adding activity', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $channel = Channel::factory()->create(['team_id' => $team->id]);
    $derivative = ContentDerivative::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'channel_id' => $channel->id,
    ]);

    $response = $this->actingAs($user)->postJson("/content-pieces/{$contentPiece->id}/derivatives/{$derivative->id}/activities", [
        'comment' => '',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['comment']);
});

test('cannot access activities of another teams derivative', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $otherContentPiece = ContentPiece::factory()->create(['team_id' => $otherTeam->id]);
    $otherChannel = Channel::factory()->create(['team_id' => $otherTeam->id]);
    $otherDerivative = ContentDerivative::factory()->create([
        'content_piece_id' => $otherContentPiece->id,
        'channel_id' => $otherChannel->id,
    ]);

    $this->actingAs($user)->getJson("/content-pieces/{$otherContentPiece->id}/derivatives/{$otherDerivative->id}/activities")
        ->assertForbidden();
});

test('derivative status change dispatches event', function () {
    Event::fake([DerivativeStatusChanged::class]);

    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $channel = Channel::factory()->create(['team_id' => $team->id]);
    $derivative = ContentDerivative::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'channel_id' => $channel->id,
        'status' => 'DRAFT',
    ]);

    $this->actingAs($user)->put("/content-pieces/{$contentPiece->id}/derivatives/{$derivative->id}", [
        'status' => 'FINAL',
    ]);

    Event::assertDispatched(DerivativeStatusChanged::class, function ($event) use ($derivative) {
        return $event->derivative->id === $derivative->id
            && $event->oldStatus === 'DRAFT'
            && $event->derivative->status === 'FINAL';
    });
});

test('derivative status change does not dispatch event when status unchanged', function () {
    Event::fake([DerivativeStatusChanged::class]);

    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $channel = Channel::factory()->create(['team_id' => $team->id]);
    $derivative = ContentDerivative::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'channel_id' => $channel->id,
        'status' => 'DRAFT',
    ]);

    $this->actingAs($user)->put("/content-pieces/{$contentPiece->id}/derivatives/{$derivative->id}", [
        'title' => 'Updated title',
        'status' => 'DRAFT', // Same status
    ]);

    Event::assertNotDispatched(DerivativeStatusChanged::class);
});

test('global derivative activities page is accessible', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->get('/derivative-activities');

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('DerivativeActivities/Index')
            ->has('logs')
            ->has('eventTypes')
        );
});

test('global derivative activities page only shows derivative activities', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $channel = Channel::factory()->create(['team_id' => $team->id]);
    $derivative = ContentDerivative::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'channel_id' => $channel->id,
    ]);

    // Create a derivative activity
    ActivityLog::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'content_derivative_id' => $derivative->id,
        'event_type' => 'derivative.comment',
        'level' => 'info',
        'description' => 'Test comment',
    ]);

    // Create a non-derivative activity
    ActivityLog::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'event_type' => 'user.login',
        'level' => 'info',
        'description' => 'User logged in',
    ]);

    $response = $this->actingAs($user)->get('/derivative-activities');

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('DerivativeActivities/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.event_type', 'derivative.comment')
        );
});

test('derivative activities relationship works correctly', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $channel = Channel::factory()->create(['team_id' => $team->id]);
    $derivative = ContentDerivative::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'channel_id' => $channel->id,
    ]);

    ActivityLog::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'content_derivative_id' => $derivative->id,
        'event_type' => 'derivative.generated',
        'level' => 'info',
        'description' => 'Content generated',
    ]);

    $derivative->refresh();

    expect($derivative->activities)->toHaveCount(1);
    expect($derivative->activities->first()->event_type)->toBe('derivative.generated');
});
