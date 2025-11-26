<?php

namespace Database\Factories;

use App\Models\ContentPiece;
use App\Models\ImageGeneration;
use App\Models\Prompt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ImageGeneration>
 */
class ImageGenerationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content_piece_id' => ContentPiece::factory(),
            'prompt_id' => null,
            'generated_text_prompt' => $this->faker->sentence(),
            'aspect_ratio' => $this->faker->randomElement([
                ImageGeneration::ASPECT_RATIO_16_9,
                ImageGeneration::ASPECT_RATIO_1_1,
                ImageGeneration::ASPECT_RATIO_4_3,
                ImageGeneration::ASPECT_RATIO_9_16,
            ]),
            'status' => ImageGeneration::STATUS_DRAFT,
            'media_id' => null,
            'error_message' => null,
        ];
    }

    public function withPrompt(?Prompt $prompt = null): static
    {
        return $this->state(fn (array $attributes) => [
            'prompt_id' => $prompt?->id ?? Prompt::factory(),
        ]);
    }

    public function generating(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImageGeneration::STATUS_GENERATING,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImageGeneration::STATUS_COMPLETED,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImageGeneration::STATUS_FAILED,
            'error_message' => $this->faker->sentence(),
        ]);
    }
}
