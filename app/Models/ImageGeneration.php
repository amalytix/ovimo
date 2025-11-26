<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageGeneration extends Model
{
    /** @use HasFactory<\Database\Factories\ImageGenerationFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_GENERATING = 'GENERATING';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_FAILED = 'FAILED';

    public const ASPECT_RATIO_16_9 = '16:9';

    public const ASPECT_RATIO_1_1 = '1:1';

    public const ASPECT_RATIO_4_3 = '4:3';

    public const ASPECT_RATIO_9_16 = '9:16';

    protected $fillable = [
        'content_piece_id',
        'prompt_id',
        'generated_text_prompt',
        'aspect_ratio',
        'status',
        'media_id',
        'error_message',
    ];

    public function contentPiece(): BelongsTo
    {
        return $this->belongsTo(ContentPiece::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
