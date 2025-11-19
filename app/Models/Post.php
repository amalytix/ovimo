<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory;

    protected $fillable = [
        'source_id',
        'uri',
        'external_title',
        'internal_title',
        'summary',
        'relevancy_score',
        'metadata',
        'is_read',
        'is_hidden',
        'status',
        'found_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (Post $post) {
            if ($post->isDirty('uri')) {
                $post->uri_hash = hash('sha256', $post->uri);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'relevancy_score' => 'integer',
            'metadata' => 'array',
            'is_read' => 'boolean',
            'is_hidden' => 'boolean',
            'found_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function contentPieces(): BelongsToMany
    {
        return $this->belongsToMany(ContentPiece::class);
    }
}
