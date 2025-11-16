<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContentPiece extends Model
{
    /** @use HasFactory<\Database\Factories\ContentPieceFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'prompt_id',
        'internal_name',
        'briefing_text',
        'channel',
        'target_language',
        'status',
        'full_text',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }
}
