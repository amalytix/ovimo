<?php

uses(Tests\TestCase::class);

use App\Services\GeminiService;
use App\Services\TokenLimitService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('throws when api key missing', function () {
    Config::set('gemini.api_key', null);

    $tokenLimitService = mock(TokenLimitService::class);

    $service = new GeminiService($tokenLimitService);

    expect(fn () => $service->generateImage('prompt', '16:9'))
        ->toThrow(RuntimeException::class, 'Gemini API key is not configured');
});

it('surfaces overloaded errors clearly', function () {
    Config::set([
        'gemini.api_key' => 'test-key',
        'gemini.image_model' => 'gemini-3-pro-image-preview',
    ]);

    Http::fake([
        '*' => Http::response([
            'error' => [
                'code' => 503,
                'message' => 'The model is overloaded. Please try again later.',
                'status' => 'UNAVAILABLE',
            ],
        ], 503),
    ]);

    $tokenLimitService = mock(TokenLimitService::class);

    $service = new GeminiService($tokenLimitService);

    $caught = null;

    try {
        $service->generateImage('a prompt', '16:9');
    } catch (RuntimeException $e) {
        $caught = $e;
    }

    expect($caught)->not->toBeNull();
    expect($caught->getMessage())->toBe('Gemini model is overloaded. Please try again later.');
});
