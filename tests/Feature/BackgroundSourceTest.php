<?php

use App\Models\BackgroundSource;
use App\Models\ContentPiece;
use App\Models\Post;
use App\Models\Source;

test('guests cannot access background sources', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);

    $this->post("/content-pieces/{$contentPiece->id}/sources")->assertRedirect('/login');
});

test('can add a POST type background source', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $source = Source::factory()->create(['team_id' => $team->id]);
    $post = Post::factory()->create(['source_id' => $source->id]);

    $response = $this->actingAs($user)->post("/content-pieces/{$contentPiece->id}/sources", [
        'type' => 'POST',
        'post_id' => $post->id,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('background_sources', [
        'content_piece_id' => $contentPiece->id,
        'type' => 'POST',
        'post_id' => $post->id,
    ]);
});

test('can add a MANUAL type background source', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post("/content-pieces/{$contentPiece->id}/sources", [
        'type' => 'MANUAL',
        'title' => 'Manual Source Title',
        'content' => 'Some background information text',
        'url' => 'https://example.com/source',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('background_sources', [
        'content_piece_id' => $contentPiece->id,
        'type' => 'MANUAL',
        'title' => 'Manual Source Title',
        'content' => 'Some background information text',
        'url' => 'https://example.com/source',
    ]);
});

test('POST type requires post_id', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post("/content-pieces/{$contentPiece->id}/sources", [
        'type' => 'POST',
    ]);

    $response->assertSessionHasErrors(['post_id']);
});

test('MANUAL type does not require post_id', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post("/content-pieces/{$contentPiece->id}/sources", [
        'type' => 'MANUAL',
        'title' => 'Manual Source',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('background_sources', [
        'content_piece_id' => $contentPiece->id,
        'type' => 'MANUAL',
        'title' => 'Manual Source',
    ]);
});

test('can update a MANUAL background source', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $source = BackgroundSource::factory()->manual()->create([
        'content_piece_id' => $contentPiece->id,
        'title' => 'Original Title',
    ]);

    $response = $this->actingAs($user)->put("/content-pieces/{$contentPiece->id}/sources/{$source->id}", [
        'title' => 'Updated Title',
        'content' => 'Updated content',
    ]);

    $response->assertRedirect();

    $source->refresh();
    expect($source->title)->toBe('Updated Title');
    expect($source->content)->toBe('Updated content');
});

test('can delete a background source', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $source = BackgroundSource::factory()->manual()->create([
        'content_piece_id' => $contentPiece->id,
    ]);

    $response = $this->actingAs($user)->delete("/content-pieces/{$contentPiece->id}/sources/{$source->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('background_sources', ['id' => $source->id]);
});

test('cannot access sources of another team content piece', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $otherContentPiece = ContentPiece::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)->post("/content-pieces/{$otherContentPiece->id}/sources", [
        'type' => 'MANUAL',
        'title' => 'Test',
    ])->assertForbidden();
});

test('sources can be reordered', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $source1 = BackgroundSource::factory()->manual()->create([
        'content_piece_id' => $contentPiece->id,
        'sort_order' => 0,
    ]);
    $source2 = BackgroundSource::factory()->manual()->create([
        'content_piece_id' => $contentPiece->id,
        'sort_order' => 1,
    ]);
    $source3 = BackgroundSource::factory()->manual()->create([
        'content_piece_id' => $contentPiece->id,
        'sort_order' => 2,
    ]);

    $response = $this->actingAs($user)->post("/content-pieces/{$contentPiece->id}/sources/reorder", [
        'source_ids' => [$source3->id, $source1->id, $source2->id],
    ]);

    $response->assertSuccessful();

    $source1->refresh();
    $source2->refresh();
    $source3->refresh();

    expect($source3->sort_order)->toBe(0);
    expect($source1->sort_order)->toBe(1);
    expect($source2->sort_order)->toBe(2);
});

test('content piece edit page includes background sources', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $source = BackgroundSource::factory()->manual()->create([
        'content_piece_id' => $contentPiece->id,
    ]);

    $response = $this->actingAs($user)->get("/content-pieces/{$contentPiece->id}/edit");

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('ContentPieces/Edit')
            ->has('backgroundSources', 1)
            ->where('backgroundSources.0.id', $source->id)
        );
});

test('cannot add same post twice to content piece', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $source = Source::factory()->create(['team_id' => $team->id]);
    $post = Post::factory()->create(['source_id' => $source->id]);

    BackgroundSource::factory()->fromPost()->create([
        'content_piece_id' => $contentPiece->id,
        'post_id' => $post->id,
    ]);

    $response = $this->actingAs($user)->post("/content-pieces/{$contentPiece->id}/sources", [
        'type' => 'POST',
        'post_id' => $post->id,
    ]);

    $response->assertSessionHasErrors(['post_id']);
});
