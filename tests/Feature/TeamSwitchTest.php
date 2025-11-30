<?php

use App\Models\Team;

test('user can switch to team they belong to', function () {
    [$owner, $team1] = createUserWithTeam();
    $team2 = Team::factory()->create(['owner_id' => $owner->id]);
    $team2->users()->attach($owner->id, ['role' => 'owner']);

    $response = $this->actingAs($owner)->post("/teams/{$team2->id}/switch");

    $response->assertRedirect();
    expect($owner->fresh()->current_team_id)->toBe($team2->id);
});

test('user cannot switch to team they do not belong to', function () {
    [$owner, $team1] = createUserWithTeam();
    [$otherOwner, $otherTeam] = createUserWithTeam();

    $this->actingAs($owner)->post("/teams/{$otherTeam->id}/switch")
        ->assertForbidden();

    expect($owner->fresh()->current_team_id)->toBe($team1->id);
});

test('after switch current_team_id is updated', function () {
    [$owner, $team1] = createUserWithTeam();
    $team2 = Team::factory()->create(['owner_id' => $owner->id]);
    $team2->users()->attach($owner->id, ['role' => 'owner']);

    expect($owner->current_team_id)->toBe($team1->id);

    $this->actingAs($owner)->post("/teams/{$team2->id}/switch");

    expect($owner->fresh()->current_team_id)->toBe($team2->id);
});

test('guests cannot switch teams', function () {
    [$owner, $team] = createUserWithTeam();

    $this->post("/teams/{$team->id}/switch")->assertRedirect('/login');
});
