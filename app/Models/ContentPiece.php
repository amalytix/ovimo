<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentPiece extends Model
{
    /** @use HasFactory<\Database\Factories\ContentPieceFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'prompt_id',
        'internal_name',
        'briefing_text',
        'channel',
        'published_at',
    ];

    public function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    public function imageGenerations(): HasMany
    {
        return $this->hasMany(ImageGeneration::class);
    }

    public function derivatives(): HasMany
    {
        return $this->hasMany(ContentDerivative::class);
    }

    public function backgroundSources(): HasMany
    {
        return $this->hasMany(BackgroundSource::class)->ordered();
    }

    /**
     * Get combined text from all sources for AI context.
     * Includes background sources and directly attached posts.
     * Each source is limited to maxWordsPerSource words, and total is limited to maxTotalWords.
     */
    public function getCombinedSourceText(int $maxWordsPerSource = 3000, int $maxTotalWords = 10000): string
    {
        $combinedParts = [];
        $totalWords = 0;

        // Helper to add content with word limits
        $addContent = function (string $content) use (&$combinedParts, &$totalWords, $maxWordsPerSource, $maxTotalWords): bool {
            if (empty(trim($content))) {
                return true; // Continue processing
            }

            $words = preg_split('/\s+/', trim($content), -1, PREG_SPLIT_NO_EMPTY);
            if (count($words) > $maxWordsPerSource) {
                $words = array_slice($words, 0, $maxWordsPerSource);
            }

            $remainingWords = $maxTotalWords - $totalWords;
            if ($remainingWords <= 0) {
                return false; // Stop processing
            }

            if (count($words) > $remainingWords) {
                $words = array_slice($words, 0, $remainingWords);
            }

            $totalWords += count($words);
            $combinedParts[] = implode(' ', $words);

            return true;
        };

        // First, add content from background sources
        foreach ($this->backgroundSources as $source) {
            $content = $source->getDisplayContent();
            if ($content && ! $addContent($content)) {
                break;
            }
        }

        // Then, add content from directly attached posts
        foreach ($this->posts as $post) {
            if ($post->summary && ! $addContent($post->summary)) {
                break;
            }
        }

        return implode("\n\n", $combinedParts);
    }
}
