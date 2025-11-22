<?php

namespace App\Http\Controllers;

use App\Events\MediaBulkDeleted;
use App\Events\MediaDeleted;
use App\Events\MediaUpdated;
use App\Events\MediaUploaded;
use App\Http\Requests\Media\BulkActionRequest;
use App\Http\Requests\Media\PresignMediaRequest;
use App\Http\Requests\Media\StoreMediaRequest;
use App\Http\Requests\Media\UpdateMediaRequest;
use App\Models\Media;
use App\Models\MediaTag;
use Aws\S3\PostObjectV4;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Normalizer;

class MediaController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Media::class);

        $user = $request->user();
        $teamId = $user->current_team_id;

        $rawSearch = trim($request->string('search')->toString());
        $search = mb_substr($rawSearch, 0, 120);

        $filters = [
            'search' => $search,
            'tag_ids' => array_map('intval', (array) $request->input('tag_ids', [])),
            'file_type' => $request->get('file_type', 'all'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'sort_by' => $request->get('sort_by', 'uploaded_at'),
            'sort_dir' => $request->get('sort_dir', 'desc'),
        ];

        $sortBy = $filters['sort_by'] === 'filename' ? 'filename' : 'created_at';
        $sortDirection = $filters['sort_dir'] === 'asc' ? 'asc' : 'desc';

        $filters['sort_by'] = $sortBy === 'filename' ? 'filename' : 'uploaded_at';
        $filters['sort_dir'] = $sortDirection;

        $query = Media::query()
            ->where('team_id', $teamId)
            ->with(['tags', 'uploader']);

        if ($filters['search']) {
            $normalized = Normalizer::normalize($filters['search'], Normalizer::FORM_C) ?: $filters['search'];
            $decomposed = Normalizer::normalize($filters['search'], Normalizer::FORM_D) ?: $normalized;

            $searchTerms = array_unique(array_filter([$normalized, $decomposed]));

            $query->where(function (Builder $builder) use ($searchTerms): void {
                foreach ($searchTerms as $term) {
                    $builder->orWhere('filename', 'like', '%'.$term.'%');
                }
            });
        }

        if (! empty($filters['tag_ids'])) {
            $query->whereHas('tags', function (Builder $builder) use ($filters): void {
                $builder->whereIn('media_tags.id', $filters['tag_ids']);
            });
        }

        if ($filters['file_type'] === 'images') {
            $query->where('mime_type', 'like', 'image/%');
        } elseif ($filters['file_type'] === 'pdfs') {
            $query->where('mime_type', 'application/pdf');
        }

        if ($filters['date_from']) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $media = $query
            ->orderBy($sortBy, $sortDirection)
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Media $media) => [
                'id' => $media->id,
                'filename' => $media->filename,
                'mime_type' => $media->mime_type,
                'file_size' => $media->file_size,
                'created_at' => $media->created_at?->toDateTimeString(),
                'tags' => $media->tags->map(fn (MediaTag $tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                ]),
                'temporary_url' => $media->getTemporaryUrl(),
                'download_url' => route('media.download', $media),
            ]);

        return Inertia::render('Media/Index', [
            'media' => $media,
            'filters' => $filters,
            'tags' => MediaTag::where('team_id', $teamId)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function presign(PresignMediaRequest $request): JsonResponse
    {
        $this->authorize('create', Media::class);

        $validated = $request->validated();
        $teamId = $request->user()->current_team_id;

        $media = new Media([
            'team_id' => $teamId,
            'mime_type' => $validated['mime_type'],
        ]);

        $s3Key = $media->generateS3Key();
        $bucket = config('filesystems.disks.s3.bucket');

        $storedFilename = basename($s3Key);
        $fields = [
            'key' => $s3Key,
            'Content-Type' => $validated['mime_type'],
        ];
        $url = Storage::disk('s3')->url($s3Key);

        try {
            $client = Storage::disk('s3')->getClient();

            $formInputs = [
                'acl' => 'private',
                'Content-Type' => $validated['mime_type'],
                'key' => $s3Key,
            ];

            $options = [
                ['acl' => 'private'],
                ['bucket' => $bucket],
                ['starts-with', '$key', "teams/{$teamId}/"],
                ['eq', '$Content-Type', $validated['mime_type']],
                ['content-length-range', 0, $validated['file_size']],
            ];

            $postObject = new PostObjectV4(
                $client,
                $bucket,
                $formInputs,
                $options,
                '+15 minutes'
            );

            $url = $postObject->getFormAttributes()['action'];
            $fields = $postObject->getFormInputs();
        } catch (\Throwable) {
            // Fall back to returning a basic payload when S3 client is unavailable (e.g. during tests)
        }

        return response()->json([
            'url' => $url,
            'fields' => $fields,
            's3_key' => $s3Key,
            'stored_filename' => $storedFilename,
            'file_path' => $s3Key,
        ]);
    }

    public function store(StoreMediaRequest $request): JsonResponse
    {
        $this->authorize('create', Media::class);

        $validated = $request->validated();
        $teamId = $request->user()->current_team_id;

        if (! Str::startsWith($validated['s3_key'], "teams/{$teamId}/")) {
            return response()->json(['message' => 'Invalid S3 key for this team.'], 422);
        }

        $media = Media::create([
            ...$validated,
            'team_id' => $teamId,
            'uploaded_by' => $request->user()->id,
        ]);

        event(new MediaUploaded($media, $request->user()));

        return response()->json([
            'media' => [
                'id' => $media->id,
                'filename' => $media->filename,
                'mime_type' => $media->mime_type,
                'file_size' => $media->file_size,
                'created_at' => $media->created_at?->toDateTimeString(),
                'temporary_url' => $media->getTemporaryUrl(),
                'download_url' => route('media.download', $media),
                'tags' => [],
            ],
        ], 201);
    }

    public function show(Media $media): Response
    {
        $this->authorize('view', $media);

        $media->load('tags', 'uploader');

        return Inertia::render('Media/Show', [
            'media' => [
                'id' => $media->id,
                'filename' => $media->filename,
                'mime_type' => $media->mime_type,
                'file_size' => $media->file_size,
                'uploaded_by' => $media->uploader?->only(['id', 'name']),
                'created_at' => $media->created_at?->toDateTimeString(),
                'metadata' => $media->metadata,
                'temporary_url' => $media->getTemporaryUrl(),
                'download_url' => route('media.download', $media),
                'tags' => $media->tags->map(fn (MediaTag $tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                ]),
            ],
            'availableTags' => MediaTag::where('team_id', $media->team_id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(UpdateMediaRequest $request, Media $media): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $media);

        $data = $request->validated();
        $changes = [];

        if (isset($data['filename'])) {
            $changes['filename'] = ['from' => $media->filename, 'to' => $data['filename']];
            $media->filename = $data['filename'];
        }

        if (isset($data['tag_ids'])) {
            $media->tags()->sync($data['tag_ids']);
        }

        $media->save();

        event(new MediaUpdated($media, $request->user(), $changes));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Media updated successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Media updated successfully.');
    }

    public function destroy(Request $request, Media $media): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $media);

        $mediaSnapshot = [
            'id' => $media->id,
            'team_id' => $media->team_id,
            'filename' => $media->filename,
        ];

        $media->delete();

        event(new MediaDeleted(
            $mediaSnapshot['id'],
            $mediaSnapshot['team_id'],
            $mediaSnapshot['filename'],
            $request->user()
        ));

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Media deleted successfully.']);
        }

        return redirect()->route('media.index')->with('success', 'Media deleted successfully.');
    }

    public function bulkDestroy(BulkActionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $teamId = $request->user()->current_team_id;

        $mediaItems = Media::where('team_id', $teamId)
            ->whereIn('id', $validated['media_ids'])
            ->get();

        foreach ($mediaItems as $media) {
            $this->authorize('delete', $media);
            $media->delete();
        }

        event(new MediaBulkDeleted($validated['media_ids'], $teamId, $request->user()));

        return response()->json(['message' => 'Media deleted successfully.']);
    }

    public function bulkTag(BulkActionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $teamId = $request->user()->current_team_id;
        $action = $validated['action'] ?? 'add_tags';

        $mediaItems = Media::where('team_id', $teamId)
            ->whereIn('id', $validated['media_ids'])
            ->get();

        $tagIds = $validated['tag_ids'] ?? [];

        foreach ($mediaItems as $media) {
            $this->authorize('update', $media);

            if ($action === 'remove_tags') {
                $media->tags()->detach($tagIds);
            } else {
                $media->tags()->syncWithoutDetaching($tagIds);
            }

            event(new MediaUpdated($media, $request->user(), ['tags' => $action]));
        }

        return response()->json(['message' => 'Tags updated successfully.']);
    }

    public function download(Request $request, Media $media): RedirectResponse
    {
        $this->authorize('view', $media);

        $signedUrl = $media->getTemporaryUrl(download: true);

        return redirect()->away($signedUrl);
    }

    public function temporary(Request $request, Media $media): JsonResponse
    {
        $this->authorize('view', $media);

        return response()->json([
            'temporary_url' => $media->getTemporaryUrl(),
            'download_url' => route('media.download', $media),
        ]);
    }

    public function view(Request $request, Media $media): RedirectResponse
    {
        $this->authorize('view', $media);

        $signedUrl = $media->getTemporaryUrl();

        return redirect()->away($signedUrl);
    }
}
