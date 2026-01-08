<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContentPiece>
 */
class ContentPieceFactory extends Factory
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
            'briefing_text' => fake()->paragraphs(2, true),
            'channel' => fake()->randomElement(['BLOG_POST', 'LINKEDIN_POST', 'YOUTUBE_SCRIPT']),
            'published_at' => fake()->optional(0.3)->dateTimeBetween('now', '+30 days'),
        ];
    }

    public function scheduled(): self
    {
        return $this->state(fn () => [
            'published_at' => fake()->dateTimeBetween('now', '+30 days'),
        ]);
    }

    public function unscheduled(): self
    {
        return $this->state(fn () => [
            'published_at' => null,
        ]);
    }
}
