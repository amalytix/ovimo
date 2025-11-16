<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Source>
 */
class SourceFactory extends Factory
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
            'type' => fake()->randomElement(['RSS', 'XML_SITEMAP']),
            'url' => fake()->url(),
            'monitoring_interval' => fake()->randomElement([
                'EVERY_10_MIN', 'EVERY_30_MIN', 'HOURLY',
                'EVERY_6_HOURS', 'DAILY', 'WEEKLY',
            ]),
            'is_active' => true,
            'should_notify' => true,
            'auto_summarize' => true,
            'last_checked_at' => null,
            'next_check_at' => now(),
        ];
    }
}
