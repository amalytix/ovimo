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

test('admin can view user edit page', function () {
    [$admin, $team] = createUserWithTeam();
    $admin->update(['is_admin' => true]);

    [$user, $userTeam] = createUserWithTeam();

    $this->actingAs($admin)
        ->get("/admin/users/{$user->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Edit')
            ->has('user')
            ->where('user.id', $user->id)
        );
});

test('admin can update user', function () {
    [$admin, $team] = createUserWithTeam();
    $admin->update(['is_admin' => true]);

    [$user, $userTeam] = createUserWithTeam();

    $this->actingAs($admin)
        ->put("/admin/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => $user->email,
            'is_active' => false,
            'is_admin' => false,
        ])
        ->assertRedirect('/admin/users');

    expect($user->fresh())
        ->name->toBe('Updated Name')
        ->is_active->toBeFalse();
});

test('admin can toggle user active status', function () {
    [$admin, $team] = createUserWithTeam();
    $admin->update(['is_admin' => true]);

    [$user, $userTeam] = createUserWithTeam();
    expect($user->is_active)->toBeTrue();

    $this->actingAs($admin)
        ->put("/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => false,
            'is_admin' => false,
        ])
        ->assertRedirect();

    expect($user->fresh()->is_active)->toBeFalse();
});
