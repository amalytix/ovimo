<?php

namespace Database\Factories;

use App\Models\BackgroundSource;
use App\Models\ContentPiece;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BackgroundSource>
 */
class BackgroundSourceFactory extends Factory
{
    private static int $sortOrder = 0;

    public function definition(): array
    {
        return [
            'content_piece_id' => ContentPiece::factory(),
            'type' => BackgroundSource::TYPE_MANUAL,
            'post_id' => null,
            'title' => fake()->sentence(4),
            'content' => fake()->paragraphs(2, true),
            'url' => fake()->optional(0.5)->url(),
            'sort_order' => self::$sortOrder++,
        ];
    }

    public function manual(): self
    {
        return $this->state(fn () => [
            'type' => BackgroundSource::TYPE_MANUAL,
            'post_id' => null,
            'title' => fake()->sentence(4),
            'content' => fake()->paragraphs(2, true),
        ]);
    }

    public function fromPost(): self
    {
        return $this->state(fn () => [
            'type' => BackgroundSource::TYPE_POST,
            'post_id' => Post::factory(),
            'title' => null,
            'content' => null,
            'url' => null,
        ]);
    }
}
