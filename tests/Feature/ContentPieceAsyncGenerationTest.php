<?php

use App\Jobs\GenerateContentPiece;
use App\Models\ContentPiece;
use App\Models\Post;
use App\Models\Prompt;
use App\Models\Source;
use Illuminate\Support\Facades\Queue;

test('dispatches job when creating content piece with generate flag', function () {
    Queue::fake();
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);
    $source = Source::factory()->create(['team_id' => $team->id]);
    $post = Post::factory()->create(['source_id' => $source->id]);

    $this->actingAs($user)->post('/content-pieces', [
        'internal_name' => 'Test Content Piece',
        'prompt_id' => $prompt->id,
        'briefing_text' => 'Test briefing',
        'channel' => 'BLOG_POST',
        'target_language' => 'ENGLISH',
        'post_ids' => [$post->id],
        'generate_content' => true,
    ]);

    Queue::assertPushed(GenerateContentPiece::class);
});

test('prevents concurrent generation for same content piece', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);
    $contentPiece = ContentPiece::factory()->create([
        'team_id' => $team->id,
        'prompt_id' => $prompt->id,
        'generation_status' => 'PROCESSING',
    ]);

    $response = $this->actingAs($user)->post("/content-pieces/{$contentPiece->id}/generate");

    $response->assertSessionHas('error');
    expect(session('error'))->toContain('already in progress');
});

test('returns status via polling endpoint', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);
    $contentPiece = ContentPiece::factory()->create([
        'team_id' => $team->id,
        'prompt_id' => $prompt->id,
        'generation_status' => 'PROCESSING',
    ]);

    $response = $this->actingAs($user)->getJson("/content-pieces/{$contentPiece->id}/status");

    $response->assertSuccessful();
    $response->assertJson([
        'generation_status' => 'PROCESSING',
    ]);
});

test('status endpoint returns content when completed', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);
    $contentPiece = ContentPiece::factory()->create([
        'team_id' => $team->id,
        'prompt_id' => $prompt->id,
        'generation_status' => 'COMPLETED',
        'research_text' => 'Generated content here',
        'status' => 'DRAFT',
    ]);

    $response = $this->actingAs($user)->getJson("/content-pieces/{$contentPiece->id}/status");

    $response->assertSuccessful();
    $response->assertJson([
        'generation_status' => 'COMPLETED',
        'research_text' => 'Generated content here',
        'status' => 'DRAFT',
    ]);
});

test('status endpoint returns error when failed', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);
    $contentPiece = ContentPiece::factory()->create([
        'team_id' => $team->id,
        'prompt_id' => $prompt->id,
        'generation_status' => 'FAILED',
        'generation_error' => 'OpenAI rate limit exceeded',
    ]);

    $response = $this->actingAs($user)->getJson("/content-pieces/{$contentPiece->id}/status");

    $response->assertSuccessful();
    $response->assertJson([
        'generation_status' => 'FAILED',
        'error' => 'OpenAI rate limit exceeded',
    ]);
});
