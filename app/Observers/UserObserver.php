<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        //
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Detect 2FA enable/disable
        if ($user->wasChanged('two_factor_secret')) {
            $team = $user->currentTeam;

            if (! $team) {
                return;
            }

            if ($user->two_factor_secret !== null) {
                // 2FA was enabled
                event(new \App\Events\TwoFactorEnabled($user, $team));
            } else {
                // 2FA was disabled
                event(new \App\Events\TwoFactorDisabled($user, $team));
            }
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
