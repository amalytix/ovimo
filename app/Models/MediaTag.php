<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MediaTag extends Model
{
    /** @use HasFactory<\Database\Factories\MediaTagFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'slug',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class);
    }
}
