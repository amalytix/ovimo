<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentDerivative extends Model
{
    use HasFactory;

    public const STATUS_NOT_STARTED = 'NOT_STARTED';

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_FINAL = 'FINAL';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    public const STATUS_NOT_PLANNED = 'NOT_PLANNED';

    public const GENERATION_IDLE = 'IDLE';

    public const GENERATION_QUEUED = 'QUEUED';

    public const GENERATION_PROCESSING = 'PROCESSING';

    public const GENERATION_COMPLETED = 'COMPLETED';

    public const GENERATION_FAILED = 'FAILED';

    protected $fillable = [
        'content_piece_id',
        'channel_id',
        'prompt_id',
        'title',
        'text',
        'status',
        'is_published',
        'planned_publish_at',
        'published_at',
        'generation_status',
        'generation_error',
        'generation_error_occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'planned_publish_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'generation_error_occurred_at' => 'immutable_datetime',
        ];
    }

    public function scopeForChannel(Builder $query, int $channelId): Builder
    {
        return $query->where('channel_id', $channelId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeNotPlanned(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_NOT_PLANNED);
    }

    public function contentPiece(): BelongsTo
    {
        return $this->belongsTo(ContentPiece::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'content_derivative_media')
            ->withTimestamps()
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'content_derivative_id')
            ->latest('created_at');
    }

    public function isGenerating(): bool
    {
        return in_array($this->generation_status, [
            self::GENERATION_QUEUED,
            self::GENERATION_PROCESSING,
        ]);
    }

    public function canGenerate(): bool
    {
        return ! $this->isGenerating() && $this->status !== self::STATUS_NOT_PLANNED;
    }
}
