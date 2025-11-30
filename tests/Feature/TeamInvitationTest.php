<?php

use App\Mail\TeamInvitationMail;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('team owner can send invitation', function () {
    Mail::fake();

    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/team-invitations', [
        'email' => 'newuser@example.com',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('team_invitations', [
        'team_id' => $team->id,
        'email' => 'newuser@example.com',
    ]);

    Mail::assertQueued(TeamInvitationMail::class, function ($mail) {
        return $mail->hasTo('newuser@example.com');
    });
});

test('non-owner cannot send invitation', function () {
    [$owner, $team] = createUserWithTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member->id, ['role' => 'member']);

    $this->actingAs($member)->post('/team-invitations', [
        'email' => 'newuser@example.com',
    ])->assertForbidden();

    $this->assertDatabaseMissing('team_invitations', [
        'email' => 'newuser@example.com',
    ]);
});

test('cannot invite existing team member', function () {
    [$owner, $team] = createUserWithTeam();
    $member = User::factory()->create([
        'email' => 'existing@example.com',
        'current_team_id' => $team->id,
    ]);
    $team->users()->attach($member->id, ['role' => 'member']);

    $response = $this->actingAs($owner)->post('/team-invitations', [
        'email' => 'existing@example.com',
    ]);

    $response->assertSessionHasErrors(['email']);
    $this->assertDatabaseMissing('team_invitations', [
        'email' => 'existing@example.com',
    ]);
});

test('cannot invite if pending invitation exists', function () {
    [$user, $team] = createUserWithTeam();

    TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'pending@example.com',
        'expires_at' => now()->addHours(48),
    ]);

    $response = $this->actingAs($user)->post('/team-invitations', [
        'email' => 'pending@example.com',
    ]);

    $response->assertSessionHasErrors(['email']);
    expect(TeamInvitation::where('email', 'pending@example.com')->count())->toBe(1);
});

test('email is normalized to lowercase', function () {
    Mail::fake();

    [$user, $team] = createUserWithTeam();

    $this->actingAs($user)->post('/team-invitations', [
        'email' => 'UPPERCASE@EXAMPLE.COM',
    ]);

    $this->assertDatabaseHas('team_invitations', [
        'team_id' => $team->id,
        'email' => 'uppercase@example.com',
    ]);
});

test('duplicate check is case insensitive', function () {
    [$user, $team] = createUserWithTeam();

    TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'test@example.com',
        'expires_at' => now()->addHours(48),
    ]);

    $response = $this->actingAs($user)->post('/team-invitations', [
        'email' => 'TEST@EXAMPLE.COM',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('team owner can revoke invitation', function () {
    [$user, $team] = createUserWithTeam();

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'revoke@example.com',
    ]);

    $response = $this->actingAs($user)->delete("/team-invitations/{$invitation->id}");

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('non-owner cannot revoke invitation', function () {
    [$owner, $team] = createUserWithTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member->id, ['role' => 'member']);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'revoke@example.com',
    ]);

    $this->actingAs($member)->delete("/team-invitations/{$invitation->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('user can accept valid invitation', function () {
    [$owner, $team] = createUserWithTeam();
    $invitee = User::factory()->create();

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => $invitee->email,
        'expires_at' => now()->addHours(48),
    ]);

    $response = $this->actingAs($invitee)->get("/invitations/{$invitation->token}/accept");

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('success');

    expect($team->users()->where('user_id', $invitee->id)->exists())->toBeTrue();
    expect($invitee->fresh()->current_team_id)->toBe($team->id);

    $this->assertDatabaseMissing('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('expired invitation shows error page', function () {
    [$owner, $team] = createUserWithTeam();
    $invitee = User::factory()->create();

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => $invitee->email,
        'expires_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($invitee)->get("/invitations/{$invitation->token}/accept");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Invitations/Expired'));
});

test('invalid token shows error page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/invitations/invalid-token/accept');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Invitations/Invalid'));
});

test('unauthenticated user is redirected to login', function () {
    [$owner, $team] = createUserWithTeam();

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'newuser@example.com',
        'expires_at' => now()->addHours(48),
    ]);

    $response = $this->get("/invitations/{$invitation->token}/accept");

    $response->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(url("/invitations/{$invitation->token}/accept"));
});

test('already a member shows message and deletes invitation', function () {
    [$owner, $team] = createUserWithTeam();

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => $owner->email,
        'expires_at' => now()->addHours(48),
    ]);

    $response = $this->actingAs($owner)->get("/invitations/{$invitation->token}/accept");

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('info');

    $this->assertDatabaseMissing('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('invitation expires after 48 hours', function () {
    [$user, $team] = createUserWithTeam();

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'expires_at' => now()->addHours(48),
    ]);

    expect($invitation->isExpired())->toBeFalse();

    $this->travel(49)->hours();

    expect($invitation->fresh()->isExpired())->toBeTrue();
});
