<?php

use App\Jobs\GenerateAIImage;
use App\Models\ContentPiece;
use App\Models\ImageGeneration;
use App\Models\Prompt;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Queue;

test('guests cannot access image generation endpoints', function () {
    $this->post('/content-pieces/1/image-generations')->assertRedirect('/login');
});

test('authenticated users can store image generation', function () {
    Queue::fake();

    [$user, $team] = createUserWithTeam();
    $team->update([
        'openai_api_key' => 'sk-openai',
        'gemini_api_key' => 'gm-key',
    ]);
    $prompt = Prompt::factory()->image()->create(['team_id' => $team->id]);
    $contentPiece = ContentPiece::factory()->create([
        'team_id' => $team->id,
        'edited_text' => 'Test content for image generation',
    ]);

    $openAI = $this->mock(OpenAIService::class, function ($mock) {
        $mock->shouldReceive('configureForTeam')->andReturnSelf();
        $mock->shouldReceive('generateImagePrompt')
            ->once()
            ->andReturn([
                'prompt' => 'A beautiful landscape with mountains and a sunset',
                'input_tokens' => 100,
                'output_tokens' => 50,
                'total_tokens' => 150,
                'model' => 'gpt-4o-mini',
            ]);
        $mock->shouldReceive('trackUsage')->once();
    });

    $response = $this->actingAs($user)
        ->postJson("/content-pieces/{$contentPiece->id}/image-generations", [
            'prompt_id' => $prompt->id,
            'aspect_ratio' => '16:9',
        ]);

    $response->assertSuccessful()
        ->assertJsonStructure(['image_generation' => ['id', 'generated_text_prompt', 'aspect_ratio', 'status']]);

    $this->assertDatabaseHas('image_generations', [
        'content_piece_id' => $contentPiece->id,
        'prompt_id' => $prompt->id,
        'aspect_ratio' => '16:9',
        'status' => 'DRAFT',
    ]);
});

test('image generation store returns 422 when openai is missing', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->image()->create(['team_id' => $team->id]);
    $contentPiece = ContentPiece::factory()->create([
        'team_id' => $team->id,
        'edited_text' => 'Content',
    ]);

    $response = $this->actingAs($user)
        ->postJson("/content-pieces/{$contentPiece->id}/image-generations", [
            'prompt_id' => $prompt->id,
            'aspect_ratio' => '16:9',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonPath('settings_url', '/team-settings?tab=ai');
});

test('store image generation validates prompt exists', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create([
        'team_id' => $team->id,
        'edited_text' => 'Test content',
    ]);

    $response = $this->actingAs($user)
        ->postJson("/content-pieces/{$contentPiece->id}/image-generations", [
            'prompt_id' => 99999,
            'aspect_ratio' => '16:9',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['prompt_id']);
});

test('store image generation validates aspect ratio', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->image()->create(['team_id' => $team->id]);
    $contentPiece = ContentPiece::factory()->create([
        'team_id' => $team->id,
        'edited_text' => 'Test content',
    ]);

    $response = $this->actingAs($user)
        ->postJson("/content-pieces/{$contentPiece->id}/image-generations", [
            'prompt_id' => $prompt->id,
            'aspect_ratio' => 'invalid',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['aspect_ratio']);
});

test('authenticated users can update image generation prompt text', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $imageGeneration = ImageGeneration::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'generated_text_prompt' => 'Original prompt',
    ]);

    $response = $this->actingAs($user)
        ->patchJson("/content-pieces/{$contentPiece->id}/image-generations/{$imageGeneration->id}", [
            'generated_text_prompt' => 'Updated prompt text',
            'aspect_ratio' => '1:1',
        ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('image_generations', [
        'id' => $imageGeneration->id,
        'generated_text_prompt' => 'Updated prompt text',
        'aspect_ratio' => '1:1',
    ]);
});

test('authenticated users can trigger image generation', function () {
    Queue::fake();

    [$user, $team] = createUserWithTeam();
    $team->update(['gemini_api_key' => 'gm-key']);
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $imageGeneration = ImageGeneration::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'generated_text_prompt' => 'A beautiful sunset over mountains',
        'status' => 'DRAFT',
    ]);

    $response = $this->actingAs($user)
        ->postJson("/content-pieces/{$contentPiece->id}/image-generations/{$imageGeneration->id}/generate");

    $response->assertSuccessful();

    Queue::assertPushed(GenerateAIImage::class, function ($job) use ($imageGeneration) {
        return $job->imageGeneration->id === $imageGeneration->id;
    });

    $this->assertDatabaseHas('image_generations', [
        'id' => $imageGeneration->id,
        'status' => 'GENERATING',
    ]);
});

test('cannot trigger generation without text prompt', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $imageGeneration = ImageGeneration::factory()->create([
        'content_piece_id' => $contentPiece->id,
        'generated_text_prompt' => null,
        'status' => 'DRAFT',
    ]);

    $response = $this->actingAs($user)
        ->postJson("/content-pieces/{$contentPiece->id}/image-generations/{$imageGeneration->id}/generate");

    $response->assertUnprocessable();
});

test('authenticated users can check generation status', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $imageGeneration = ImageGeneration::factory()->generating()->create([
        'content_piece_id' => $contentPiece->id,
    ]);

    $response = $this->actingAs($user)
        ->getJson("/content-pieces/{$contentPiece->id}/image-generations/{$imageGeneration->id}/status");

    $response->assertSuccessful()
        ->assertJsonStructure(['image_generation' => ['id', 'status', 'media']]);
});

test('authenticated users can delete image generation', function () {
    [$user, $team] = createUserWithTeam();
    $contentPiece = ContentPiece::factory()->create(['team_id' => $team->id]);
    $imageGeneration = ImageGeneration::factory()->create([
        'content_piece_id' => $contentPiece->id,
    ]);

    $response = $this->actingAs($user)
        ->deleteJson("/content-pieces/{$contentPiece->id}/image-generations/{$imageGeneration->id}");

    $response->assertSuccessful();

    $this->assertDatabaseMissing('image_generations', [
        'id' => $imageGeneration->id,
    ]);
});

test('users cannot access other teams image generations', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

    $otherContentPiece = ContentPiece::factory()->create(['team_id' => $otherTeam->id]);
    $otherGeneration = ImageGeneration::factory()->create([
        'content_piece_id' => $otherContentPiece->id,
    ]);

    $response = $this->actingAs($user)
        ->getJson("/content-pieces/{$otherContentPiece->id}/image-generations/{$otherGeneration->id}/status");

    $response->assertForbidden();
});

test('content piece edit page shows image prompts and generations', function () {
    [$user, $team] = createUserWithTeam();
    $contentPrompt = Prompt::factory()->create(['team_id' => $team->id]);
    $imagePrompt = Prompt::factory()->image()->create(['team_id' => $team->id]);
    $contentPiece = ContentPiece::factory()->create([
        'team_id' => $team->id,
        'prompt_id' => $contentPrompt->id,
    ]);
    $imageGeneration = ImageGeneration::factory()->completed()->create([
        'content_piece_id' => $contentPiece->id,
        'prompt_id' => $imagePrompt->id,
    ]);

    $response = $this->actingAs($user)
        ->get("/content-pieces/{$contentPiece->id}/edit");

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('ContentPieces/Edit')
            ->has('imagePrompts', 1)
            ->has('imageGenerations', 1)
        );
});

test('prompts can be created with IMAGE type', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)
        ->post('/prompts', [
            'internal_name' => 'Hero Image Generator',
            'type' => 'IMAGE',
            'prompt_text' => 'Generate a hero image based on: {{content}}',
        ]);

    $response->assertRedirect('/prompts');

    $this->assertDatabaseHas('prompts', [
        'team_id' => $team->id,
        'internal_name' => 'Hero Image Generator',
        'type' => 'IMAGE',
    ]);
});

test('prompts index shows type column', function () {
    [$user, $team] = createUserWithTeam();

    Prompt::factory()->create(['team_id' => $team->id, 'type' => 'CONTENT']);
    Prompt::factory()->image()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->get('/prompts');

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Prompts/Index')
            ->has('prompts.data', 2)
            ->where('prompts.data.0.type', fn ($type) => in_array($type, ['CONTENT', 'IMAGE']))
        );
});
