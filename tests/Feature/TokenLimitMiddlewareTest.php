<?php

use App\Models\TokenUsageLog;

it('allows request when team has very high token limit', function () {
    [$user, $team] = createUserWithTeam();
    $team->update(['monthly_token_limit' => 999999999]);

    // Request should pass middleware (not get 429)
    $response = $this->actingAs($user)
        ->postJson('/sources/analyze-webpage', ['url' => 'https://example.com']);

    expect($response->status())->not->toBe(429);
});

it('allows request when team has not exceeded token limit', function () {
    [$user, $team] = createUserWithTeam();
    $team->update(['monthly_token_limit' => 100000]);

    TokenUsageLog::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'input_tokens' => 1000,
        'output_tokens' => 500,
        'total_tokens' => 1500,
        'model' => 'gpt-4',
        'operation' => 'test',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->postJson('/sources/analyze-webpage', ['url' => 'https://example.com']);

    expect($response->status())->not->toBe(429);
});

it('blocks request when team has exceeded token limit', function () {
    [$user, $team] = createUserWithTeam();
    $team->update(['monthly_token_limit' => 10000]);

    TokenUsageLog::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'input_tokens' => 8000,
        'output_tokens' => 3000,
        'total_tokens' => 11000,
        'model' => 'gpt-4',
        'operation' => 'test',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->postJson('/sources/analyze-webpage', ['url' => 'https://example.com'])
        ->assertStatus(429);

    expect($response->json('message'))->toContain('Monthly token limit exceeded');
});

it('only counts current month usage for token limit', function () {
    [$user, $team] = createUserWithTeam();
    $team->update(['monthly_token_limit' => 10000]);

    // Add usage from last month (should not count)
    TokenUsageLog::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'input_tokens' => 8000,
        'output_tokens' => 3000,
        'total_tokens' => 11000,
        'model' => 'gpt-4',
        'operation' => 'test',
        'created_at' => now()->subMonth(),
    ]);

    // Add small usage this month
    TokenUsageLog::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'input_tokens' => 100,
        'output_tokens' => 50,
        'total_tokens' => 150,
        'model' => 'gpt-4',
        'operation' => 'test',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->postJson('/sources/analyze-webpage', ['url' => 'https://example.com']);

    // Should pass middleware (not get 429) since only 150 tokens used this month
    expect($response->status())->not->toBe(429);
});

it('allows request when token limit is zero (means unlimited)', function () {
    [$user, $team] = createUserWithTeam();
    $team->update(['monthly_token_limit' => 0]);

    $response = $this->actingAs($user)
        ->postJson('/sources/analyze-webpage', ['url' => 'https://example.com']);

    // Zero limit means no restriction
    expect($response->status())->not->toBe(429);
});

it('includes usage statistics in error message', function () {
    [$user, $team] = createUserWithTeam();
    $team->update(['monthly_token_limit' => 10000]);

    TokenUsageLog::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'input_tokens' => 8000,
        'output_tokens' => 3000,
        'total_tokens' => 11000,
        'model' => 'gpt-4',
        'operation' => 'test',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->postJson('/sources/analyze-webpage', ['url' => 'https://example.com'])
        ->assertStatus(429);

    $message = $response->json('message');
    expect($message)->toContain('11000')
        ->toContain('10000')
        ->toContain('110'); // Percentage
});
