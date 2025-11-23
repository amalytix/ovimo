<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialIntegration extends Model
{
    /** @use HasFactory<\Database\Factories\SocialIntegrationFactory> */
    use HasFactory;

    public const PLATFORM_LINKEDIN = 'linkedin';

    public const PLATFORMS = [self::PLATFORM_LINKEDIN];

    protected $fillable = [
        'team_id',
        'user_id',
        'platform',
        'platform_user_id',
        'platform_username',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'profile_data',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'scopes' => 'array',
            'profile_data' => 'array',
            'is_active' => 'boolean',
            'token_expires_at' => 'immutable_datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
