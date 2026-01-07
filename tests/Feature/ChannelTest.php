<?php

use App\Models\Channel;

test('guests cannot access channels', function () {
    $this->get('/channels')->assertRedirect('/login');
});

test('authenticated users can view channels index', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->get('/channels');

    $response->assertSuccessful();
    $response->assertJsonStructure(['channels']);
});

test('channels index only shows team channels', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

    $teamChannel = Channel::factory()->create(['team_id' => $team->id]);
    $otherChannel = Channel::factory()->create(['team_id' => $otherTeam->id]);

    $response = $this->actingAs($user)->get('/channels');

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'channels');
    $response->assertJsonPath('channels.0.id', $teamChannel->id);
});

test('authenticated users can create a channel', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/channels', [
        'name' => 'Test Channel',
        'icon' => '📝',
        'color' => '#3b82f6',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('channels', [
        'team_id' => $team->id,
        'name' => 'Test Channel',
        'icon' => '📝',
        'color' => '#3b82f6',
        'is_active' => true,
    ]);
});

test('channel creation validates required fields', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->post('/channels', []);

    $response->assertSessionHasErrors(['name']);
});

test('authenticated users can update their channels', function () {
    [$user, $team] = createUserWithTeam();
    $channel = Channel::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->put("/channels/{$channel->id}", [
        'name' => 'Updated Channel',
        'icon' => '🚀',
        'color' => '#10b981',
        'is_active' => false,
    ]);

    $response->assertRedirect();

    $channel->refresh();
    expect($channel->name)->toBe('Updated Channel');
    expect($channel->icon)->toBe('🚀');
    expect($channel->color)->toBe('#10b981');
    expect($channel->is_active)->toBeFalse();
});

test('users cannot update channels from other teams', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $otherChannel = Channel::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->put("/channels/{$otherChannel->id}", [
            'name' => 'Updated',
        ])
        ->assertForbidden();
});

test('authenticated users can delete their channels', function () {
    [$user, $team] = createUserWithTeam();
    $channel = Channel::factory()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->delete("/channels/{$channel->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('channels', ['id' => $channel->id]);
});

test('users cannot delete channels from other teams', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();
    $otherChannel = Channel::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->delete("/channels/{$otherChannel->id}")
        ->assertForbidden();
});

test('channels can be reordered', function () {
    [$user, $team] = createUserWithTeam();
    $channel1 = Channel::factory()->create(['team_id' => $team->id, 'sort_order' => 0]);
    $channel2 = Channel::factory()->create(['team_id' => $team->id, 'sort_order' => 1]);
    $channel3 = Channel::factory()->create(['team_id' => $team->id, 'sort_order' => 2]);

    $response = $this->actingAs($user)->post('/channels/reorder', [
        'channel_ids' => [$channel3->id, $channel1->id, $channel2->id],
    ]);

    $response->assertSuccessful();

    $channel1->refresh();
    $channel2->refresh();
    $channel3->refresh();

    expect($channel3->sort_order)->toBe(0);
    expect($channel1->sort_order)->toBe(1);
    expect($channel2->sort_order)->toBe(2);
});
