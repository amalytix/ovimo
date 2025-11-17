<?php

use App\Models\TokenUsageLog;

test('guests cannot access usage page', function () {
    $this->get('/usage')->assertRedirect('/login');
});

test('authenticated users can view usage index', function () {
    [$user, $team] = createUserWithTeam();

    $this->actingAs($user)
        ->get('/usage')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Usage/Index'));
});

test('usage page displays total stats', function () {
    [$user, $team] = createUserWithTeam();

    TokenUsageLog::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'input_tokens' => 100,
        'output_tokens' => 50,
        'total_tokens' => 150,
        'model' => 'gpt-4',
        'operation' => 'summarization',
        'created_at' => now(),
    ]);

    TokenUsageLog::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'input_tokens' => 200,
        'output_tokens' => 100,
        'total_tokens' => 300,
        'model' => 'gpt-4',
        'operation' => 'content_generation',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/usage');

    $response->assertInertia(fn ($page) => $page
        ->where('totalStats.total_input', 300)
        ->where('totalStats.total_output', 150)
        ->where('totalStats.total_tokens', 450)
        ->where('totalStats.total_requests', 2)
    );
});

test('usage page only shows team usage', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

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

    TokenUsageLog::create([
        'team_id' => $otherTeam->id,
        'user_id' => $otherUser->id,
        'input_tokens' => 500,
        'output_tokens' => 250,
        'total_tokens' => 750,
        'model' => 'gpt-4',
        'operation' => 'test',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/usage');

    $response->assertInertia(fn ($page) => $page
        ->where('totalStats.total_tokens', 150)
        ->where('totalStats.total_requests', 1)
    );
});

test('usage page displays by operation breakdown', function () {
    [$user, $team] = createUserWithTeam();

    TokenUsageLog::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'input_tokens' => 100,
        'output_tokens' => 50,
        'total_tokens' => 150,
        'model' => 'gpt-4',
        'operation' => 'summarization',
        'created_at' => now(),
    ]);

    TokenUsageLog::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'input_tokens' => 200,
        'output_tokens' => 100,
        'total_tokens' => 300,
        'model' => 'gpt-4',
        'operation' => 'content_generation',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/usage');

    $response->assertInertia(fn ($page) => $page
        ->has('byOperation', 2)
    );
});

test('usage page displays by model breakdown', function () {
    [$user, $team] = createUserWithTeam();

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

    TokenUsageLog::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'input_tokens' => 200,
        'output_tokens' => 100,
        'total_tokens' => 300,
        'model' => 'gpt-3.5-turbo',
        'operation' => 'test',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/usage');

    $response->assertInertia(fn ($page) => $page
        ->has('byModel', 2)
    );
});

test('usage page displays recent logs', function () {
    [$user, $team] = createUserWithTeam();

    TokenUsageLog::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'input_tokens' => 100,
        'output_tokens' => 50,
        'total_tokens' => 150,
        'model' => 'gpt-4',
        'operation' => 'summarization',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/usage');

    $response->assertInertia(fn ($page) => $page
        ->has('recentLogs', 1)
        ->where('recentLogs.0.operation', 'summarization')
        ->where('recentLogs.0.model', 'gpt-4')
        ->where('recentLogs.0.total_tokens', 150)
    );
});

test('usage page displays daily usage', function () {
    [$user, $team] = createUserWithTeam();

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

    $response = $this->actingAs($user)->get('/usage');

    $response->assertInertia(fn ($page) => $page
        ->has('dailyUsage')
    );
});

test('usage page handles empty state', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->get('/usage');

    $response->assertInertia(fn ($page) => $page
        ->where('totalStats.total_tokens', 0)
        ->where('totalStats.total_requests', 0)
        ->has('byOperation', 0)
        ->has('byModel', 0)
        ->has('recentLogs', 0)
    );
});
