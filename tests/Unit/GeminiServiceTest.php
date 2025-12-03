<?php

uses(Tests\TestCase::class);

use App\Exceptions\AINotConfiguredException;
use App\Services\GeminiService;
use App\Services\TokenLimitService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('throws when api key missing', function () {
    Config::set('gemini.api_key', null);

    $tokenLimitService = mock(TokenLimitService::class);

    $service = new GeminiService($tokenLimitService);

    expect(fn () => $service->generateImage('prompt', '16:9'))
        ->toThrow(AINotConfiguredException::class, 'Gemini is not configured for this team.');
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
    $service->configureForTeam('test-key', 'gemini-3-pro-image-preview', '1K');

    $caught = null;

    try {
        $service->generateImage('a prompt', '16:9');
    } catch (RuntimeException $e) {
        $caught = $e;
    }

    expect($caught)->not->toBeNull();
    expect($caught->getMessage())->toBe('Gemini model is overloaded. Please try again later.');
});
