<?php

use App\Jobs\PublishContentToLinkedIn;
use App\Models\ContentPiece;
use App\Models\SocialIntegration;
use Illuminate\Support\Facades\Queue;

test('user can publish content piece immediately', function () {
    [$user, $team] = createUserWithTeam();
    Queue::fake();

    $integration = SocialIntegration::factory()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
    ]);

    $contentPiece = ContentPiece::factory()->create([
        'team_id' => $team->id,
    ]);

    $response = $this->actingAs($user)->post("/content-pieces/{$contentPiece->id}/publish", [
        'integration_id' => $integration->id,
    ]);

    $response->assertRedirect();
    $contentPiece->refresh();

    expect($contentPiece->publish_status)->toBe('publishing')
        ->and($contentPiece->publish_to_platforms['linkedin'])->toBe($integration->id);

    Queue::assertPushed(PublishContentToLinkedIn::class);
});

test('user can schedule publishing for later', function () {
    [$user, $team] = createUserWithTeam();

    $integration = SocialIntegration::factory()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
    ]);

    $contentPiece = ContentPiece::factory()->create([
        'team_id' => $team->id,
    ]);

    $scheduleAt = now()->addHour()->toIso8601String();

    $response = $this->actingAs($user)->post("/content-pieces/{$contentPiece->id}/publish", [
        'integration_id' => $integration->id,
        'schedule_at' => $scheduleAt,
    ]);

    $response->assertRedirect();

    $contentPiece->refresh();

    expect($contentPiece->publish_status)->toBe('scheduled')
        ->and($contentPiece->scheduled_publish_at?->toIso8601String())->toBe($scheduleAt)
        ->and($contentPiece->publish_to_platforms['linkedin'])->toBe($integration->id);
});

test('scheduled publishing job dispatches when due', function () {
    [$user, $team] = createUserWithTeam();
    Queue::fake();

    $integration = SocialIntegration::factory()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
    ]);

    $contentPiece = ContentPiece::factory()->create([
        'team_id' => $team->id,
        'publish_status' => 'scheduled',
        'publish_to_platforms' => ['linkedin' => $integration->id],
        'scheduled_publish_at' => now()->subMinute(),
    ]);

    $job = new \App\Jobs\ProcessScheduledPublishing;
    $job->handle();

    Queue::assertPushed(PublishContentToLinkedIn::class, function (PublishContentToLinkedIn $dispatched) use ($contentPiece, $integration) {
        return $dispatched->contentPiece->is($contentPiece) && $dispatched->integration->is($integration);
    });
});
