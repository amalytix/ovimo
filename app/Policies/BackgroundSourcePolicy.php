<?php

namespace App\Policies;

use App\Models\BackgroundSource;
use App\Models\ContentPiece;
use App\Models\User;

class BackgroundSourcePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BackgroundSource $backgroundSource): bool
    {
        return $backgroundSource->contentPiece->team_id === $user->current_team_id;
    }

    public function create(User $user, ContentPiece $contentPiece): bool
    {
        return $contentPiece->team_id === $user->current_team_id;
    }

    public function update(User $user, BackgroundSource $backgroundSource): bool
    {
        return $backgroundSource->contentPiece->team_id === $user->current_team_id;
    }

    public function delete(User $user, BackgroundSource $backgroundSource): bool
    {
        return $backgroundSource->contentPiece->team_id === $user->current_team_id;
    }

    public function reorder(User $user, ContentPiece $contentPiece): bool
    {
        return $contentPiece->team_id === $user->current_team_id;
    }
}
