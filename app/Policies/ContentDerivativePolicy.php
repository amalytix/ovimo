<?php

namespace App\Policies;

use App\Models\ContentDerivative;
use App\Models\ContentPiece;
use App\Models\User;

class ContentDerivativePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ContentDerivative $contentDerivative): bool
    {
        return $contentDerivative->contentPiece->team_id === $user->current_team_id;
    }

    public function create(User $user, ContentPiece $contentPiece): bool
    {
        return $contentPiece->team_id === $user->current_team_id;
    }

    public function update(User $user, ContentDerivative $contentDerivative): bool
    {
        return $contentDerivative->contentPiece->team_id === $user->current_team_id;
    }

    public function generate(User $user, ContentDerivative $contentDerivative): bool
    {
        return $contentDerivative->contentPiece->team_id === $user->current_team_id;
    }

    public function delete(User $user, ContentDerivative $contentDerivative): bool
    {
        return $contentDerivative->contentPiece->team_id === $user->current_team_id;
    }
}
