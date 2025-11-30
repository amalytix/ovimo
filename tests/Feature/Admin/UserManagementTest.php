<?php

use App\Models\Source;
use App\Models\User;

test('admin can view users list', function () {
    [$admin, $team] = createUserWithTeam();
    $admin->update(['is_admin' => true]);

    User::factory()->count(5)->create();

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Index')
            ->has('users.data', 6) // 5 + admin
        );
});

test('users list shows correct stats', function () {
    [$admin, $team] = createUserWithTeam();
    $admin->update(['is_admin' => true]);

    [$user, $userTeam] = createUserWithTeam();
    Source::factory()->count(3)->create(['team_id' => $userTeam->id]);

    $response = $this->actingAs($admin)
        ->get('/admin/users')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Index')
        );

    // Find the user in the data and check their sources count
    $usersData = $response->viewData('page')['props']['users']['data'];
    $targetUser = collect($usersData)->firstWhere('id', $user->id);
    expect($targetUser['sources_count'])->toBe(3);
});
