<?php

namespace Database\Factories;

use App\Models\SocialIntegration;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SocialIntegration>
 */
class SocialIntegrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'platform' => SocialIntegration::PLATFORM_LINKEDIN,
            'platform_user_id' => 'urn:li:person:'.Str::random(12),
            'platform_username' => $this->faker->userName(),
            'access_token' => Str::random(64),
            'refresh_token' => Str::random(64),
            'token_expires_at' => now()->addHours(2),
            'scopes' => ['openid', 'profile', 'w_member_social'],
            'profile_data' => [
                'name' => $this->faker->name(),
                'picture' => $this->faker->imageUrl(),
                'vanityName' => $this->faker->userName(),
            ],
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (SocialIntegration $integration): void {
            $team = $integration->team;
            $user = $integration->user;

            if (! $team || ! $user) {
                return;
            }

            if (! $team->users()->where('user_id', $user->id)->exists()) {
                $team->users()->attach($user->id);
            }

            if ($user->current_team_id === null) {
                $user->forceFill(['current_team_id' => $team->id])->save();
            }
        });
    }
}
