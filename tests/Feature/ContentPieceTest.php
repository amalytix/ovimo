<?php

use App\Models\ContentPiece;
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

// Status filtering has been moved to the derivatives level

test('content pieces index can filter by channel', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);

    $blogPost = ContentPiece::factory()->create([
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
        ->where('contentPieces.data.0.id', $blogPost->id)
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

    $response = $this->actingAs($user)->post('/content-pieces', [
        'internal_name' => 'Test Content Piece',
        'sources' => [
            [
                'type' => 'MANUAL',
                'title' => 'Test Source',
                'content' => 'Some background content',
            ],
        ],
    ]);

    $contentPiece = ContentPiece::where('internal_name', 'Test Content Piece')->first();
    $response->assertRedirect("/content-pieces/{$contentPiece->id}/edit?tab=derivatives");

    $this->assertDatabaseHas('content_pieces', [
        'team_id' => $team->id,
        'internal_name' => 'Test Content Piece',
    ]);

    $this->assertDatabaseHas('background_sources', [
        'content_piece_id' => $contentPiece->id,
        'type' => 'MANUAL',
        'title' => 'Test Source',
    ]);
});

test('content piece creation validates required fields', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/content-pieces', []);

    $response->assertSessionHasErrors(['internal_name']);
});

test('content piece creation validates source type', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/content-pieces', [
        'internal_name' => 'Test',
        'sources' => [
            ['type' => 'INVALID_TYPE'],
        ],
    ]);

    $response->assertSessionHasErrors(['sources.0.type']);
});

// Target language validation has been moved to channels

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

    $response = $this->actingAs($user)->put("/content-pieces/{$contentPiece->id}", [
        'internal_name' => 'Updated Content Name',
        'briefing_text' => 'Updated briefing',
        'channel' => 'LINKEDIN_POST',
    ]);

    $response->assertRedirect("/content-pieces/{$contentPiece->id}/edit");

    $contentPiece->refresh();
    expect($contentPiece->internal_name)->toBe('Updated Content Name');
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
        ])
        ->assertForbidden();
});

// Status updates have been moved to the derivatives level

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

// Generation is now done at the derivative level - see ContentDerivativeTest for derivative generation tests
