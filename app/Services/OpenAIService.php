<?php

namespace App\Services;

use App\Exceptions\AINotConfiguredException;
use App\Models\Team;
use App\Models\TokenUsageLog;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use OpenAI;

class OpenAIService
{
    private ?OpenAI\Client $client = null;

    private ?string $configuredApiKey = null;

    private ?string $configuredModel = null;

    private int $timeout;

    public function __construct(private TokenLimitService $tokenLimitService)
    {
        $this->timeout = (int) config('openai.request_timeout', 300);
    }

    public function configureForTeam(string $apiKey, ?string $model = null): self
    {
        $this->configuredApiKey = $apiKey;
        $this->configuredModel = $model ?: config('openai.model', 'gpt-5.1');

        $this->client = OpenAI::factory()
            ->withApiKey($apiKey)
            ->withHttpClient(new Client([
                'timeout' => $this->timeout,
            ]))
            ->make();

        return $this;
    }

    private function ensureConfigured(): void
    {
        if (! $this->client || ! $this->configuredApiKey) {
            throw new AINotConfiguredException('OpenAI is not configured for this team.', 'openai');
        }
    }

    /**
     * @return array{summary: string, relevancy_score: int, internal_title: string, input_tokens: int, output_tokens: int, total_tokens: int, model: string}
     */
    public function summarizePost(string $uri, Team $team): array
    {
        $this->ensureConfigured();

        $relevancyPrompt = $team->relevancy_prompt ?? 'You are a news analyst. Rate the relevancy of content for a business news monitoring system.';

        $prompt = <<<PROMPT
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

        Analyze this URL: {$uri}
        PROMPT;

        $model = 'gpt-5-mini';

        Log::debug('OpenAI API Request - summarizePost', [
            'model' => $model,
            'input' => $prompt,
        ]);

        $response = $this->client->responses()->create([
            'model' => $model,
            'input' => $prompt,
            'text' => [
                'format' => ['type' => 'json_object'],
            ],
        ]);

        $content = $response->outputText;
        $data = json_decode($content, true);

        return [
            'summary' => $data['summary'] ?? 'Unable to generate summary',
            'relevancy_score' => min(100, max(0, (int) ($data['relevancy_score'] ?? 50))),
            'internal_title' => $data['title'] ?? 'Untitled',
            'input_tokens' => $response->usage->inputTokens,
            'output_tokens' => $response->usage->outputTokens,
            'total_tokens' => $response->usage->totalTokens,
            'model' => $model,
        ];
    }

    /**
     * Analyze a webpage HTML to suggest CSS selectors for extracting post titles and links.
     *
     * @return array{css_selector_title: string, css_selector_link: string, input_tokens: int, output_tokens: int, total_tokens: int, model: string}
     */
    public function analyzeWebpage(string $html): array
    {
        $this->ensureConfigured();

        $prompt = <<<PROMPT
        You are an expert web scraper. Analyze the following HTML and identify CSS selectors that can extract article/post titles and their corresponding links.

        The page contains a list of articles or posts. Your task is to:
        1. Identify the repeating pattern for each article/post item
        2. Provide a CSS selector for the title text
        3. Provide a CSS selector for the link URL (href attribute)

        Important:
        - The selectors should match ALL posts on the page, not just one
        - For the link selector, target the <a> element so we can extract the href attribute
        - Use specific, reliable selectors that won't break easily

        Respond in JSON format only:
        {
            "css_selector_title": "selector for title text",
            "css_selector_link": "selector for link element"
        }

        HTML to analyze:
        {$html}
        PROMPT;

        $model = 'gpt-5.1';

        Log::debug('OpenAI API Request - analyzeWebpage', [
            'model' => $model,
            'html_length' => strlen($html),
        ]);

        $response = $this->client->responses()->create([
            'model' => $model,
            'input' => $prompt,
            'reasoning' => [
                'effort' => 'low',
            ],
            'text' => [
                'format' => ['type' => 'json_object'],
            ],
        ]);

        $content = $response->outputText;
        $data = json_decode($content, true);

        return [
            'css_selector_title' => $data['css_selector_title'] ?? '',
            'css_selector_link' => $data['css_selector_link'] ?? '',
            'input_tokens' => $response->usage->inputTokens,
            'output_tokens' => $response->usage->outputTokens,
            'total_tokens' => $response->usage->totalTokens,
            'model' => $model,
        ];
    }

    /**
     * @return array{content: string, input_tokens: int, output_tokens: int, total_tokens: int, model: string}
     */
    public function generateContent(string $prompt, string $context): array
    {
        $this->ensureConfigured();

        // Extend PHP execution time for long-running AI requests
        set_time_limit(config('openai.request_timeout', 300));

        $model = $this->configuredModel ?? config('openai.model', 'gpt-5.1');

        Log::debug('OpenAI API Request - generateContent', [
            'model' => $model,
            'input' => $prompt,
        ]);

        $response = $this->client->responses()->create([
            'model' => $model,
            'instructions' => 'You are a professional content writer. Generate high-quality content based on the provided context and instructions.',
            'input' => $prompt,
            'reasoning' => [
                'effort' => 'low',
            ],
        ]);

        return [
            'content' => $response->outputText,
            'input_tokens' => $response->usage->inputTokens,
            'output_tokens' => $response->usage->outputTokens,
            'total_tokens' => $response->usage->totalTokens,
            'model' => $model,
        ];
    }

    /**
     * Generate an image prompt based on content text and a prompt template.
     *
     * @return array{prompt: string, input_tokens: int, output_tokens: int, total_tokens: int, model: string}
     */
    public function generateImagePrompt(string $contentText, string $promptTemplate): array
    {
        $this->ensureConfigured();

        $model = 'gpt-5-mini';

        // Replace {{content}} placeholder with actual content
        $prompt = str_replace('{{content}}', $contentText, $promptTemplate);

        Log::debug('OpenAI API Request - generateImagePrompt', [
            'model' => $model,
            'prompt_length' => strlen($prompt),
        ]);

        $response = $this->client->responses()->create([
            'model' => $model,
            'instructions' => 'You are an expert at creating detailed, visually descriptive image prompts for AI image generators. Generate a concise but detailed prompt (2-4 sentences) that describes the image to be created. Focus on visual elements: composition, lighting, colors, mood, style, and key subjects. Do not include any explanatory text - just output the image prompt itself.',
            'input' => $prompt,
        ]);

        return [
            'prompt' => trim($response->outputText),
            'input_tokens' => $response->usage->inputTokens,
            'output_tokens' => $response->usage->outputTokens,
            'total_tokens' => $response->usage->totalTokens,
            'model' => $model,
        ];
    }

    public function trackUsage(int $inputTokens, int $outputTokens, int $totalTokens, string $model, ?User $user, Team $team, string $operation): void
    {
        $this->tokenLimitService->assertWithinLimit($team, $totalTokens, $user, $operation);

        TokenUsageLog::create([
            'user_id' => $user?->id,
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
