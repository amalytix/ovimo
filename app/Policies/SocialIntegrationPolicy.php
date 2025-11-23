<?php

namespace App\Policies;

use App\Models\SocialIntegration;
use App\Models\User;

class SocialIntegrationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->current_team_id !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SocialIntegration $socialIntegration): bool
    {
        return $socialIntegration->team_id === $user->current_team_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->current_team_id !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SocialIntegration $socialIntegration): bool
    {
        return $socialIntegration->team_id === $user->current_team_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SocialIntegration $socialIntegration): bool
    {
        return $socialIntegration->team_id === $user->current_team_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SocialIntegration $socialIntegration): bool
    {
        return $socialIntegration->team_id === $user->current_team_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SocialIntegration $socialIntegration): bool
    {
        return $socialIntegration->team_id === $user->current_team_id;
    }
}
