<?php

use App\Models\Source;
use App\Models\Team;
use App\Models\User;

test('admin can view teams list', function () {
    [$admin, $team] = createUserWithTeam();
    $admin->update(['is_admin' => true]);

    Team::factory()->count(5)->create();

    $this->actingAs($admin)
        ->get('/admin/teams')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Teams/Index')
            ->has('teams.data', 6) // 5 + admin team
        );
});

test('teams list shows correct stats', function () {
    [$admin, $team] = createUserWithTeam();
    $admin->update(['is_admin' => true]);

    $testTeam = Team::factory()->create();
    User::factory()->count(3)->create()->each(fn ($u) => $u->teams()->attach($testTeam));
    Source::factory()->count(5)->create(['team_id' => $testTeam->id]);

    $response = $this->actingAs($admin)
        ->get('/admin/teams')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Teams/Index')
        );

    $teamsData = $response->viewData('page')['props']['teams']['data'];
    $targetTeam = collect($teamsData)->firstWhere('id', $testTeam->id);
    expect($targetTeam['users_count'])->toBe(3);
    expect($targetTeam['sources_count'])->toBe(5);
});

test('admin can view team edit page', function () {
    [$admin, $team] = createUserWithTeam();
    $admin->update(['is_admin' => true]);

    $testTeam = Team::factory()->create();

    $this->actingAs($admin)
        ->get("/admin/teams/{$testTeam->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Teams/Edit')
            ->has('team')
            ->where('team.id', $testTeam->id)
        );
});

test('admin can update team', function () {
    [$admin, $team] = createUserWithTeam();
    $admin->update(['is_admin' => true]);

    $testTeam = Team::factory()->create();

    $this->actingAs($admin)
        ->put("/admin/teams/{$testTeam->id}", [
            'name' => 'Updated Team Name',
            'is_active' => false,
        ])
        ->assertRedirect('/admin/teams');

    expect($testTeam->fresh())
        ->name->toBe('Updated Team Name')
        ->is_active->toBeFalse();
});

test('admin can toggle team active status', function () {
    [$admin, $team] = createUserWithTeam();
    $admin->update(['is_admin' => true]);

    $testTeam = Team::factory()->create();
    expect($testTeam->is_active)->toBeTrue();

    $this->actingAs($admin)
        ->put("/admin/teams/{$testTeam->id}", [
            'name' => $testTeam->name,
            'is_active' => false,
        ])
        ->assertRedirect();

    expect($testTeam->fresh()->is_active)->toBeFalse();
});
