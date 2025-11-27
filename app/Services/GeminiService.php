<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TokenUsageLog;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiService
{
    private string $apiKey;

    private string $model;

    private int $timeout;

    private string $imageSize;

    public function __construct(private TokenLimitService $tokenLimitService)
    {
        $this->apiKey = (string) config('gemini.api_key', '');
        $this->model = config('gemini.image_model', 'gemini-3-pro-image-preview');
        $this->timeout = config('gemini.request_timeout', 120);
        $this->imageSize = config('gemini.image_size', '1K');
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
        $aspect = $this->mapAspectRatio($aspectRatio);

        if (str_contains($this->model, 'gemini-3')) {
            // gemini-3-pro-image-preview expects generationConfig.imageConfig
            Log::info('Using gemini-3 request format (simple with imageConfig)');
            $requestBody = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseModalities' => ['IMAGE'],
                    'imageConfig' => [
                        'aspectRatio' => $aspect,
                        'imageSize' => $this->imageSize,
                    ],
                ],
            ];
        } else {
            // gemini-2.x models use responseModalities
            Log::info('Using gemini-2 request format (with responseModalities)');
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
                    'imageConfig' => [
                        'aspectRatio' => $aspect,
                    ],
                ],
            ];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->retry(2, 5000, function ($exception, $request) {
                    Log::warning('Gemini API retry triggered', [
                        'exception' => $exception->getMessage(),
                    ]);

                    return true;
                })
                ->post($endpoint, $requestBody);
        } catch (RequestException $e) {
            $response = $e->response;

            if ($response && $response->status() === 503) {
                $errorBody = json_decode($response->body(), true);
                $errorMessage = $errorBody['error']['message'] ?? $response->body();

                if (str_contains(strtolower($errorMessage), 'overloaded')) {
                    throw new RuntimeException('Gemini model is overloaded. Please try again later.', 0, $e);
                }
            }

            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        $elapsed = round(microtime(true) - $startTime, 2);

        if ($response->failed()) {
            $errorBody = json_decode($response->body(), true);
            $errorMessage = $errorBody['error']['message'] ?? $response->body();

            if ($response->status() === 503 && str_contains(strtolower($errorMessage), 'overloaded')) {
                Log::warning('Gemini API overloaded', [
                    'status' => $response->status(),
                    'message' => $errorMessage,
                    'elapsed_seconds' => $elapsed,
                ]);

                throw new RuntimeException('Gemini model is overloaded. Please try again later.');
            }

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
            // Handle both camelCase (inlineData) and snake_case (inline_data) response formats
            $inlineData = $part['inlineData'] ?? $part['inline_data'] ?? null;

            if ($inlineData) {
                return [
                    'image_data' => $inlineData['data'],
                    'mime_type' => $inlineData['mimeType'] ?? $inlineData['mime_type'] ?? 'image/png',
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
            '4:5' => '4:5',
            default => '4:5',
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
