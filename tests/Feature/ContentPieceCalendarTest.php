<?php

use App\Models\Channel;
use App\Models\ContentDerivative;
use App\Models\ContentPiece;
use App\Models\Prompt;
use Carbon\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;

it('allows scheduling on create and update', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);
    $publishAt = Carbon::now()->addDay()->setSecond(0);

    $response = $this->actingAs($user)->post('/content-pieces', [
        'internal_name' => 'Scheduled piece',
        'published_at' => $publishAt->toDateTimeString(),
    ]);

    /** @var ContentPiece $contentPiece */
    $contentPiece = ContentPiece::where('internal_name', 'Scheduled piece')->first();

    $response->assertRedirect("/content-pieces/{$contentPiece->id}/edit?tab=derivatives");
    expect($contentPiece->published_at?->toDateTimeString())->toBe($publishAt->toDateTimeString());

    $this->actingAs($user)->put("/content-pieces/{$contentPiece->id}", [
        'internal_name' => 'Scheduled piece',
        'prompt_id' => $prompt->id,
        'briefing_text' => 'Some briefing text',
        'channel' => 'BLOG_POST',
        'published_at' => null,
    ])->assertRedirect("/content-pieces/{$contentPiece->id}/edit");

    $contentPiece->refresh();
    expect($contentPiece->published_at)->toBeNull();
});

it('rejects past publish dates', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/content-pieces', [
        'internal_name' => 'Too soon',
        'published_at' => Carbon::now()->subDay()->toDateTimeString(),
    ]);

    $response->assertSessionHasErrors([
        'published_at' => 'Publish date cannot be in the past.',
    ]);
});

it('orders index with unscheduled first then upcoming publish date', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);
    Carbon::setTestNow('2025-01-01 09:00:00');

    $unscheduled = ContentPiece::factory()->for($team)->create([
        'prompt_id' => $prompt->id,
        'published_at' => null,
    ]);

    $scheduledSoon = ContentPiece::factory()->for($team)->scheduled()->create([
        'prompt_id' => $prompt->id,
        'published_at' => Carbon::now()->addDays(1),
    ]);

    $scheduledLater = ContentPiece::factory()->for($team)->scheduled()->create([
        'prompt_id' => $prompt->id,
        'published_at' => Carbon::now()->addDays(5),
    ]);

    $response = $this->actingAs($user)->get('/content-pieces');

    $response->assertInertia(fn ($page) => $page
        ->where('contentPieces.data.0.id', $unscheduled->id)
        ->where('contentPieces.data.1.id', $scheduledSoon->id)
        ->where('contentPieces.data.2.id', $scheduledLater->id)
    );

    Carbon::setTestNow();
});

it('returns only in-range scheduled derivatives for the calendar endpoint and scopes to the team', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $anchorDate = Carbon::parse('2025-02-03'); // Monday

    $channel = Channel::factory()->for($team)->create();
    $contentPiece = ContentPiece::factory()->for($team)->create();

    $inRangeDerivative = ContentDerivative::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'channel_id' => $channel->id,
        'planned_publish_at' => $anchorDate->copy()->addDays(2),
        'status' => ContentDerivative::STATUS_DRAFT,
    ]);

    $outOfRangeDerivative = ContentDerivative::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'channel_id' => Channel::factory()->for($team)->create()->id,
        'planned_publish_at' => $anchorDate->copy()->addWeeks(2),
    ]);

    // Create derivative for another team - should not appear
    $otherChannel = Channel::factory()->for($otherTeam)->create();
    $otherContentPiece = ContentPiece::factory()->for($otherTeam)->create();
    ContentDerivative::factory()->create([
        'content_piece_id' => $otherContentPiece->id,
        'channel_id' => $otherChannel->id,
        'planned_publish_at' => $anchorDate->copy()->addDays(1),
    ]);

    $response = $this->actingAs($user)->get('/content-pieces/calendar?view=week&date='.$anchorDate->toDateString());

    $response->assertOk();
    $response->assertJson(fn (AssertableJson $json) => $json
        ->has('events.'.$inRangeDerivative->planned_publish_at->toDateString())
        ->missing('events.'.$outOfRangeDerivative->planned_publish_at->toDateString())
        ->etc()
    );
});

it('returns derivative data with channel and status in calendar response', function () {
    [$user, $team] = createUserWithTeam();
    $anchorDate = Carbon::parse('2025-02-03');

    $channel = Channel::factory()->for($team)->create([
        'name' => 'Test Channel',
        'icon' => 'blog',
        'color' => '#ff0000',
    ]);
    $contentPiece = ContentPiece::factory()->for($team)->create([
        'internal_name' => 'Parent Content Piece',
    ]);

    $derivative = ContentDerivative::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'channel_id' => $channel->id,
        'title' => 'Derivative Title',
        'planned_publish_at' => $anchorDate->copy()->addDays(2),
        'status' => ContentDerivative::STATUS_FINAL,
    ]);

    $response = $this->actingAs($user)->get('/content-pieces/calendar?view=week&date='.$anchorDate->toDateString());

    $response->assertOk();
    $eventDate = $derivative->planned_publish_at->toDateString();
    $response->assertJson(fn (AssertableJson $json) => $json
        ->has("events.{$eventDate}", 1)
        ->where("events.{$eventDate}.0.id", $derivative->id)
        ->where("events.{$eventDate}.0.title", 'Derivative Title')
        ->where("events.{$eventDate}.0.content_piece_id", $contentPiece->id)
        ->where("events.{$eventDate}.0.channel_name", 'Test Channel')
        ->where("events.{$eventDate}.0.channel_icon", 'blog')
        ->where("events.{$eventDate}.0.channel_color", '#ff0000')
        ->where("events.{$eventDate}.0.status", ContentDerivative::STATUS_FINAL)
        ->etc()
    );
});

it('falls back to content piece title when derivative has no title', function () {
    [$user, $team] = createUserWithTeam();
    $anchorDate = Carbon::parse('2025-02-03');

    $channel = Channel::factory()->for($team)->create();
    $contentPiece = ContentPiece::factory()->for($team)->create([
        'internal_name' => 'Fallback Title',
    ]);

    $derivative = ContentDerivative::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'channel_id' => $channel->id,
        'title' => null,
        'planned_publish_at' => $anchorDate->copy()->addDays(2),
    ]);

    $response = $this->actingAs($user)->get('/content-pieces/calendar?view=week&date='.$anchorDate->toDateString());

    $response->assertOk();
    $eventDate = $derivative->planned_publish_at->toDateString();
    $response->assertJson(fn (AssertableJson $json) => $json
        ->where("events.{$eventDate}.0.title", 'Fallback Title')
        ->etc()
    );
});

// published_at fields are no longer included in the content-pieces index response
// as status is now managed at the derivative level

it('sorts by published_at with scheduled items first when sorting by publish date', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);
    Carbon::setTestNow('2025-01-01 09:00:00');

    $unscheduled = ContentPiece::factory()->for($team)->create([
        'prompt_id' => $prompt->id,
        'published_at' => null,
    ]);

    $scheduledSoon = ContentPiece::factory()->for($team)->scheduled()->create([
        'prompt_id' => $prompt->id,
        'published_at' => Carbon::now()->addDays(1),
    ]);

    $scheduledLater = ContentPiece::factory()->for($team)->scheduled()->create([
        'prompt_id' => $prompt->id,
        'published_at' => Carbon::now()->addDays(5),
    ]);

    $response = $this->actingAs($user)->get('/content-pieces?sort_by=published_at&sort_direction=asc');

    $response->assertInertia(fn ($page) => $page
        ->where('contentPieces.data.0.id', $scheduledSoon->id)
        ->where('contentPieces.data.1.id', $scheduledLater->id)
        ->where('contentPieces.data.2.id', $unscheduled->id)
    );

    Carbon::setTestNow();
});
