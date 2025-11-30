<?php

use App\Models\User;

test('members page shows team members with 2FA status', function () {
    [$owner, $team] = createUserWithTeam();
    $memberWith2FA = User::factory()->create([
        'current_team_id' => $team->id,
        'two_factor_confirmed_at' => now(),
    ]);
    $memberWithout2FA = User::factory()->withoutTwoFactor()->create([
        'current_team_id' => $team->id,
    ]);
    $team->users()->attach($memberWith2FA->id, ['role' => 'member']);
    $team->users()->attach($memberWithout2FA->id, ['role' => 'member']);

    $response = $this->actingAs($owner)->get('/team-settings');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/Index')
        ->has('team.users', 3)
        ->where('isOwner', true)
    );
});

test('non-owner sees isOwner as false', function () {
    [$owner, $team] = createUserWithTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member->id, ['role' => 'member']);

    $response = $this->actingAs($member)->get('/team-settings');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->where('isOwner', false));
});

test('team owner can remove member', function () {
    [$owner, $team] = createUserWithTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member->id, ['role' => 'member']);

    $response = $this->actingAs($owner)->delete("/team-members/{$member->id}");

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect($team->users()->where('user_id', $member->id)->exists())->toBeFalse();
});

test('non-owner cannot remove member', function () {
    [$owner, $team] = createUserWithTeam();
    $member1 = User::factory()->create(['current_team_id' => $team->id]);
    $member2 = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member1->id, ['role' => 'member']);
    $team->users()->attach($member2->id, ['role' => 'member']);

    $this->actingAs($member1)->delete("/team-members/{$member2->id}")
        ->assertForbidden();

    expect($team->users()->where('user_id', $member2->id)->exists())->toBeTrue();
});

test('owner cannot remove self', function () {
    [$owner, $team] = createUserWithTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member->id, ['role' => 'member']);

    $response = $this->actingAs($owner)->delete("/team-members/{$owner->id}");

    $response->assertSessionHasErrors(['user']);
    expect($team->users()->where('user_id', $owner->id)->exists())->toBeTrue();
});

test('removed user current_team_id is updated', function () {
    [$owner, $team] = createUserWithTeam();
    [$owner2, $team2] = createUserWithTeam();

    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member->id, ['role' => 'member']);
    $team2->users()->attach($member->id, ['role' => 'member']);

    $this->actingAs($owner)->delete("/team-members/{$member->id}");

    expect($member->fresh()->current_team_id)->toBe($team2->id);
});

test('removed user with no other teams has null current_team_id', function () {
    [$owner, $team] = createUserWithTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member->id, ['role' => 'member']);

    $this->actingAs($owner)->delete("/team-members/{$member->id}");

    expect($member->fresh()->current_team_id)->toBeNull();
});

test('user can leave team', function () {
    [$owner, $team] = createUserWithTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member->id, ['role' => 'member']);

    $response = $this->actingAs($member)->post('/team-members/leave');

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('success');

    expect($team->users()->where('user_id', $member->id)->exists())->toBeFalse();
});

test('cannot leave if only member', function () {
    [$owner, $team] = createUserWithTeam();

    $response = $this->actingAs($owner)->post('/team-members/leave');

    $response->assertSessionHasErrors(['team']);
    expect($team->users()->where('user_id', $owner->id)->exists())->toBeTrue();
});

test('owner cannot leave team', function () {
    [$owner, $team] = createUserWithTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member->id, ['role' => 'member']);

    $response = $this->actingAs($owner)->post('/team-members/leave');

    $response->assertSessionHasErrors(['team']);
    expect($team->users()->where('user_id', $owner->id)->exists())->toBeTrue();
});

test('after leaving current_team_id is updated', function () {
    [$owner, $team] = createUserWithTeam();
    [$owner2, $team2] = createUserWithTeam();

    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member->id, ['role' => 'member']);
    $team2->users()->attach($member->id, ['role' => 'member']);

    $this->actingAs($member)->post('/team-members/leave');

    expect($member->fresh()->current_team_id)->toBe($team2->id);
});

test('page shows pending invitations for owner', function () {
    [$owner, $team] = createUserWithTeam();

    \App\Models\TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'pending@example.com',
        'expires_at' => now()->addHours(48),
    ]);

    $response = $this->actingAs($owner)->get('/team-settings');

    $response->assertInertia(fn ($page) => $page
        ->has('pendingInvitations', 1)
        ->where('pendingInvitations.0.email', 'pending@example.com')
    );
});

test('guests cannot access team settings page', function () {
    $this->get('/team-settings')->assertRedirect('/login');
});
