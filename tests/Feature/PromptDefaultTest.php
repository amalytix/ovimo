<?php

use App\Models\Prompt;

test('users can set a prompt as default', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id, 'is_default' => false]);

    $response = $this->actingAs($user)->post("/prompts/{$prompt->id}/set-default");

    $response->assertRedirect('/prompts');

    $prompt->refresh();
    expect($prompt->is_default)->toBeTrue();
});

test('setting a prompt as default removes default flag from other prompts', function () {
    [$user, $team] = createUserWithTeam();

    $prompt1 = Prompt::factory()->create(['team_id' => $team->id, 'is_default' => true]);
    $prompt2 = Prompt::factory()->create(['team_id' => $team->id, 'is_default' => false]);
    $prompt3 = Prompt::factory()->create(['team_id' => $team->id, 'is_default' => false]);

    $response = $this->actingAs($user)->post("/prompts/{$prompt2->id}/set-default");

    $response->assertRedirect('/prompts');

    $prompt1->refresh();
    $prompt2->refresh();
    $prompt3->refresh();

    expect($prompt1->is_default)->toBeFalse();
    expect($prompt2->is_default)->toBeTrue();
    expect($prompt3->is_default)->toBeFalse();
});

test('only one prompt can be default at a time', function () {
    [$user, $team] = createUserWithTeam();

    $prompt1 = Prompt::factory()->create(['team_id' => $team->id, 'is_default' => false]);
    $prompt2 = Prompt::factory()->create(['team_id' => $team->id, 'is_default' => false]);

    $this->actingAs($user)->post("/prompts/{$prompt1->id}/set-default");

    $defaultCount = Prompt::where('team_id', $team->id)->where('is_default', true)->count();
    expect($defaultCount)->toBe(1);

    $this->actingAs($user)->post("/prompts/{$prompt2->id}/set-default");

    $defaultCount = Prompt::where('team_id', $team->id)->where('is_default', true)->count();
    expect($defaultCount)->toBe(1);

    $prompt2->refresh();
    expect($prompt2->is_default)->toBeTrue();
});

test('users cannot set prompts from other teams as default', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

    $otherPrompt = Prompt::factory()->create(['team_id' => $otherTeam->id, 'is_default' => false]);

    $this->actingAs($user)
        ->post("/prompts/{$otherPrompt->id}/set-default")
        ->assertForbidden();

    $otherPrompt->refresh();
    expect($otherPrompt->is_default)->toBeFalse();
});

test('default prompt is shown first in content piece create page', function () {
    [$user, $team] = createUserWithTeam();

    $prompt1 = Prompt::factory()->create([
        'team_id' => $team->id,
        'internal_name' => 'A First Alphabetically',
        'is_default' => false,
        'created_at' => now()->subDays(2),
    ]);

    $prompt2 = Prompt::factory()->create([
        'team_id' => $team->id,
        'internal_name' => 'B Second',
        'is_default' => true,
        'created_at' => now()->subDays(1),
    ]);

    $response = $this->actingAs($user)->get('/content-pieces/create');

    $response->assertInertia(fn ($page) => $page
        ->component('ContentPieces/Create')
        ->where('prompts.0.id', $prompt2->id)
        ->where('prompts.1.id', $prompt1->id)
    );
});

test('last created prompt is shown first when no default exists', function () {
    [$user, $team] = createUserWithTeam();

    $prompt1 = Prompt::factory()->create([
        'team_id' => $team->id,
        'internal_name' => 'A First',
        'is_default' => false,
        'created_at' => now()->subDays(2),
    ]);

    $prompt2 = Prompt::factory()->create([
        'team_id' => $team->id,
        'internal_name' => 'Z Last Alphabetically',
        'is_default' => false,
        'created_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($user)->get('/content-pieces/create');

    $response->assertInertia(fn ($page) => $page
        ->component('ContentPieces/Create')
        ->where('prompts.0.id', $prompt2->id)
        ->where('prompts.1.id', $prompt1->id)
    );
});

test('default indicator is shown in prompts index', function () {
    [$user, $team] = createUserWithTeam();

    $defaultPrompt = Prompt::factory()->create(['team_id' => $team->id, 'is_default' => true]);
    $normalPrompt = Prompt::factory()->create(['team_id' => $team->id, 'is_default' => false]);

    $response = $this->actingAs($user)->get('/prompts');

    $response->assertInertia(fn ($page) => $page
        ->component('Prompts/Index')
        ->where('prompts.data.0.is_default', true)
        ->where('prompts.data.1.is_default', false)
    );
});

test('guests cannot set default prompt', function () {
    [$user, $team] = createUserWithTeam();
    $prompt = Prompt::factory()->create(['team_id' => $team->id, 'is_default' => false]);

    $this->post("/prompts/{$prompt->id}/set-default")
        ->assertRedirect('/login');

    $prompt->refresh();
    expect($prompt->is_default)->toBeFalse();
});
