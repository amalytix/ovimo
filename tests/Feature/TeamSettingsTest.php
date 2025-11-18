<?php

use App\Models\User;

test('guests cannot access team settings', function () {
    $this->get('/team-settings')->assertRedirect('/login');
});

test('authenticated users can view team settings', function () {
    [$user, $team] = createUserWithTeam();

    $this->actingAs($user)
        ->get('/team-settings')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Index')
            ->has('team')
            ->where('team.id', $team->id)
        );
});

test('team owner can update settings', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->put('/team-settings', [
        'name' => 'Updated Team Name',
        'post_auto_hide_days' => 30,
        'monthly_token_limit' => 5000000,
        'relevancy_prompt' => 'Custom AI prompt for relevancy scoring.',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'Updated Team Name',
        'post_auto_hide_days' => 30,
        'monthly_token_limit' => 5000000,
    ]);
});

test('non-owner cannot update team settings', function () {
    [$owner, $team] = createUserWithTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member->id, ['role' => 'member']);

    $this->actingAs($member)->put('/team-settings', [
        'name' => 'Hacked Team Name',
        'post_auto_hide_days' => null,
        'monthly_token_limit' => 10000000,
        'relevancy_prompt' => null,
    ])->assertForbidden();

    $this->assertDatabaseMissing('teams', [
        'id' => $team->id,
        'name' => 'Hacked Team Name',
    ]);
});

test('team settings validation requires name', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->put('/team-settings', [
        'name' => '',
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('team settings accepts nullable fields', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->put('/team-settings', [
        'name' => 'Team Name',
        'post_auto_hide_days' => null,
        'monthly_token_limit' => 10000000,
        'relevancy_prompt' => null,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'Team Name',
        'post_auto_hide_days' => null,
        'relevancy_prompt' => null,
    ]);
});

test('team settings validates post_auto_hide_days range', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->put('/team-settings', [
        'name' => 'Team Name',
        'post_auto_hide_days' => 400, // exceeds max 365
    ]);

    $response->assertSessionHasErrors(['post_auto_hide_days']);
});

test('team owner can update keyword filtering settings', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->put('/team-settings', [
        'name' => 'Team Name',
        'positive_keywords' => "climate\nrenewable\nsustainability",
        'negative_keywords' => "sponsored\nadvertisement",
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'positive_keywords' => "climate\nrenewable\nsustainability",
        'negative_keywords' => "sponsored\nadvertisement",
    ]);
});

test('team settings includes keyword fields in response', function () {
    [$user, $team] = createUserWithTeam();

    $team->update([
        'positive_keywords' => "climate\nrenewable",
        'negative_keywords' => 'sponsored',
    ]);

    $this->actingAs($user)
        ->get('/team-settings')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Index')
            ->where('team.positive_keywords', "climate\nrenewable")
            ->where('team.negative_keywords', 'sponsored')
        );
});

test('team settings accepts null keyword fields', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->put('/team-settings', [
        'name' => 'Team Name',
        'positive_keywords' => null,
        'negative_keywords' => null,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'positive_keywords' => null,
        'negative_keywords' => null,
    ]);
});
