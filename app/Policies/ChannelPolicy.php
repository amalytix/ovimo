<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\User;

class ChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Channel $channel): bool
    {
        return $channel->team_id === $user->current_team_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Channel $channel): bool
    {
        return $channel->team_id === $user->current_team_id;
    }

    public function delete(User $user, Channel $channel): bool
    {
        return $channel->team_id === $user->current_team_id;
    }

    public function reorder(User $user): bool
    {
        return true;
    }
}
