<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Channel>
 */
class ChannelFactory extends Factory
{
    private static int $sortOrder = 0;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Blog Post',
            'LinkedIn',
            'YouTube Script',
            'Reddit',
            'Twitter/X',
            'Newsletter',
            'Press Release',
        ]);

        return [
            'team_id' => Team::factory(),
            'name' => $name,
            'language' => fake()->randomElement(['ENGLISH', 'GERMAN']),
            'icon' => fake()->randomElement(['file-text', 'linkedin', 'youtube', 'message-circle', 'twitter', 'mail']),
            'color' => fake()->randomElement(['blue-500', 'sky-600', 'red-500', 'orange-500', 'slate-700', 'green-500']),
            'sort_order' => self::$sortOrder++,
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function blogPost(): self
    {
        return $this->state(fn () => [
            'name' => 'Blog Post',
            'icon' => 'file-text',
            'color' => 'blue-500',
        ]);
    }

    public function linkedin(): self
    {
        return $this->state(fn () => [
            'name' => 'LinkedIn',
            'icon' => 'linkedin',
            'color' => 'sky-600',
        ]);
    }

    public function youtube(): self
    {
        return $this->state(fn () => [
            'name' => 'YouTube Script',
            'icon' => 'youtube',
            'color' => 'red-500',
        ]);
    }

    public function reddit(): self
    {
        return $this->state(fn () => [
            'name' => 'Reddit',
            'icon' => 'message-circle',
            'color' => 'orange-500',
        ]);
    }
}
