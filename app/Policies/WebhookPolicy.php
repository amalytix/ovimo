<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Webhook;

class WebhookPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Webhook $webhook): bool
    {
        return $webhook->team_id === $user->current_team_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Webhook $webhook): bool
    {
        return $webhook->team_id === $user->current_team_id;
    }

    /**
     * Determine whether the user can test the model.
     */
    public function test(User $user, Webhook $webhook): bool
    {
        return $webhook->team_id === $user->current_team_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Webhook $webhook): bool
    {
        return $webhook->team_id === $user->current_team_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Webhook $webhook): bool
    {
        return $webhook->team_id === $user->current_team_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Webhook $webhook): bool
    {
        return $webhook->team_id === $user->current_team_id;
    }
}
