<?php

use App\Jobs\GenerateContentPiece;
use App\Models\ContentPiece;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\TokenUsageLog;
use App\Models\User;
use App\Services\OpenAIService;
use App\Services\TokenLimitService;
use App\Services\WebContentExtractor;

use function Pest\Laravel\mock;

test('content generation job stops when team is over limit', function () {
    /** @var User $user */
    $user = User::factory()->create();
    /** @var Team $team */
    $team = Team::factory()->create(['owner_id' => $user->id, 'monthly_token_limit' => 1000]);
    $team->users()->attach($user);

    $prompt = Prompt::factory()->for($team)->create([
        'prompt_text' => 'Write: {{context}}',
    ]);

    /** @var ContentPiece $piece */
    $piece = ContentPiece::factory()->for($team)->create([
        'prompt_id' => $prompt->id,
        'status' => 'NOT_STARTED',
        'generation_status' => 'NOT_STARTED',
        'research_text' => null,
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
        $mock->shouldReceive('generateContent')->never();
        $mock->shouldReceive('trackUsage')->never();
    });

    mock(WebContentExtractor::class, function ($mock) {
        $mock->shouldReceive('extractArticleAsMarkdown')->never();
    });

    (new GenerateContentPiece($piece))->handle(
        app(OpenAIService::class),
        app(WebContentExtractor::class),
        app(TokenLimitService::class)
    );

    $piece->refresh();

    expect($piece->generation_status)->toBe('FAILED')
        ->and($piece->generation_error)->not->toBeNull();
});
