<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uri' => fake()->url(),
            'external_title' => fake()->sentence(6),
            'internal_title' => fake()->sentence(5),
            'summary' => fake()->paragraph(),
            'relevancy_score' => fake()->numberBetween(0, 100),
            'is_hidden' => false,
            'status' => 'NOT_RELEVANT',
            'found_at' => now(),
        ];
    }
}
