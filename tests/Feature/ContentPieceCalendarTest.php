<?php

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
        'prompt_id' => $prompt->id,
        'briefing_text' => 'Some briefing text',
        'channel' => 'BLOG_POST',
        'target_language' => 'ENGLISH',
        'published_at' => $publishAt->toDateTimeString(),
    ]);

    /** @var ContentPiece $contentPiece */
    $contentPiece = ContentPiece::where('internal_name', 'Scheduled piece')->first();

    $response->assertRedirect("/content-pieces/{$contentPiece->id}/edit");
    expect($contentPiece->published_at?->toDateTimeString())->toBe($publishAt->toDateTimeString());

    $this->actingAs($user)->put("/content-pieces/{$contentPiece->id}", [
        'internal_name' => 'Scheduled piece',
        'prompt_id' => $prompt->id,
        'briefing_text' => 'Some briefing text',
        'channel' => 'BLOG_POST',
        'target_language' => 'ENGLISH',
        'published_at' => null,
    ])->assertRedirect("/content-pieces/{$contentPiece->id}/edit");

    $contentPiece->refresh();
    expect($contentPiece->published_at)->toBeNull();
});

it('rejects past publish dates', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/content-pieces', [
        'internal_name' => 'Too soon',
        'channel' => 'BLOG_POST',
        'target_language' => 'ENGLISH',
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

it('returns only in-range scheduled items for the calendar endpoint and scopes to the team', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);
    $anchorDate = Carbon::parse('2025-02-03'); // Monday

    $inRange = ContentPiece::factory()->for($team)->create([
        'prompt_id' => $prompt->id,
        'published_at' => $anchorDate->copy()->addDays(2),
    ]);

    $outOfRange = ContentPiece::factory()->for($team)->create([
        'prompt_id' => $prompt->id,
        'published_at' => $anchorDate->copy()->addWeeks(2),
    ]);

    ContentPiece::factory()->for($otherTeam)->create([
        'prompt_id' => Prompt::factory()->create(['team_id' => $otherTeam->id])->id,
        'published_at' => $anchorDate->copy()->addDays(1),
    ]);

    $response = $this->actingAs($user)->get('/content-pieces/calendar?view=week&date='.$anchorDate->toDateString());

    $response->assertOk();
    $response->assertJson(fn (AssertableJson $json) => $json
        ->has('events.'.$inRange->published_at->toDateString())
        ->missing('events.'.$outOfRange->published_at->toDateString())
        ->etc()
    );
});

it('includes published_at fields in the inertia payload', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);
    $scheduled = ContentPiece::factory()->for($team)->create([
        'prompt_id' => $prompt->id,
        'published_at' => Carbon::now()->addDay(),
    ]);

    $response = $this->actingAs($user)->get('/content-pieces');

    $response->assertInertia(fn ($page) => $page
        ->where('contentPieces.data.0.id', $scheduled->id)
        ->where('contentPieces.data.0.published_at', $scheduled->published_at?->toIso8601String())
        ->where('contentPieces.data.0.published_at_human', $scheduled->published_at?->diffForHumans())
    );
});
