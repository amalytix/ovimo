<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TokenUsageLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use OpenAI;

class OpenAIService
{
    private OpenAI\Client $client;

    public function __construct()
    {
        $this->client = OpenAI::client(config('openai.api_key'));
    }

    /**
     * @return array{summary: string, relevancy_score: int, internal_title: string, input_tokens: int, output_tokens: int, total_tokens: int, model: string}
     */
    public function summarizePost(string $uri, Team $team): array
    {
        $relevancyPrompt = $team->relevancy_prompt ?? 'You are a news analyst. Rate the relevancy of content for a business news monitoring system.';

        $systemPrompt = <<<PROMPT
        {$relevancyPrompt}

        For the given URL, provide:
        1. A concise title (5-10 words) that captures the main topic
        2. A concise summary (2-3 sentences) of what the content is about
        3. A relevancy score from 0-100 based on how relevant this content is

        Respond in JSON format only:
        {
            "title": "Your concise title here",
            "summary": "Your summary here",
            "relevancy_score": 75
        }
        PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Analyze this URL: {$uri}"],
        ];

        Log::debug('OpenAI API Request - summarizePost', [
            'model' => config('openai.model', 'gpt-4o'),
            'messages' => $messages,
            'max_tokens' => 500,
        ]);

        $response = $this->client->chat()->create([
            'model' => config('openai.model', 'gpt-4o'),
            'messages' => $messages,
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 500,
        ]);

        $content = $response->choices[0]->message->content;
        $data = json_decode($content, true);

        return [
            'summary' => $data['summary'] ?? 'Unable to generate summary',
            'relevancy_score' => min(100, max(0, (int) ($data['relevancy_score'] ?? 50))),
            'internal_title' => $data['title'] ?? 'Untitled',
            'input_tokens' => $response->usage->promptTokens,
            'output_tokens' => $response->usage->completionTokens,
            'total_tokens' => $response->usage->totalTokens,
            'model' => config('openai.model', 'gpt-4o'),
        ];
    }

    /**
     * @return array{content: string, input_tokens: int, output_tokens: int, total_tokens: int, model: string}
     */
    public function generateContent(string $prompt, string $context): array
    {
        $messages = [
            ['role' => 'system', 'content' => 'You are a professional content writer. Generate high-quality content based on the provided context and instructions.'],
            ['role' => 'user', 'content' => "{$prompt}"],
        ];

        Log::debug('OpenAI API Request - generateContent', [
            'model' => config('openai.model', 'gpt-4o'),
            'messages' => $messages,
            'max_tokens' => 2000,
        ]);

        $response = $this->client->chat()->create([
            'model' => config('openai.model', 'gpt-4o'),
            'messages' => $messages,
            'max_tokens' => 2000,
        ]);

        return [
            'content' => $response->choices[0]->message->content,
            'input_tokens' => $response->usage->promptTokens,
            'output_tokens' => $response->usage->completionTokens,
            'total_tokens' => $response->usage->totalTokens,
            'model' => config('openai.model', 'gpt-4o'),
        ];
    }

    public function trackUsage(int $inputTokens, int $outputTokens, int $totalTokens, string $model, User $user, Team $team, string $operation): void
    {
        TokenUsageLog::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $totalTokens,
            'model' => $model,
            'operation' => $operation,
            'created_at' => now(),
        ]);
    }
}
