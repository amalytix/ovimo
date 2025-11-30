<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->regularUser = User::factory()->create(['is_admin' => false]);
});

it('admin can start impersonating a regular user', function () {
    $response = $this->actingAs($this->admin)
        ->post("/admin/users/{$this->regularUser->id}/impersonate");

    $response->assertRedirect(route('dashboard'));

    // Session should contain impersonator ID
    $this->assertEquals($this->admin->id, session('impersonator_id'));

    // Should be logged in as the regular user now
    $this->assertAuthenticatedAs($this->regularUser);
});

it('admin cannot impersonate another admin', function () {
    $otherAdmin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($this->admin)
        ->post("/admin/users/{$otherAdmin->id}/impersonate");

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('error', 'Cannot impersonate admin users.');

    // Should still be logged in as original admin
    $this->assertAuthenticatedAs($this->admin);
});

it('admin cannot impersonate themselves', function () {
    $response = $this->actingAs($this->admin)
        ->post("/admin/users/{$this->admin->id}/impersonate");

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('error', 'Cannot impersonate yourself.');
});

it('non-admin cannot impersonate users', function () {
    $response = $this->actingAs($this->regularUser)
        ->post("/admin/users/{$this->admin->id}/impersonate");

    $response->assertForbidden();
});

it('can stop impersonating and return to admin session', function () {
    // Start impersonating
    $this->actingAs($this->admin)
        ->post("/admin/users/{$this->regularUser->id}/impersonate");

    // Now stop impersonating
    $response = $this->post('/admin/impersonate/stop');

    $response->assertRedirect(route('admin.users.index'));

    // Session should be cleared
    $this->assertNull(session('impersonator_id'));

    // Should be logged in as admin again
    $this->assertAuthenticatedAs($this->admin);
});

it('logs impersonation start event', function () {
    $this->actingAs($this->admin)
        ->post("/admin/users/{$this->regularUser->id}/impersonate");

    $log = ActivityLog::where('event_type', 'admin.impersonation_started')->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($this->admin->id);
    expect($log->metadata['admin_id'])->toBe($this->admin->id);
    expect($log->metadata['target_user_id'])->toBe($this->regularUser->id);
});

it('logs impersonation end event', function () {
    // Start impersonating
    $this->actingAs($this->admin)
        ->post("/admin/users/{$this->regularUser->id}/impersonate");

    // Stop impersonating
    $this->post('/admin/impersonate/stop');

    $log = ActivityLog::where('event_type', 'admin.impersonation_ended')->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($this->admin->id);
    expect($log->metadata['admin_id'])->toBe($this->admin->id);
    expect($log->metadata['target_user_id'])->toBe($this->regularUser->id);
});

it('returns error when stopping impersonation without active session', function () {
    $response = $this->actingAs($this->regularUser)
        ->post('/admin/impersonate/stop');

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('error', 'You are not impersonating anyone.');
});
