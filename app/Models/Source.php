<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Source extends Model
{
    /** @use HasFactory<\Database\Factories\SourceFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id',
        'internal_name',
        'type',
        'url',
        'css_selector_title',
        'css_selector_link',
        'keywords',
        'monitoring_interval',
        'is_active',
        'should_notify',
        'auto_summarize',
        'bypass_keyword_filter',
        'last_checked_at',
        'consecutive_failures',
        'failed_at',
        'next_check_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'should_notify' => 'boolean',
            'auto_summarize' => 'boolean',
            'bypass_keyword_filter' => 'boolean',
            'last_checked_at' => 'datetime',
            'failed_at' => 'datetime',
            'next_check_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function calculateNextCheckTime(?string $interval = null): \Carbon\Carbon
    {
        $interval = $interval ?? $this->monitoring_interval;

        return match ($interval) {
            'EVERY_10_MIN' => now()->addMinutes(10),
            'EVERY_30_MIN' => now()->addMinutes(30),
            'HOURLY' => now()->addHour(),
            'EVERY_6_HOURS' => now()->addHours(6),
            'DAILY' => now()->addDay(),
            'WEEKLY' => now()->addWeek(),
            default => now()->addDay(),
        };
    }
}
