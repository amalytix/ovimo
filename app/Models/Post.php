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
        'summary',
        'relevancy_score',
        'is_read',
        'is_hidden',
        'status',
        'found_at',
    ];

    protected function casts(): array
    {
        return [
            'relevancy_score' => 'integer',
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
