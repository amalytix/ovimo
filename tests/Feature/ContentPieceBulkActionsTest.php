<?php

use App\Models\ContentPiece;
use App\Models\Prompt;

it('allows bulk delete of multiple content pieces', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);

    $piece1 = ContentPiece::factory()->for($team)->create(['prompt_id' => $prompt->id]);
    $piece2 = ContentPiece::factory()->for($team)->create(['prompt_id' => $prompt->id]);
    $piece3 = ContentPiece::factory()->for($team)->create(['prompt_id' => $prompt->id]);

    $response = $this->actingAs($user)->postJson('/content-pieces/bulk-delete', [
        'content_piece_ids' => [$piece1->id, $piece2->id],
    ]);

    $response->assertOk();
    $response->assertJson(['message' => 'Content pieces deleted successfully.']);

    expect(ContentPiece::find($piece1->id))->toBeNull();
    expect(ContentPiece::find($piece2->id))->toBeNull();
    expect(ContentPiece::find($piece3->id))->not->toBeNull();
});

it('prevents deleting content pieces from another team', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);
    $otherPrompt = Prompt::factory()->create(['team_id' => $otherTeam->id]);

    $piece = ContentPiece::factory()->for($team)->create(['prompt_id' => $prompt->id]);
    $otherPiece = ContentPiece::factory()->for($otherTeam)->create(['prompt_id' => $otherPrompt->id]);

    $response = $this->actingAs($user)->postJson('/content-pieces/bulk-delete', [
        'content_piece_ids' => [$piece->id, $otherPiece->id],
    ]);

    // Should succeed for team piece but skip other team's piece
    $response->assertOk();
    expect(ContentPiece::find($piece->id))->toBeNull();
    expect(ContentPiece::find($otherPiece->id))->not->toBeNull();
});

it('validates bulk delete requires array of IDs', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->postJson('/content-pieces/bulk-delete', [
        'content_piece_ids' => 'invalid',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['content_piece_ids']);
});

it('validates bulk delete requires at least one ID', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->postJson('/content-pieces/bulk-delete', [
        'content_piece_ids' => [],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['content_piece_ids']);
});

it('allows bulk unset of publish dates', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);

    $piece1 = ContentPiece::factory()->for($team)->create([
        'prompt_id' => $prompt->id,
        'published_at' => now()->addDay(),
    ]);
    $piece2 = ContentPiece::factory()->for($team)->create([
        'prompt_id' => $prompt->id,
        'published_at' => now()->addDays(2),
    ]);
    $piece3 = ContentPiece::factory()->for($team)->create([
        'prompt_id' => $prompt->id,
        'published_at' => now()->addDays(3),
    ]);

    $response = $this->actingAs($user)->postJson('/content-pieces/bulk-unset-publish-date', [
        'content_piece_ids' => [$piece1->id, $piece2->id],
    ]);

    $response->assertOk();
    $response->assertJson(['message' => 'Publish dates removed successfully.']);

    $piece1->refresh();
    $piece2->refresh();
    $piece3->refresh();

    expect($piece1->published_at)->toBeNull();
    expect($piece2->published_at)->toBeNull();
    expect($piece3->published_at)->not->toBeNull();
});

it('allows bulk update of status', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);

    $piece1 = ContentPiece::factory()->for($team)->create([
        'prompt_id' => $prompt->id,
        'status' => 'NOT_STARTED',
    ]);
    $piece2 = ContentPiece::factory()->for($team)->create([
        'prompt_id' => $prompt->id,
        'status' => 'NOT_STARTED',
    ]);
    $piece3 = ContentPiece::factory()->for($team)->create([
        'prompt_id' => $prompt->id,
        'status' => 'NOT_STARTED',
    ]);

    $response = $this->actingAs($user)->postJson('/content-pieces/bulk-update-status', [
        'content_piece_ids' => [$piece1->id, $piece2->id],
        'status' => 'DRAFT',
    ]);

    $response->assertOk();
    $response->assertJson(['message' => 'Status updated successfully.']);

    $piece1->refresh();
    $piece2->refresh();
    $piece3->refresh();

    expect($piece1->status)->toBe('DRAFT');
    expect($piece2->status)->toBe('DRAFT');
    expect($piece3->status)->toBe('NOT_STARTED');
});

it('validates bulk status update requires valid status', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);

    $piece = ContentPiece::factory()->for($team)->create(['prompt_id' => $prompt->id]);

    $response = $this->actingAs($user)->postJson('/content-pieces/bulk-update-status', [
        'content_piece_ids' => [$piece->id],
        'status' => 'INVALID_STATUS',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['status']);
});

it('validates bulk status update requires status field', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);

    $piece = ContentPiece::factory()->for($team)->create(['prompt_id' => $prompt->id]);

    $response = $this->actingAs($user)->postJson('/content-pieces/bulk-update-status', [
        'content_piece_ids' => [$piece->id],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['status']);
});

it('can update status to each valid value', function (string $status) {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);

    $piece = ContentPiece::factory()->for($team)->create([
        'prompt_id' => $prompt->id,
        'status' => 'NOT_STARTED',
    ]);

    $response = $this->actingAs($user)->postJson('/content-pieces/bulk-update-status', [
        'content_piece_ids' => [$piece->id],
        'status' => $status,
    ]);

    $response->assertOk();

    $piece->refresh();
    expect($piece->status)->toBe($status);
})->with([
    'NOT_STARTED',
    'DRAFT',
    'FINAL',
]);
