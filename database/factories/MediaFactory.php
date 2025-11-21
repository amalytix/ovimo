<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Media>
 */
class MediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mimeType = fake()->randomElement([
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'application/pdf',
        ]);

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            default => 'bin',
        };

        $directory = str_starts_with($mimeType, 'image/') ? 'images' : 'documents';
        $storedFilename = Str::uuid()->toString().'.'.$extension;

        return [
            'team_id' => Team::factory(),
            'uploaded_by' => User::factory(),
            'filename' => fake()->unique()->lexify('file-????').'.'.$extension,
            'stored_filename' => $storedFilename,
            'file_path' => fn (array $attributes) => "teams/{$attributes['team_id']}/{$directory}/{$storedFilename}",
            'mime_type' => $mimeType,
            'file_size' => fake()->numberBetween(10_000, 5_000_000),
            's3_key' => fn (array $attributes) => $attributes['file_path'],
            'metadata' => [
                'width' => fake()->numberBetween(300, 2000),
                'height' => fake()->numberBetween(300, 2000),
            ],
        ];
    }
}
