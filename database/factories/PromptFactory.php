<?php

namespace Database\Factories;

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
            'internal_name' => fake()->words(3, true),
            'channel' => fake()->randomElement(['BLOG_POST', 'LINKEDIN_POST', 'YOUTUBE_SCRIPT']),
            'prompt_text' => fake()->paragraphs(3, true),
        ];
    }
}
