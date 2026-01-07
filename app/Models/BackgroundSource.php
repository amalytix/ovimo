<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackgroundSource extends Model
{
    use HasFactory;

    public const TYPE_POST = 'POST';

    public const TYPE_MANUAL = 'MANUAL';

    protected $fillable = [
        'content_piece_id',
        'type',
        'post_id',
        'title',
        'content',
        'url',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    public function scopePosts(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_POST);
    }

    public function scopeManual(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_MANUAL);
    }

    public function contentPiece(): BelongsTo
    {
        return $this->belongsTo(ContentPiece::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function isPost(): bool
    {
        return $this->type === self::TYPE_POST;
    }

    public function isManual(): bool
    {
        return $this->type === self::TYPE_MANUAL;
    }

    public function getDisplayTitle(): string
    {
        if ($this->isPost() && $this->post) {
            return $this->post->internal_title ?? $this->post->external_title ?? 'Untitled Post';
        }

        return $this->title ?? 'Untitled Source';
    }

    public function getDisplayContent(): ?string
    {
        if ($this->isPost() && $this->post) {
            return $this->post->summary;
        }

        return $this->content;
    }
}
