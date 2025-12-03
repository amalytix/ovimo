<?php

namespace App\Services;

use App\Exceptions\AINotConfiguredException;
use App\Models\Team;
use Illuminate\Contracts\Foundation\Application;

class AIServiceFactory
{
    public function __construct(private Application $app) {}

    public function forOpenAI(Team $team): OpenAIService
    {
        if (! $team->hasOpenAIConfigured()) {
            throw new AINotConfiguredException('OpenAI API key is not configured for this team.', 'openai');
        }

        $service = $this->app->make(OpenAIService::class);
        $service->configureForTeam($team->openai_api_key, $team->openai_model);

        return $service;
    }

    public function forGemini(Team $team): GeminiService
    {
        if (! $team->hasGeminiConfigured()) {
            throw new AINotConfiguredException('Gemini API key is not configured for this team.', 'gemini');
        }

        $service = $this->app->make(GeminiService::class);
        $service->configureForTeam($team->gemini_api_key, $team->gemini_image_model, $team->gemini_image_size);

        return $service;
    }
}
