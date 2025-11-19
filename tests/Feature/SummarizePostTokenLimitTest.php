<?php

use App\Jobs\SummarizePost;
use App\Models\Post;
use App\Models\Team;
use App\Models\TokenUsageLog;
use App\Models\User;
use App\Services\OpenAIService;
use App\Services\TokenLimitService;

use function Pest\Laravel\mock;

test('summarization job stops when team is over limit', function () {
    /** @var User $user */
    $user = User::factory()->create();
    /** @var Team $team */
    $team = Team::factory()->create(['owner_id' => $user->id, 'monthly_token_limit' => 1000]);
    $team->users()->attach($user);

    /** @var Post $post */
    $post = Post::factory()->for(
        $team->sources()->create([
            'internal_name' => 'Test Source',
            'type' => 'RSS',
            'url' => 'https://example.com/rss',
            'monitoring_interval' => 'DAILY',
            'is_active' => true,
            'should_notify' => false,
            'auto_summarize' => true,
        ])
    )->create([
        'uri' => 'https://example.com/article-1',
        'summary' => null,
    ]);

    TokenUsageLog::create([
        'team_id' => $team->id,
        'user_id' => null,
        'input_tokens' => 2000,
        'output_tokens' => 0,
        'total_tokens' => 2000,
        'model' => 'gpt-4',
        'operation' => 'manual',
        'created_at' => now(),
    ]);

    mock(OpenAIService::class, function ($mock) {
        $mock->shouldReceive('summarizePost')->never();
        $mock->shouldReceive('trackUsage')->never();
    });

    (new SummarizePost($post))->handle(
        app(OpenAIService::class),
        app(TokenLimitService::class)
    );

    $post->refresh();

    expect($post->summary)->toBeNull();
});
