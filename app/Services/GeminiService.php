<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TokenUsageLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiService
{
    private string $apiKey;

    private string $model;

    private int $timeout;

    public function __construct(private TokenLimitService $tokenLimitService)
    {
        $this->apiKey = config('gemini.api_key');
        $this->model = config('gemini.image_model', 'gemini-3-pro-image-preview');
        $this->timeout = config('gemini.request_timeout', 120);
    }

    /**
     * Generate an image using Google Gemini.
     *
     * @return array{image_data: string, mime_type: string}
     */
    public function generateImage(string $prompt, string $aspectRatio): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('Gemini API key is not configured');
        }

        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            $this->model,
            $this->apiKey
        );

        Log::info('Gemini API Request - generateImage', [
            'model' => $this->model,
            'prompt_length' => strlen($prompt),
            'aspect_ratio' => $aspectRatio,
            'timeout' => $this->timeout,
            'endpoint' => $endpoint,
        ]);

        $startTime = microtime(true);

        // Build request body based on model
        $requestBody = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
            ],
        ];

        // gemini-2.5-flash-image uses responseModalities, gemini-3-pro-image-preview uses imageConfig
        if (str_contains($this->model, 'gemini-3')) {
            $requestBody['generationConfig'] = [
                'imageConfig' => [
                    'aspectRatio' => $this->mapAspectRatio($aspectRatio),
                    'imageSize' => '2K',
                ],
            ];
        }

        $response = Http::timeout($this->timeout)
            ->retry(2, 5000, function ($exception, $request) {
                Log::warning('Gemini API retry triggered', [
                    'exception' => $exception->getMessage(),
                ]);

                return true;
            })
            ->post($endpoint, $requestBody);

        $elapsed = round(microtime(true) - $startTime, 2);

        if ($response->failed()) {
            Log::error('Gemini API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'elapsed_seconds' => $elapsed,
            ]);
            throw new RuntimeException('Gemini API request failed: '.$response->body());
        }

        Log::info('Gemini API Response received', [
            'status' => $response->status(),
            'elapsed_seconds' => $elapsed,
        ]);

        $data = $response->json();

        // Extract image from response
        $candidates = $data['candidates'] ?? [];
        if (empty($candidates)) {
            throw new RuntimeException('Gemini API returned no candidates');
        }

        $parts = $candidates[0]['content']['parts'] ?? [];
        foreach ($parts as $part) {
            if (isset($part['inlineData'])) {
                return [
                    'image_data' => $part['inlineData']['data'],
                    'mime_type' => $part['inlineData']['mimeType'] ?? 'image/png',
                ];
            }
        }

        throw new RuntimeException('Gemini API response did not contain an image');
    }

    /**
     * Map user-friendly aspect ratio to Gemini API format.
     */
    private function mapAspectRatio(string $aspectRatio): string
    {
        return match ($aspectRatio) {
            '16:9' => '16:9',
            '1:1' => '1:1',
            '4:3' => '4:3',
            '9:16' => '9:16',
            '3:4' => '3:4',
            default => '16:9',
        };
    }

    public function trackUsage(?User $user, Team $team, string $operation): void
    {
        // Gemini image generation doesn't provide token counts in the same way as text models
        // We'll track it as a fixed cost per image generation for now
        $estimatedTokens = 1000;

        $this->tokenLimitService->assertWithinLimit($team, $estimatedTokens, $user, $operation);

        TokenUsageLog::create([
            'user_id' => $user?->id,
            'team_id' => $team->id,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'total_tokens' => $estimatedTokens,
            'model' => $this->model,
            'operation' => $operation,
            'created_at' => now(),
        ]);
    }
}
