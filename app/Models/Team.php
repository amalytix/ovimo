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
        'is_active',
        'post_auto_hide_days',
        'monthly_token_limit',
        'relevancy_prompt',
        'positive_keywords',
        'negative_keywords',
        'openai_api_key',
        'openai_model',
        'gemini_api_key',
        'gemini_image_model',
        'gemini_image_size',
    ];

    protected function casts(): array
    {
        return [
            'post_auto_hide_days' => 'integer',
            'monthly_token_limit' => 'integer',
            'is_active' => 'boolean',
            'openai_api_key' => 'encrypted',
            'gemini_api_key' => 'encrypted',
        ];
    }

    public function hasOpenAIConfigured(): bool
    {
        return filled($this->openai_api_key);
    }

    public function hasGeminiConfigured(): bool
    {
        return filled($this->gemini_api_key);
    }

    public function getMaskedOpenAIKey(): ?string
    {
        if (! $this->openai_api_key) {
            return null;
        }

        $key = $this->openai_api_key;

        return '****...'.substr($key, -min(8, strlen($key)));
    }

    public function getMaskedGeminiKey(): ?string
    {
        if (! $this->gemini_api_key) {
            return null;
        }

        $key = $this->gemini_api_key;

        return '****...'.substr($key, -min(8, strlen($key)));
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

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function mediaTags(): HasMany
    {
        return $this->hasMany(MediaTag::class);
    }

    public function contentPieces(): HasMany
    {
        return $this->hasMany(ContentPiece::class);
    }

    public function tokenUsageLogs(): HasMany
    {
        return $this->hasMany(TokenUsageLog::class);
    }

    public function socialIntegrations(): HasMany
    {
        return $this->hasMany(SocialIntegration::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    public function pendingInvitations(): HasMany
    {
        return $this->invitations()->where('expires_at', '>', now());
    }
}
