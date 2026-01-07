<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'research_text',
        'edited_text',
        'generation_status',
        'generation_error',
        'generation_error_occurred_at',
        'published_at',
        'publish_to_platforms',
        'published_platforms',
    ];

    public function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'generation_error_occurred_at' => 'immutable_datetime',
            'publish_to_platforms' => 'array',
            'published_platforms' => 'array',
        ];
    }

    public function scopeOrderedForPublishing(Builder $query): Builder
    {
        return $query
            ->orderByRaw('published_at IS NOT NULL')
            ->orderBy('published_at')
            ->orderByDesc('created_at');
    }

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

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class)
            ->withTimestamps()
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function imageGenerations(): HasMany
    {
        return $this->hasMany(ImageGeneration::class);
    }

    public function derivatives(): HasMany
    {
        return $this->hasMany(ContentDerivative::class);
    }

    public function backgroundSources(): HasMany
    {
        return $this->hasMany(BackgroundSource::class)->ordered();
    }
}
