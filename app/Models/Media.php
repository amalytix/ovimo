<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Media extends Model
{
    /** @use HasFactory<\Database\Factories\MediaFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'uploaded_by',
        'filename',
        'stored_filename',
        'file_path',
        'mime_type',
        'file_size',
        's3_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'file_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $media): void {
            try {
                Storage::disk('s3')->delete($media->s3_key);
            } catch (\Throwable $exception) {
                Log::error('S3 deletion failed', [
                    'media_id' => $media->id,
                    's3_key' => $media->s3_key,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MediaTag::class);
    }

    public function contentPieces(): BelongsToMany
    {
        return $this->belongsToMany(ContentPiece::class)
            ->withTimestamps();
    }

    public function generateS3Key(): string
    {
        $uuid = Str::uuid();
        $extension = $this->getExtensionFromMimeType();
        $directory = $this->getStorageDirectory();

        return "teams/{$this->team_id}/{$directory}/{$uuid}.{$extension}";
    }

    public function getStorageDirectory(): string
    {
        return str_starts_with($this->mime_type, 'image/') ? 'images' : 'documents';
    }

    private function getExtensionFromMimeType(): string
    {
        return match ($this->mime_type) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }

    public function getTemporaryUrl(int $expiryMinutes = 60, bool $download = false): string
    {
        try {
            $options = [];

            if ($download) {
                $options['ResponseContentDisposition'] = 'attachment; filename="'.$this->filename.'"';
            }

            return Storage::disk('s3')->temporaryUrl($this->s3_key, now()->addMinutes($expiryMinutes), $options);
        } catch (\Throwable) {
            $baseUrl = config('filesystems.disks.s3.url');

            return $baseUrl ? rtrim($baseUrl, '/').'/'.$this->s3_key : $this->s3_key;
        }
    }
}
