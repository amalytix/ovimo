<?php

use App\Models\User;

test('non-admin users cannot access admin routes', function () {
    [$user, $team] = createUserWithTeam();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('admin users can access admin routes', function () {
    [$user, $team] = createUserWithTeam();
    $user->update(['is_admin' => true]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertSuccessful();
});

test('guests are redirected to login for admin routes', function () {
    $this->get('/admin')
        ->assertRedirect('/login');
});
