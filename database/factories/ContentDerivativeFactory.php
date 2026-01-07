<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\ContentDerivative;
use App\Models\ContentPiece;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContentDerivative>
 */
class ContentDerivativeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'content_piece_id' => ContentPiece::factory(),
            'channel_id' => Channel::factory(),
            'prompt_id' => null,
            'title' => fake()->sentence(4),
            'text' => fake()->paragraphs(3, true),
            'status' => ContentDerivative::STATUS_NOT_STARTED,
            'is_published' => false,
            'planned_publish_at' => null,
            'published_at' => null,
            'generation_status' => ContentDerivative::GENERATION_IDLE,
            'generation_error' => null,
            'generation_error_occurred_at' => null,
        ];
    }

    public function draft(): self
    {
        return $this->state(fn () => [
            'status' => ContentDerivative::STATUS_DRAFT,
        ]);
    }

    public function final(): self
    {
        return $this->state(fn () => [
            'status' => ContentDerivative::STATUS_FINAL,
        ]);
    }

    public function published(): self
    {
        return $this->state(fn () => [
            'status' => ContentDerivative::STATUS_PUBLISHED,
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function notPlanned(): self
    {
        return $this->state(fn () => [
            'status' => ContentDerivative::STATUS_NOT_PLANNED,
        ]);
    }

    public function scheduled(): self
    {
        return $this->state(fn () => [
            'planned_publish_at' => fake()->dateTimeBetween('now', '+30 days'),
        ]);
    }

    public function generating(): self
    {
        return $this->state(fn () => [
            'generation_status' => ContentDerivative::GENERATION_PROCESSING,
        ]);
    }

    public function generated(): self
    {
        return $this->state(fn () => [
            'generation_status' => ContentDerivative::GENERATION_COMPLETED,
            'text' => fake()->paragraphs(5, true),
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn () => [
            'generation_status' => ContentDerivative::GENERATION_FAILED,
            'generation_error' => 'AI generation failed: Rate limit exceeded',
            'generation_error_occurred_at' => now(),
        ]);
    }
}
