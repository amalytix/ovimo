<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Webhook>
 */
class WebhookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'url' => fake()->url(),
            'event' => fake()->randomElement(['NEW_POSTS', 'HIGH_RELEVANCY_POST', 'CONTENT_GENERATED']),
            'is_active' => true,
            'secret' => fake()->optional()->uuid(),
            'failure_count' => 0,
        ];
    }
}
