<?php

use App\Models\Prompt;

test('guests cannot access prompts', function () {
    $this->get('/prompts')->assertRedirect('/login');
});

test('authenticated users can view prompts index', function () {
    [$user, $team] = createUserWithTeam();

    $this->actingAs($user)
        ->get('/prompts')
        ->assertSuccessful();
});

test('prompts index only shows team prompts', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

    $teamPrompt = Prompt::factory()->create(['team_id' => $team->id]);
    $otherPrompt = Prompt::factory()->create(['team_id' => $otherTeam->id]);

    $response = $this->actingAs($user)->get('/prompts');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Prompts/Index')
        ->has('prompts.data', 1)
        ->where('prompts.data.0.id', $teamPrompt->id)
    );
});

test('authenticated users can view create prompt form', function () {
    [$user, $team] = createUserWithTeam();

    $this->actingAs($user)
        ->get('/prompts/create')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Prompts/Create'));
});

test('authenticated users can create a prompt', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/prompts', [
        'internal_name' => 'Test Prompt',
        'channel' => 'BLOG_POST',
        'prompt_text' => 'Generate a blog post about {{context}}',
    ]);

    $response->assertRedirect('/prompts');

    $this->assertDatabaseHas('prompts', [
        'team_id' => $team->id,
        'internal_name' => 'Test Prompt',
        'channel' => 'BLOG_POST',
        'prompt_text' => 'Generate a blog post about {{context}}',
    ]);
});

test('prompt creation validates required fields', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/prompts', []);

    $response->assertSessionHasErrors(['internal_name', 'channel', 'prompt_text']);
});

test('prompt creation validates channel is valid', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/prompts', [
        'internal_name' => 'Test',
        'channel' => 'INVALID_CHANNEL',
        'prompt_text' => 'Test prompt',
    ]);

    $response->assertSessionHasErrors(['channel']);
});

test('authenticated users can edit their own team prompts', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->get("/prompts/{$prompt->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Prompts/Edit')
            ->where('prompt.id', $prompt->id)
        );
});

test('users cannot edit prompts from other teams', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $otherPrompt = Prompt::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->get("/prompts/{$otherPrompt->id}/edit")
        ->assertForbidden();
});

test('authenticated users can update their prompts', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->put("/prompts/{$prompt->id}", [
        'internal_name' => 'Updated Prompt Name',
        'channel' => 'LINKEDIN_POST',
        'prompt_text' => 'Updated prompt text',
    ]);

    $response->assertRedirect('/prompts');

    $prompt->refresh();
    expect($prompt->internal_name)->toBe('Updated Prompt Name');
    expect($prompt->channel)->toBe('LINKEDIN_POST');
    expect($prompt->prompt_text)->toBe('Updated prompt text');
});

test('users cannot update prompts from other teams', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $otherPrompt = Prompt::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->put("/prompts/{$otherPrompt->id}", [
            'internal_name' => 'Updated',
            'channel' => 'BLOG_POST',
            'prompt_text' => 'Updated',
        ])
        ->assertForbidden();
});

test('authenticated users can delete their prompts', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->delete("/prompts/{$prompt->id}");

    $response->assertRedirect('/prompts');
    $this->assertDatabaseMissing('prompts', ['id' => $prompt->id]);
});

test('users cannot delete prompts from other teams', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $otherPrompt = Prompt::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->delete("/prompts/{$otherPrompt->id}")
        ->assertForbidden();
});
