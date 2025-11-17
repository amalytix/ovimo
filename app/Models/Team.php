<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    /** @use HasFactory<\Database\Factories\TeamFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'owner_id',
        'notifications_enabled',
        'webhook_url',
        'post_auto_hide_days',
        'monthly_token_limit',
        'relevancy_prompt',
        'positive_keywords',
        'negative_keywords',
    ];

    protected function casts(): array
    {
        return [
            'notifications_enabled' => 'boolean',
            'post_auto_hide_days' => 'integer',
            'monthly_token_limit' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }

    public function contentPieces(): HasMany
    {
        return $this->hasMany(ContentPiece::class);
    }

    public function tokenUsageLogs(): HasMany
    {
        return $this->hasMany(TokenUsageLog::class);
    }
}
