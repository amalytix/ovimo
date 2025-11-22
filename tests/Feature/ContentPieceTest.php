<?php

use App\Models\ContentPiece;
use App\Models\Media;
use App\Models\Prompt;

test('guests cannot access content pieces', function () {
    $this->get('/content-pieces')->assertRedirect('/login');
});

test('authenticated users can view content pieces index', function () {
    [$user, $team] = createUserWithTeam();

    $this->actingAs($user)
        ->get('/content-pieces')
        ->assertSuccessful();
});

test('content pieces index only shows team content pieces', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

    $prompt = Prompt::factory()->create(['team_id' => $team->id]);
    $otherPrompt = Prompt::factory()->create(['team_id' => $otherTeam->id]);

    $teamPiece = ContentPiece::factory()->create([
        'team_id' => $team->id,
        'prompt_id' => $prompt->id,
    ]);
    $otherPiece = ContentPiece::factory()->create([
        'team_id' => $otherTeam->id,
        'prompt_id' => $otherPrompt->id,
    ]);

    $response = $this->actingAs($user)->get('/content-pieces');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('ContentPieces/Index')
        ->has('contentPieces.data', 1)
        ->where('contentPieces.data.0.id', $teamPiece->id)
    );
});

test('content pieces index can filter by status', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);

    ContentPiece::factory()->create([
        'team_id' => $team->id,
        'prompt_id' => $prompt->id,
        'status' => 'DRAFT',
    ]);
    ContentPiece::factory()->create([
        'team_id' => $team->id,
        'prompt_id' => $prompt->id,
        'status' => 'FINAL',
    ]);

    $response = $this->actingAs($user)->get('/content-pieces?status=DRAFT');

    $response->assertInertia(fn ($page) => $page
        ->has('contentPieces.data', 1)
        ->where('contentPieces.data.0.status', 'DRAFT')
    );
});

test('content pieces index can filter by channel', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);

    ContentPiece::factory()->create([
        'team_id' => $team->id,
        'prompt_id' => $prompt->id,
        'channel' => 'BLOG_POST',
    ]);
    ContentPiece::factory()->create([
        'team_id' => $team->id,
        'prompt_id' => $prompt->id,
        'channel' => 'LINKEDIN_POST',
    ]);

    $response = $this->actingAs($user)->get('/content-pieces?channel=BLOG_POST');

    $response->assertInertia(fn ($page) => $page
        ->has('contentPieces.data', 1)
        ->where('contentPieces.data.0.channel', 'BLOG_POST')
    );
});

test('content pieces index can search by name', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);

    ContentPiece::factory()->create([
        'team_id' => $team->id,
        'prompt_id' => $prompt->id,
        'internal_name' => 'Important Blog Post',
    ]);
    ContentPiece::factory()->create([
        'team_id' => $team->id,
        'prompt_id' => $prompt->id,
        'internal_name' => 'LinkedIn Update',
    ]);

    $response = $this->actingAs($user)->get('/content-pieces?search=Important');

    $response->assertInertia(fn ($page) => $page
        ->has('contentPieces.data', 1)
        ->where('contentPieces.data.0.internal_name', 'Important Blog Post')
    );
});

test('authenticated users can view create content piece form', function () {
    [$user, $team] = createUserWithTeam();

    $this->actingAs($user)
        ->get('/content-pieces/create')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('ContentPieces/Create'));
});

test('authenticated users can create a content piece', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);
    $media = Media::factory()->create([
        'team_id' => $team->id,
        'uploaded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post('/content-pieces', [
        'internal_name' => 'Test Content Piece',
        'prompt_id' => $prompt->id,
        'briefing_text' => 'Some briefing text',
        'channel' => 'BLOG_POST',
        'target_language' => 'ENGLISH',
        'media_ids' => [$media->id],
    ]);

    $contentPiece = ContentPiece::where('internal_name', 'Test Content Piece')->first();
    $response->assertRedirect("/content-pieces/{$contentPiece->id}/edit");

    $this->assertDatabaseHas('content_pieces', [
        'team_id' => $team->id,
        'internal_name' => 'Test Content Piece',
        'prompt_id' => $prompt->id,
        'channel' => 'BLOG_POST',
        'target_language' => 'ENGLISH',
        'status' => 'NOT_STARTED',
    ]);

    $this->assertDatabaseHas('content_piece_media', [
        'content_piece_id' => $contentPiece->id,
        'media_id' => $media->id,
    ]);
});

test('content piece creation validates required fields', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/content-pieces', []);

    $response->assertSessionHasErrors(['internal_name', 'channel', 'target_language']);
});

test('content piece creation validates channel is valid', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/content-pieces', [
        'internal_name' => 'Test',
        'channel' => 'INVALID_CHANNEL',
        'target_language' => 'ENGLISH',
    ]);

    $response->assertSessionHasErrors(['channel']);
});

test('content piece creation validates target language', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/content-pieces', [
        'internal_name' => 'Test',
        'channel' => 'BLOG_POST',
        'target_language' => 'SPANISH',
    ]);

    $response->assertSessionHasErrors(['target_language']);
});

test('authenticated users can edit their own team content pieces', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);
    $contentPiece = ContentPiece::factory()->create([
        'team_id' => $team->id,
        'prompt_id' => $prompt->id,
    ]);

    $this->actingAs($user)
        ->get("/content-pieces/{$contentPiece->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('ContentPieces/Edit')
            ->where('contentPiece.id', $contentPiece->id)
        );
});

test('users cannot edit content pieces from other teams', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $otherPrompt = Prompt::factory()->create(['team_id' => $otherTeam->id]);
    $otherPiece = ContentPiece::factory()->create([
        'team_id' => $otherTeam->id,
        'prompt_id' => $otherPrompt->id,
    ]);

    $this->actingAs($user)
        ->get("/content-pieces/{$otherPiece->id}/edit")
        ->assertForbidden();
});

test('authenticated users can update their content pieces', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);
    $contentPiece = ContentPiece::factory()->create([
        'team_id' => $team->id,
        'prompt_id' => $prompt->id,
    ]);
    $media = Media::factory()->create([
        'team_id' => $team->id,
        'uploaded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->put("/content-pieces/{$contentPiece->id}", [
        'internal_name' => 'Updated Content Name',
        'briefing_text' => 'Updated briefing',
        'channel' => 'LINKEDIN_POST',
        'target_language' => 'GERMAN',
        'media_ids' => [$media->id],
    ]);

    $response->assertRedirect("/content-pieces/{$contentPiece->id}/edit");

    $contentPiece->refresh();
    expect($contentPiece->internal_name)->toBe('Updated Content Name');
    expect($contentPiece->channel)->toBe('LINKEDIN_POST');
    expect($contentPiece->target_language)->toBe('GERMAN');

    $this->assertDatabaseHas('content_piece_media', [
        'content_piece_id' => $contentPiece->id,
        'media_id' => $media->id,
    ]);
});

test('users cannot update content pieces from other teams', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $otherPrompt = Prompt::factory()->create(['team_id' => $otherTeam->id]);
    $otherPiece = ContentPiece::factory()->create([
        'team_id' => $otherTeam->id,
        'prompt_id' => $otherPrompt->id,
    ]);

    $this->actingAs($user)
        ->put("/content-pieces/{$otherPiece->id}", [
            'internal_name' => 'Updated',
            'channel' => 'BLOG_POST',
            'target_language' => 'ENGLISH',
        ])
        ->assertForbidden();
});

test('authenticated users can update content piece status', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);
    $contentPiece = ContentPiece::factory()->create([
        'team_id' => $team->id,
        'prompt_id' => $prompt->id,
        'status' => 'DRAFT',
    ]);

    $response = $this->actingAs($user)->patch("/content-pieces/{$contentPiece->id}/status", [
        'status' => 'FINAL',
    ]);

    $response->assertRedirect();
    $contentPiece->refresh();
    expect($contentPiece->status)->toBe('FINAL');
});

test('content piece status update validates status value', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);
    $contentPiece = ContentPiece::factory()->create([
        'team_id' => $team->id,
        'prompt_id' => $prompt->id,
    ]);

    $response = $this->actingAs($user)->patch("/content-pieces/{$contentPiece->id}/status", [
        'status' => 'INVALID_STATUS',
    ]);

    $response->assertSessionHasErrors(['status']);
});

test('users cannot update status of content pieces from other teams', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $otherPrompt = Prompt::factory()->create(['team_id' => $otherTeam->id]);
    $otherPiece = ContentPiece::factory()->create([
        'team_id' => $otherTeam->id,
        'prompt_id' => $otherPrompt->id,
    ]);

    $this->actingAs($user)
        ->patch("/content-pieces/{$otherPiece->id}/status", [
            'status' => 'FINAL',
        ])
        ->assertForbidden();
});

test('authenticated users can delete their content pieces', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);
    $contentPiece = ContentPiece::factory()->create([
        'team_id' => $team->id,
        'prompt_id' => $prompt->id,
    ]);

    $response = $this->actingAs($user)->delete("/content-pieces/{$contentPiece->id}");

    $response->assertRedirect('/content-pieces');
    $this->assertDatabaseMissing('content_pieces', ['id' => $contentPiece->id]);
});

test('users cannot delete content pieces from other teams', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $otherPrompt = Prompt::factory()->create(['team_id' => $otherTeam->id]);
    $otherPiece = ContentPiece::factory()->create([
        'team_id' => $otherTeam->id,
        'prompt_id' => $otherPrompt->id,
    ]);

    $this->actingAs($user)
        ->delete("/content-pieces/{$otherPiece->id}")
        ->assertForbidden();
});

test('generate content requires prompt to be set', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create([
        'team_id' => $team->id,
        'prompt_id' => null,
    ]);

    $response = $this->actingAs($user)->post("/content-pieces/{$contentPiece->id}/generate");

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Please select a prompt template first.');
});

test('users cannot generate content for other teams content pieces', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $otherPrompt = Prompt::factory()->create(['team_id' => $otherTeam->id]);
    $otherPiece = ContentPiece::factory()->create([
        'team_id' => $otherTeam->id,
        'prompt_id' => $otherPrompt->id,
    ]);

    $this->actingAs($user)
        ->post("/content-pieces/{$otherPiece->id}/generate")
        ->assertForbidden();
});
