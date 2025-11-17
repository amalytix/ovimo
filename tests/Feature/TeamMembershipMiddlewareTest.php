<?php

use App\Models\Team;
use App\Models\User;

it('allows request when user belongs to current team', function () {
    [$user, $team] = createUserWithTeam();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful();
});

it('allows request when user owns the current team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);

    // User owns but doesn't belong via pivot (edge case)
    $user->update(['current_team_id' => $team->id]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful();
});

it('redirects user to dashboard when current team is invalid', function () {
    [$user, $validTeam] = createUserWithTeam();

    // Create another team that user doesn't belong to
    $invalidTeam = Team::factory()->create();
    $user->update(['current_team_id' => $invalidTeam->id]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/dashboard')
        ->assertSessionHas('warning');

    // Verify user was switched back to valid team
    $user->refresh();
    expect($user->current_team_id)->toBe($validTeam->id);
});

it('switches to owned team if user has no team membership', function () {
    $user = User::factory()->create();
    $ownedTeam = Team::factory()->create(['owner_id' => $user->id]);

    // Set an invalid team
    $invalidTeam = Team::factory()->create();
    $user->update(['current_team_id' => $invalidTeam->id]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/dashboard');

    // Should switch to owned team
    $user->refresh();
    expect($user->current_team_id)->toBe($ownedTeam->id);
});

it('aborts with 403 when user has no teams at all', function () {
    $user = User::factory()->create();
    $invalidTeam = Team::factory()->create();

    $user->update(['current_team_id' => $invalidTeam->id]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertStatus(403)
        ->assertSee('do not have access to any team');
});

it('allows switching between teams user belongs to', function () {
    $user = User::factory()->create();
    $team1 = Team::factory()->create(['owner_id' => $user->id]);
    $team2 = Team::factory()->create(['owner_id' => $user->id]);

    $user->teams()->attach([$team1->id, $team2->id], ['role' => 'admin']);
    $user->update(['current_team_id' => $team1->id]);

    // Access with first team
    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful();

    // Switch to second team
    $user->update(['current_team_id' => $team2->id]);
    $user->refresh();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful();

    expect($user->current_team_id)->toBe($team2->id);
});

it('prevents access to other teams resources even when team is valid', function () {
    [$user, $team] = createUserWithTeam();
    $otherTeam = Team::factory()->create();
    $otherSource = \App\Models\Source::factory()->create(['team_id' => $otherTeam->id]);

    // User has valid team but tries to access another team's source
    $this->actingAs($user)
        ->get("/sources/{$otherSource->id}/edit")
        ->assertForbidden();
});
