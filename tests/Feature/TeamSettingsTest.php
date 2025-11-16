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
        'notifications_enabled' => false,
        'webhook_url' => 'https://example.com/webhook',
        'post_auto_hide_days' => 30,
        'monthly_token_limit' => 5000000,
        'relevancy_prompt' => 'Custom AI prompt for relevancy scoring.',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'Updated Team Name',
        'notifications_enabled' => false,
        'webhook_url' => 'https://example.com/webhook',
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
        'notifications_enabled' => false,
        'webhook_url' => null,
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
        'notifications_enabled' => true,
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('team settings validation rejects invalid webhook url', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->put('/team-settings', [
        'name' => 'Team Name',
        'webhook_url' => 'not-a-valid-url',
    ]);

    $response->assertSessionHasErrors(['webhook_url']);
});

test('team settings accepts nullable fields', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->put('/team-settings', [
        'name' => 'Team Name',
        'notifications_enabled' => true,
        'webhook_url' => null,
        'post_auto_hide_days' => null,
        'monthly_token_limit' => 10000000,
        'relevancy_prompt' => null,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'Team Name',
        'webhook_url' => null,
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
