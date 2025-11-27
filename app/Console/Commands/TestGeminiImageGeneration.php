<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestGeminiImageGeneration extends Command
{
    protected $signature = 'gemini:test-image
                            {--model=gemini-2.5-flash-image : The model to use}
                            {--prompt=A serene mountain landscape at sunset : The prompt for image generation}
                            {--timeout=120 : Request timeout in seconds}
                            {--aspect=16:9 : Aspect ratio}
                            {--size=1K : Image size for Gemini 3 (1K, 2K, 4K)}';

    protected $description = 'Test Gemini image generation with different models and configurations';

    public function handle(): int
    {
        $model = $this->option('model');
        $prompt = $this->option('prompt');
        $timeout = (int) $this->option('timeout');
        $aspectRatio = $this->option('aspect');
        $imageSize = $this->option('size');
        $apiKey = config('gemini.api_key');

        if (empty($apiKey)) {
            $this->error('GEMINI_API_KEY is not configured');

            return self::FAILURE;
        }

        $this->info('Testing Gemini Image Generation');
        $this->table(['Setting', 'Value'], [
            ['Model', $model],
            ['Prompt', substr($prompt, 0, 50).'...'],
            ['Timeout', $timeout.'s'],
            ['Aspect Ratio', $aspectRatio],
            ['Image Size', $imageSize],
        ]);

        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            $model,
            $apiKey
        );

        // Build request body based on model
        $requestBody = $this->buildRequestBody($model, $prompt, $aspectRatio, $imageSize);

        $this->newLine();
        $this->info('Request body:');
        $this->line(json_encode($requestBody, JSON_PRETTY_PRINT));
        $this->newLine();

        $this->info('Sending request to Gemini API...');
        $startTime = microtime(true);

        try {
            $response = Http::timeout($timeout)
                ->withOptions([
                    'debug' => $this->output->isVerbose(),
                ])
                ->post($endpoint, $requestBody);

            $elapsed = round(microtime(true) - $startTime, 2);

            $this->newLine();
            $this->info("Response received in {$elapsed}s");
            $this->line("Status: {$response->status()}");

            if ($response->failed()) {
                $this->error('Request failed:');
                $this->line($response->body());

                return self::FAILURE;
            }

            $data = $response->json();

            // Check for image in response
            $candidates = $data['candidates'] ?? [];
            if (empty($candidates)) {
                $this->error('No candidates in response');
                $this->line(json_encode($data, JSON_PRETTY_PRINT));

                return self::FAILURE;
            }

            $parts = $candidates[0]['content']['parts'] ?? [];
            $imageFound = false;
            $textContent = null;

            foreach ($parts as $part) {
                // Check for image data (both camelCase and snake_case formats)
                $inlineData = $part['inlineData'] ?? $part['inline_data'] ?? null;

                if ($inlineData) {
                    $imageFound = true;
                    $mimeType = $inlineData['mimeType'] ?? $inlineData['mime_type'] ?? 'unknown';
                    $base64Data = $inlineData['data'] ?? '';
                    $dataLength = strlen($base64Data);
                    $this->info("Image found: {$mimeType}, {$dataLength} bytes (base64)");

                    // Optionally save the image
                    if ($this->confirm('Save image to storage/app/gemini-test.png?', true)) {
                        $imageData = base64_decode($base64Data);
                        $extension = match ($mimeType) {
                            'image/jpeg', 'image/jpg' => 'jpg',
                            'image/png' => 'png',
                            'image/webp' => 'webp',
                            default => 'png',
                        };
                        $path = storage_path("app/gemini-test.{$extension}");
                        file_put_contents($path, $imageData);
                        $this->info("Image saved to: {$path}");
                    }
                }

                if (isset($part['text'])) {
                    $textContent = $part['text'];
                }
            }

            if ($textContent) {
                $this->newLine();
                $this->info('Text response:');
                $this->line($textContent);
            }

            if (! $imageFound) {
                $this->warn('No image found in response');
                $this->line(json_encode($data, JSON_PRETTY_PRINT));

                return self::FAILURE;
            }

            $this->newLine();
            $this->info('Test completed successfully!');

            return self::SUCCESS;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $elapsed = round(microtime(true) - $startTime, 2);
            $this->error("Connection error after {$elapsed}s: {$e->getMessage()}");

            return self::FAILURE;
        } catch (\Exception $e) {
            $elapsed = round(microtime(true) - $startTime, 2);
            $this->error("Error after {$elapsed}s: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function buildRequestBody(string $model, string $prompt, string $aspectRatio, string $imageSize): array
    {
        // Model-specific configuration
        if (str_contains($model, 'gemini-3')) {
            // gemini-3-pro-image-preview uses a simpler format with role
            // Based on working example code
            $this->info('Using gemini-3 configuration (simple format with role)');

            return [
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
                        'aspectRatio' => $aspectRatio,
                        'imageSize' => $imageSize,
                    ],
                ],
            ];
        }

        // gemini-2.x models use responseModalities
        $this->info('Using gemini-2 configuration with responseModalities');

        return [
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
                    'aspectRatio' => $aspectRatio,
                ],
            ],
        ];
    }
}
