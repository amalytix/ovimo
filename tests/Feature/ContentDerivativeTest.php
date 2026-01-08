<?php

use App\Models\Channel;
use App\Models\ContentDerivative;
use App\Models\ContentPiece;
use App\Models\Media;
use App\Models\Prompt;

test('guests cannot access content derivatives', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $channel = Channel::factory()->create(['team_id' => $team->id]);

    $this->post("/content-pieces/{$contentPiece->id}/derivatives", [
        'channel_id' => $channel->id,
    ])->assertRedirect('/login');
});

test('authenticated users can create a content derivative', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $channel = Channel::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post("/content-pieces/{$contentPiece->id}/derivatives", [
        'channel_id' => $channel->id,
        'title' => 'Test Derivative',
        'text' => 'Some content text',
        'status' => 'DRAFT',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('content_derivatives', [
        'content_piece_id' => $contentPiece->id,
        'channel_id' => $channel->id,
        'title' => 'Test Derivative',
        'status' => 'DRAFT',
    ]);
});

test('content derivative requires channel_id', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post("/content-pieces/{$contentPiece->id}/derivatives", [
        'title' => 'Test Derivative',
        'status' => 'DRAFT',
    ]);

    $response->assertSessionHasErrors(['channel_id']);
});

test('cannot create duplicate derivative for same content piece and channel', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $channel = Channel::factory()->create(['team_id' => $team->id]);

    ContentDerivative::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'channel_id' => $channel->id,
    ]);

    $response = $this->actingAs($user)->post("/content-pieces/{$contentPiece->id}/derivatives", [
        'channel_id' => $channel->id,
        'title' => 'Another Derivative',
        'status' => 'DRAFT',
    ]);

    $response->assertSessionHasErrors(['channel_id']);
});

test('can update a content derivative', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $channel = Channel::factory()->create(['team_id' => $team->id]);
    $derivative = ContentDerivative::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'channel_id' => $channel->id,
    ]);

    $response = $this->actingAs($user)->put("/content-pieces/{$contentPiece->id}/derivatives/{$derivative->id}", [
        'title' => 'Updated Title',
        'text' => 'Updated text content',
        'status' => 'FINAL',
    ]);

    $response->assertRedirect();

    $derivative->refresh();
    expect($derivative->title)->toBe('Updated Title');
    expect($derivative->text)->toBe('Updated text content');
    expect($derivative->status)->toBe('FINAL');
});

test('can delete a content derivative', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $channel = Channel::factory()->create(['team_id' => $team->id]);
    $derivative = ContentDerivative::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'channel_id' => $channel->id,
    ]);

    $response = $this->actingAs($user)->delete("/content-pieces/{$contentPiece->id}/derivatives/{$derivative->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('content_derivatives', ['id' => $derivative->id]);
});

test('cannot create derivative on another team content piece', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $otherContentPiece = ContentPiece::factory()->create(['team_id' => $otherTeam->id]);
    // Use user's own channel for the request, content piece authorization should fail
    $channel = Channel::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)->post("/content-pieces/{$otherContentPiece->id}/derivatives", [
        'channel_id' => $channel->id,
        'title' => 'Test',
        'status' => 'DRAFT',
    ])->assertForbidden();
});

test('content piece edit page includes derivatives', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $channel = Channel::factory()->create(['team_id' => $team->id]);
    $derivative = ContentDerivative::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'channel_id' => $channel->id,
    ]);

    $response = $this->actingAs($user)->get("/content-pieces/{$contentPiece->id}/edit");

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('ContentPieces/Edit')
            ->has('derivatives', 1)
            ->where('derivatives.0.id', $derivative->id)
        );
});

test('content derivative status can be set to NOT_PLANNED', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $channel = Channel::factory()->create(['team_id' => $team->id]);
    $derivative = ContentDerivative::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'channel_id' => $channel->id,
        'status' => 'DRAFT',
    ]);

    $response = $this->actingAs($user)->put("/content-pieces/{$contentPiece->id}/derivatives/{$derivative->id}", [
        'status' => 'NOT_PLANNED',
    ]);

    $response->assertRedirect();

    $derivative->refresh();
    expect($derivative->status)->toBe('NOT_PLANNED');
});

test('derivative can have a prompt assigned', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $channel = Channel::factory()->create(['team_id' => $team->id]);
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post("/content-pieces/{$contentPiece->id}/derivatives", [
        'channel_id' => $channel->id,
        'prompt_id' => $prompt->id,
        'status' => 'NOT_STARTED',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('content_derivatives', [
        'content_piece_id' => $contentPiece->id,
        'prompt_id' => $prompt->id,
    ]);
});

test('can update derivative with media attachments', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $channel = Channel::factory()->create(['team_id' => $team->id]);
    $derivative = ContentDerivative::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'channel_id' => $channel->id,
    ]);
    $media1 = Media::factory()->create(['team_id' => $team->id]);
    $media2 = Media::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->put("/content-pieces/{$contentPiece->id}/derivatives/{$derivative->id}", [
        'title' => 'Updated Title',
        'status' => 'DRAFT',
        'media_ids' => [$media1->id, $media2->id],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('content_derivative_media', [
        'content_derivative_id' => $derivative->id,
        'media_id' => $media1->id,
    ]);
    $this->assertDatabaseHas('content_derivative_media', [
        'content_derivative_id' => $derivative->id,
        'media_id' => $media2->id,
    ]);
});

test('can remove all media from derivative by sending empty array', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $channel = Channel::factory()->create(['team_id' => $team->id]);
    $derivative = ContentDerivative::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'channel_id' => $channel->id,
    ]);
    $media = Media::factory()->create(['team_id' => $team->id]);

    // First attach media
    $derivative->media()->attach($media->id);
    $this->assertDatabaseHas('content_derivative_media', [
        'content_derivative_id' => $derivative->id,
        'media_id' => $media->id,
    ]);

    // Then remove by sending empty array
    $response = $this->actingAs($user)->put("/content-pieces/{$contentPiece->id}/derivatives/{$derivative->id}", [
        'title' => 'Updated Title',
        'status' => 'DRAFT',
        'media_ids' => [],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseMissing('content_derivative_media', [
        'content_derivative_id' => $derivative->id,
        'media_id' => $media->id,
    ]);
});

test('derivative media is included in edit page response', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $channel = Channel::factory()->create(['team_id' => $team->id]);
    $derivative = ContentDerivative::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'channel_id' => $channel->id,
    ]);
    $media = Media::factory()->create(['team_id' => $team->id]);
    $derivative->media()->attach($media->id);

    $response = $this->actingAs($user)->get("/content-pieces/{$contentPiece->id}/edit");

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('ContentPieces/Edit')
            ->has('derivatives', 1)
            ->where('derivatives.0.id', $derivative->id)
            ->has('derivatives.0.media', 1)
            ->where('derivatives.0.media.0.id', $media->id)
        );
});
