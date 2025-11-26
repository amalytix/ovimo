<?php

namespace Database\Factories;

use App\Models\Prompt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prompt>
 */
class PromptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => Prompt::TYPE_CONTENT,
            'internal_name' => fake()->words(3, true),
            'channel' => fake()->randomElement(['BLOG_POST', 'LINKEDIN_POST', 'YOUTUBE_SCRIPT']),
            'prompt_text' => fake()->paragraphs(3, true),
        ];
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Prompt::TYPE_IMAGE,
            'channel' => null,
            'prompt_text' => 'Generate a hero image based on: {{content}}',
        ]);
    }
}
