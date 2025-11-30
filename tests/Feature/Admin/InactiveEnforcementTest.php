<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('inactive user login', function () {
    it('blocks inactive users from logging in', function () {
        $user = User::factory()->inactive()->withoutTwoFactor()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // User should not be authenticated
        $this->assertGuest();
    });

    it('allows active users to log in', function () {
        $user = User::factory()->withoutTwoFactor()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
    });
});

describe('inactive team enforcement', function () {
    it('redirects user when current team becomes inactive', function () {
        $user = User::factory()->create();
        $team = Team::factory()->create(['is_active' => false]);

        $user->teams()->attach($team);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)
            ->get('/dashboard');

        // Should redirect with error message
        $response->assertRedirect('/dashboard');
    });

    it('switches to active team when current team is inactive', function () {
        $user = User::factory()->create();
        $inactiveTeam = Team::factory()->create(['is_active' => false]);
        $activeTeam = Team::factory()->create(['is_active' => true]);

        $user->teams()->attach($inactiveTeam);
        $user->teams()->attach($activeTeam);
        $user->update(['current_team_id' => $inactiveTeam->id]);

        $this->actingAs($user)
            ->get('/dashboard');

        // User should be switched to active team
        $user->refresh();
        expect($user->current_team_id)->toBe($activeTeam->id);
    });
});
