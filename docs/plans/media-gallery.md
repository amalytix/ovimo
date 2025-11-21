# Media Gallery - Design Document

**Date:** 2025-01-21
**Status:** Design Approved, Ready for Implementation
**Phase:** 1 (Core Gallery MVP)

---

## Overview

A team-scoped media gallery for Ovimo that allows users to upload, organize, and manage images and PDFs stored on AWS S3. Features include tagging, search/filter capabilities, bulk operations, and both list and gallery view modes.

### Key Features

- Direct browser-to-S3 uploads using presigned URLs
- Support for images (JPEG, PNG, GIF, WebP, SVG) and PDFs
- Team-specific tag organization system
- List and gallery view modes
- Advanced search and filtering (filename, tags, date, file type)
- Bulk operations (delete, add/remove tags)
- Seamless multi-tenant integration

### Design Principles

- **YAGNI:** No thumbnail generation, storage quotas, or content integration in phase 1
- **Scalability:** Direct S3 uploads avoid server bottlenecks
- **Simplicity:** Equal permissions for all team members
- **Consistency:** Follows existing Ovimo patterns (policies, controllers, Inertia)

---

## Architecture Decisions

### Upload Strategy: Direct S3 Upload (Presigned URLs)

**Chosen Approach:** Users upload files directly from browser to S3 using presigned URLs.

**Why:**
- Scalable - no server bandwidth bottleneck
- Better UX - real-time upload progress, faster uploads
- Lower server costs - files never touch Laravel server
- Standard AWS pattern for modern web apps

**Alternatives Considered:**
- Traditional server upload (rejected: bandwidth bottleneck, slower)
- Hybrid approach (rejected: unnecessary complexity)

### Other Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| **Delete Strategy** | Hard deletes | Automatic S3 cleanup when media deleted |
| **Tag System** | Separate MediaTag model | Different vocabulary than Source tags |
| **Storage Limits** | None initially | Simplify MVP, add later if needed |
| **Image Processing** | Store originals only | No thumbnail generation in phase 1 (prepared for phase 2) |
| **Permissions** | Equal for all team members | Simplified authorization model |
| **Multi-select UI** | Always-visible checkboxes | Clear, discoverable interaction |
| **File Types** | Images + PDFs | Covers most content creation needs |
| **Search/Filter** | All options (filename, tags, date, type) | Comprehensive findability |
| **Bulk Operations** | Delete + Tag management | Most common organizational tasks |
| **Content Integration** | Phase 2 | Focus on standalone gallery first |

---

## Database Schema

### Media Table

```sql
CREATE TABLE media (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    team_id BIGINT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    s3_key VARCHAR(500) NOT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_team_id (team_id),
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_mime_type (mime_type),
    INDEX idx_created_at (created_at)
);
```

**Field Descriptions:**
- `filename` - Original filename from user
- `stored_filename` - UUID-based filename for S3 (security)
- `file_path` - Full S3 path: `teams/{team_id}/images/{uuid}.jpg`
- `s3_key` - S3 object key for deletion operations
- `metadata` - JSON field for extensibility (width, height, color palette, etc.)

### MediaTag Table

```sql
CREATE TABLE media_tags (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    team_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    INDEX idx_team_id (team_id),
    UNIQUE KEY unique_team_slug (team_id, slug)
);
```

### Media-MediaTag Pivot Table

```sql
CREATE TABLE media_media_tag (
    media_id BIGINT UNSIGNED NOT NULL,
    media_tag_id BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (media_id, media_tag_id),
    FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE,
    FOREIGN KEY (media_tag_id) REFERENCES media_tags(id) ON DELETE CASCADE
);
```

### Relationships

**Media Model:**
```php
belongsTo(Team::class)
belongsTo(User::class, 'uploaded_by')
belongsToMany(MediaTag::class)
```

**MediaTag Model:**
```php
belongsTo(Team::class)
belongsToMany(Media::class)
```

**Team Model (add):**
```php
hasMany(Media::class)
hasMany(MediaTag::class)
```

---

## Backend Architecture

### Controllers

#### MediaController (Resource Controller)

```php
Route::middleware(['auth', 'verified', 'team.valid'])->group(function () {
    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::post('/media/presign', [MediaController::class, 'presign'])->name('media.presign');
    Route::post('/media', [MediaController::class, 'store'])->name('media.store');
    Route::get('/media/{media}', [MediaController::class, 'show'])->name('media.show');
    Route::patch('/media/{media}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::post('/media/bulk-delete', [MediaController::class, 'bulkDestroy'])->name('media.bulk-delete');
    Route::post('/media/bulk-tag', [MediaController::class, 'bulkTag'])->name('media.bulk-tag');
});
```

**Method Responsibilities:**

- `index()` - Paginated list with search/filter support
- `presign()` - Generate presigned S3 POST URL for direct upload
- `store()` - Save metadata after successful S3 upload
- `show()` - Single media detail view
- `update()` - Edit filename and tags
- `destroy()` - Delete media record + S3 file
- `bulkDestroy()` - Delete multiple media items
- `bulkTag()` - Add/remove tags from multiple items

#### MediaTagController (Resource Controller)

```php
Route::middleware(['auth', 'verified', 'team.valid'])->group(function () {
    Route::get('/media-tags', [MediaTagController::class, 'index'])->name('media-tags.index');
    Route::post('/media-tags', [MediaTagController::class, 'store'])->name('media-tags.store');
    Route::patch('/media-tags/{mediaTag}', [MediaTagController::class, 'update'])->name('media-tags.update');
    Route::delete('/media-tags/{mediaTag}', [MediaTagController::class, 'destroy'])->name('media-tags.destroy');
});
```

### Upload Flow

```
┌─────────┐                ┌─────────┐                ┌─────────┐
│ Browser │                │ Laravel │                │   S3    │
└────┬────┘                └────┬────┘                └────┬────┘
     │                          │                          │
     │ 1. Request presigned URL │                          │
     │─────────────────────────>│                          │
     │  POST /media/presign     │                          │
     │  {filename, mime_type}   │                          │
     │                          │                          │
     │ 2. Generate presigned    │                          │
     │    POST URL with policy  │                          │
     │<─────────────────────────│                          │
     │  {url, fields}           │                          │
     │                          │                          │
     │ 3. Upload directly to S3 │                          │
     │──────────────────────────┼─────────────────────────>│
     │  POST to presigned URL   │                          │
     │                          │                          │
     │ 4. S3 confirms upload    │                          │
     │<─────────────────────────┼──────────────────────────│
     │  200 OK                  │                          │
     │                          │                          │
     │ 5. Save metadata         │                          │
     │─────────────────────────>│                          │
     │  POST /media             │                          │
     │  {s3_key, filename, ...} │                          │
     │                          │                          │
     │ 6. Media record created  │                          │
     │<─────────────────────────│                          │
     │  201 Created             │                          │
```

### S3 Cleanup Strategy

```php
// Media Model
protected static function booted()
{
    static::deleting(function ($media) {
        try {
            Storage::disk('s3')->delete($media->s3_key);
        } catch (\Exception $e) {
            // Log error but continue deletion
            Log::error('S3 deletion failed', [
                'media_id' => $media->id,
                's3_key' => $media->s3_key,
                'error' => $e->getMessage()
            ]);
        }
    });
}
```

**Orphaned File Cleanup:** Implement scheduled command to find and remove S3 files without DB records (weekly).

### Authorization (MediaPolicy)

```php
class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Team-scoped in controller
    }

    public function view(User $user, Media $media): bool
    {
        return $media->team_id === $user->current_team_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Media $media): bool
    {
        return $media->team_id === $user->current_team_id;
    }

    public function delete(User $user, Media $media): bool
    {
        return $media->team_id === $user->current_team_id;
    }
}
```

### Form Requests

**PresignMediaRequest:**
```php
public function rules(): array
{
    return [
        'filename' => ['required', 'string', 'max:255'],
        'mime_type' => ['required', 'string', 'in:image/jpeg,image/png,image/gif,image/webp,image/svg+xml,application/pdf'],
        'file_size' => ['required', 'integer', 'max:52428800'], // 50MB
    ];
}
```

**StoreMediaRequest:**
```php
public function rules(): array
{
    return [
        's3_key' => ['required', 'string'],
        'filename' => ['required', 'string', 'max:255'],
        'stored_filename' => ['required', 'string'],
        'file_path' => ['required', 'string'],
        'mime_type' => ['required', 'string'],
        'file_size' => ['required', 'integer'],
        'metadata' => ['nullable', 'array'],
    ];
}
```

**UpdateMediaRequest:**
```php
public function rules(): array
{
    return [
        'filename' => ['sometimes', 'string', 'max:255'],
        'tag_ids' => ['sometimes', 'array'],
        'tag_ids.*' => ['exists:media_tags,id'],
    ];
}
```

**BulkActionRequest:**
```php
public function rules(): array
{
    return [
        'media_ids' => ['required', 'array', 'min:1'],
        'media_ids.*' => ['exists:media,id'],
        'tag_ids' => ['sometimes', 'array'],
        'tag_ids.*' => ['exists:media_tags,id'],
        'action' => ['sometimes', 'in:add_tags,remove_tags'],
    ];
}
```

### Events & Activity Logging

```php
MediaUploaded::class     // When media uploaded
MediaUpdated::class      // When filename/tags changed
MediaDeleted::class      // When single media deleted
MediaBulkDeleted::class  // When multiple media deleted
```

Each event triggers ActivityLog creation with user, team, and action context.

---

## Frontend Architecture

### Page Structure

```
resources/js/Pages/Media/
├── Index.vue              # Main gallery (list/grid toggle)
├── Show.vue               # Single media detail view
└── Tags/
    └── Index.vue          # Manage media tags
```

### Component Structure

```
resources/js/components/Media/
├── MediaCard.vue          # Gallery view card component
├── MediaListItem.vue      # List view row component
├── MediaUploader.vue      # Upload modal with progress
├── MediaFilters.vue       # Filter toolbar component
├── MediaBulkActions.vue   # Bottom bulk action bar
├── MediaTagInput.vue      # Tag selector with autocomplete
└── MediaPreview.vue       # Image/PDF preview modal
```

### Main Gallery View (Media/Index.vue)

**Layout Sections:**

1. **Header**
   - Upload button (triggers file picker)
   - View toggle button (List ⟷ Gallery)
   - Search input (debounced filename search)

2. **Filter Toolbar**
   - Tag multi-select dropdown with count badges
   - File type buttons: All | Images | PDFs
   - Date range picker: Today | This Week | This Month | Custom
   - Active filter chips (dismissible)
   - Clear all filters button

3. **Content Area**
   - Gallery mode: CSS Grid (2-6 columns, responsive)
   - List mode: Sortable table

4. **Bulk Actions Bar** (fixed bottom, appears when items selected)
   - Selection count indicator
   - Delete Selected button
   - Add Tags button
   - Remove Tags button
   - Deselect All button

**Gallery Mode Card:**
```vue
<div class="media-card">
  <div class="relative">
    <img :src="media.url" :alt="media.filename" />
    <input type="checkbox" v-model="selected" class="absolute top-2 left-2" />
    <div class="overlay"><!-- Quick actions --></div>
  </div>
  <div class="card-info">
    <p class="filename">{{ media.filename }}</p>
    <div class="tags">
      <Badge v-for="tag in media.tags.slice(0, 3)" :key="tag.id">
        {{ tag.name }}
      </Badge>
      <Badge v-if="media.tags.length > 3">+{{ media.tags.length - 3 }}</Badge>
    </div>
    <div class="metadata">
      <span>{{ formatFileSize(media.file_size) }}</span>
      <span>{{ formatRelativeTime(media.created_at) }}</span>
    </div>
  </div>
</div>
```

**List Mode Row:**
```vue
<TableRow>
  <TableCell><Checkbox v-model="selected" /></TableCell>
  <TableCell><img :src="media.thumbnail_url" /></TableCell>
  <TableCell>{{ media.filename }}</TableCell>
  <TableCell>
    <Badge v-for="tag in media.tags" :key="tag.id">{{ tag.name }}</Badge>
  </TableCell>
  <TableCell>{{ formatFileSize(media.file_size) }}</TableCell>
  <TableCell>{{ formatDate(media.created_at) }}</TableCell>
  <TableCell>
    <DropdownMenu>
      <DropdownMenuItem>View</DropdownMenuItem>
      <DropdownMenuItem>Edit</DropdownMenuItem>
      <DropdownMenuItem>Delete</DropdownMenuItem>
    </DropdownMenu>
  </TableCell>
</TableRow>
```

### Upload Experience

**MediaUploader.vue Component:**

```vue
<Dialog v-model:open="isOpen">
  <DialogContent>
    <DialogHeader>
      <DialogTitle>Upload Media</DialogTitle>
    </DialogHeader>

    <div class="upload-area" @drop.prevent="handleDrop" @dragover.prevent>
      <input type="file" ref="fileInput" @change="handleFiles" multiple />
      <p>Drag files here or click to browse</p>
      <p class="text-sm">Images and PDFs up to 50MB</p>
    </div>

    <div v-if="uploads.length" class="upload-list">
      <div v-for="upload in uploads" :key="upload.id" class="upload-item">
        <img v-if="upload.preview" :src="upload.preview" />
        <FileIcon v-else />
        <div class="upload-info">
          <p>{{ upload.filename }}</p>
          <Progress :value="upload.progress" />
          <p class="text-sm">{{ upload.status }}</p>
        </div>
        <CheckCircle v-if="upload.complete" />
        <XCircle v-if="upload.error" />
      </div>
    </div>
  </DialogContent>
</Dialog>
```

**Upload Flow:**
1. User selects files via drag-drop or file picker
2. Validate file types and sizes client-side
3. For each file:
   - Request presigned URL from backend
   - Upload directly to S3 with progress tracking
   - On success, send metadata to backend
   - Update progress bar and status
4. Show success animation
5. Auto-close modal after 2 seconds
6. Refresh gallery with new items highlighted

### State Management

```typescript
// View mode (persisted to localStorage)
const viewMode = ref<'gallery' | 'list'>(
  localStorage.getItem('media.viewMode') ?? 'gallery'
)

watch(viewMode, (mode) => {
  localStorage.setItem('media.viewMode', mode)
})

// Selected items (for bulk actions)
const selectedIds = ref<Set<number>>(new Set())

// Filters (synced with URL query params)
const filters = reactive({
  search: '',
  tags: [] as number[],
  fileType: 'all',
  dateRange: null,
})
```

### Empty States

**No Media Uploaded:**
```vue
<div class="empty-state">
  <ImageIcon class="w-16 h-16 opacity-50" />
  <h3>No media yet</h3>
  <p>Upload images and PDFs to organize and use in your content</p>
  <Button @click="openUploader">Upload Media</Button>
</div>
```

**No Search Results:**
```vue
<div class="empty-state">
  <SearchIcon class="w-16 h-16 opacity-50" />
  <h3>No media found</h3>
  <p>Try adjusting your filters or search term</p>
  <Button @click="clearFilters">Clear Filters</Button>
</div>
```

**No Tags:**
```vue
<div class="empty-state">
  <TagIcon class="w-16 h-16 opacity-50" />
  <h3>No tags yet</h3>
  <p>Create tags to organize your media</p>
  <Button @click="createTag">Create Tag</Button>
</div>
```

---

## AWS S3 Setup Guide

### Step 1: Create S3 Bucket

1. Log into AWS Console → S3 → **Create bucket**
2. **Bucket name:** `ovimo-media-production` (globally unique)
3. **Region:** `us-east-1`
4. **Block Public Access settings:**
   - ✅ Block all public access
   - We'll use presigned URLs, not public access
5. **Bucket Versioning:** Disabled (not needed)
6. **Encryption:** Enable (Server-side encryption with Amazon S3 managed keys - SSE-S3)
7. Click **Create bucket**

### Step 2: Configure CORS Policy

Navigate to bucket → **Permissions** → **CORS configuration**:

```json
[
  {
    "AllowedHeaders": ["*"],
    "AllowedMethods": ["GET", "PUT", "POST", "DELETE"],
    "AllowedOrigins": [
      "http://localhost:5173",
      "http://ovimo.test",
      "https://yourdomain.com"
    ],
    "ExposeHeaders": ["ETag"],
    "MaxAgeSeconds": 3600
  }
]
```

**⚠️ Important:** Update `AllowedOrigins` with your actual frontend URLs:
- Development: `http://localhost:5173`, `http://ovimo.test`
- Production: `https://app.ovimo.com` (or your domain)

### Step 3: Create IAM Policy

1. IAM → **Policies** → **Create policy**
2. Switch to **JSON** tab
3. Paste this policy:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "OvimoMediaAccess",
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:DeleteObject",
        "s3:ListBucket"
      ],
      "Resource": [
        "arn:aws:s3:::ovimo-test",
        "arn:aws:s3:::ovimo-test/*"
      ]
    }
  ]
}
```

4. Click **Next**
5. **Policy name:** `OvimoMediaBucketPolicy`
6. Click **Create policy**

**Permission Explanations:**
- `s3:PutObject` - Upload files (via presigned URLs)
- `s3:GetObject` - Download/view files (via presigned URLs)
- `s3:DeleteObject` - Delete files when media deleted in app
- `s3:ListBucket` - List bucket contents (for debugging/admin)

### Step 4: Create IAM User

1. IAM → **Users** → **Create user**
2. **User name:** `ovimo-media-service`
3. **Access type:** Programmatic access (no console access)
4. Click **Next**
5. **Permissions:** Attach policy `OvimoMediaBucketPolicy`
6. Click **Next** → **Create user**
7. **⚠️ CRITICAL:** Save credentials immediately:
   - **Access Key ID:** `AKIA...`
   - **Secret Access Key:** (shown once only!)

   You cannot retrieve the secret key later!

### Step 5: Laravel Configuration

**Update `.env`:**
```bash
AWS_ACCESS_KEY_ID=AKIA... # from Step 4
AWS_SECRET_ACCESS_KEY=your-secret-key # from Step 4
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=ovimo-media-production
AWS_USE_PATH_STYLE_ENDPOINT=false
AWS_URL=https://ovimo-media-production.s3.us-east-1.amazonaws.com
```

**Update `config/filesystems.php`:**
```php
'disks' => [
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
        'throw' => true, // Throw exceptions on failures
    ],
],
```

**Verify S3 connection:**
```bash
php artisan tinker
> Storage::disk('s3')->put('test.txt', 'Hello S3');
> Storage::disk('s3')->exists('test.txt');
> Storage::disk('s3')->delete('test.txt');
```

### Step 6: S3 File Organization

**Directory structure:**
```
ovimo-media-production/
└── teams/
    └── {team_id}/
        ├── images/
        │   └── {uuid}.jpg
        ├── thumbnails/          ← Phase 2: thumbnail generation
        │   ├── small/
        │   │   └── {uuid}.jpg
        │   ├── medium/
        │   │   └── {uuid}.jpg
        │   └── large/
        │       └── {uuid}.jpg
        └── documents/
            └── {uuid}.pdf
```

**Example paths:**
- Image: `teams/1/images/9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d.jpg`
- PDF: `teams/1/documents/a1b2c3d4-e5f6-7890-abcd-ef1234567890.pdf`
- Thumbnail (phase 2): `teams/1/thumbnails/medium/9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d.jpg`

**Benefits:**
- Team isolation (security)
- Organized by file type (clarity)
- Easy to implement per-team storage quotas later
- Future-proof for thumbnail subdirectories

### Security Best Practices

✅ **Never commit AWS credentials to git**
- Add `.env` to `.gitignore`
- Use environment-specific credentials

✅ **Use separate buckets per environment**
- Development: `ovimo-media-dev`
- Staging: `ovimo-media-staging`
- Production: `ovimo-media-production`

✅ **Enable CloudTrail logging**
- Audit trail for all S3 API calls
- IAM → CloudTrail → Create trail

✅ **Set lifecycle rules**
- Automatically clean up incomplete multipart uploads after 7 days
- Bucket → Management → Lifecycle rules

✅ **Use short-lived presigned URLs**
- Upload URLs: 15 minute expiry
- View URLs: 60 minute expiry
- Configure in `MediaController`

✅ **Enable versioning (optional)**
- Protects against accidental deletion
- Bucket → Properties → Versioning

### Testing S3 Setup

```bash
# Test presigned URL generation
php artisan tinker
> $disk = Storage::disk('s3');
> $url = $disk->temporaryUrl('test.jpg', now()->addMinutes(15));
> dump($url);
```

---

## Error Handling Strategy

### S3 Upload Failures

**Frontend Handling:**
- Retry up to 3 times with exponential backoff (1s, 2s, 4s)
- Show user-friendly error: "Upload failed. Please check your connection and try again."
- Provide "Retry" button for manual retry
- Log detailed error to browser console for debugging

**Backend Handling:**
- Validate presigned URL parameters before generating
- Return clear error messages for invalid requests
- Log S3 API errors with context (user, team, filename)

### Presigned URL Expiration

**Problem:** Upload takes longer than 15 minutes

**Solution:**
- Monitor upload progress in frontend
- If nearing expiry (>12 minutes), automatically request new presigned URL
- Resume upload with new URL
- Show status: "Refreshing upload link..."

### File Validation Errors

**Client-Side Validation:**
- Check file type before upload: `image/*` or `application/pdf`
- Check file size: max 50MB
- Show errors immediately: "Only images and PDFs are allowed" or "File exceeds 50MB limit"

**Server-Side Validation:**
- Re-validate in `PresignMediaRequest` (never trust client)
- Sanitize filename: strip special characters, limit length to 255
- Validate mime type matches file extension

### S3 Deletion Failures

**Problem:** S3 delete API call fails during media deletion

**Strategy:**
```php
// In Media model booted() method
static::deleting(function ($media) {
    try {
        Storage::disk('s3')->delete($media->s3_key);
    } catch (\Exception $e) {
        Log::error('S3 deletion failed', [
            'media_id' => $media->id,
            's3_key' => $media->s3_key,
            'team_id' => $media->team_id,
            'error' => $e->getMessage()
        ]);

        // Still allow DB deletion to proceed
        // Orphaned file will be cleaned up by scheduled job
    }
});
```

**Scheduled Cleanup Job:**
```php
// app/Console/Commands/CleanupOrphanedS3Files.php
// Run weekly: finds S3 files without DB records and deletes them
```

### Network Errors

**API Request Failures:**
- Show toast notification: "Unable to load media. Please try again."
- Provide "Retry" button
- Cache last successful media list for offline graceful degradation

**Real-time Upload Failures:**
- Detect network disconnection during upload
- Pause upload and show status: "Waiting for connection..."
- Resume upload when connection restored

### Authorization Errors

**403 Forbidden:**
- Redirect to dashboard with message: "You don't have permission to access this media."
- Log unauthorized access attempts

**Team Switch During Upload:**
- Cancel in-progress uploads
- Show message: "Upload cancelled due to team change"
- Clear upload queue

### User-Friendly Error Messages

| Technical Error | User Message |
|----------------|--------------|
| `S3Exception: Access Denied` | "Unable to upload. Please try again or contact support." |
| `ValidationException: Invalid mime type` | "This file type is not supported. Please upload images or PDFs." |
| `413 Payload Too Large` | "File is too large. Maximum size is 50MB." |
| `404 Not Found` | "This media item no longer exists." |
| `Network timeout` | "Upload is taking longer than expected. Please check your connection." |

---

## Testing Strategy

### Feature Tests (Pest)

**Media CRUD Tests** (`tests/Feature/MediaTest.php`):
```php
describe('media upload flow', function () {
    it('generates presigned URL for authenticated user', function () {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $user->teams()->attach($team, ['role' => 'member']);
        $user->current_team_id = $team->id;
        $user->save();

        $response = $this->actingAs($user)->postJson('/media/presign', [
            'filename' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024000,
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure(['url', 'fields']);
    });

    it('saves media metadata after S3 upload', function () {
        Storage::fake('s3');
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $user->teams()->attach($team, ['role' => 'member']);
        $user->current_team_id = $team->id;
        $user->save();

        $response = $this->actingAs($user)->postJson('/media', [
            's3_key' => 'teams/1/images/test.jpg',
            'filename' => 'test.jpg',
            'stored_filename' => 'test.jpg',
            'file_path' => 'teams/1/images/test.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024000,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('media', [
            'filename' => 'test.jpg',
            'team_id' => $team->id,
        ]);
    });

    it('prevents uploading invalid file types', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/media/presign', [
            'filename' => 'test.exe',
            'mime_type' => 'application/x-executable',
            'file_size' => 1024000,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('mime_type');
    });

    it('enforces team scoping for media access', function () {
        $user = User::factory()->create();
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();
        $user->teams()->attach($team1, ['role' => 'member']);
        $user->current_team_id = $team1->id;
        $user->save();

        $media = Media::factory()->create(['team_id' => $team2->id]);

        $response = $this->actingAs($user)->getJson("/media/{$media->id}");

        $response->assertForbidden();
    });
});

describe('media filtering', function () {
    it('filters media by tags', function () {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $user->teams()->attach($team, ['role' => 'member']);
        $user->current_team_id = $team->id;
        $user->save();

        $tag = MediaTag::factory()->create(['team_id' => $team->id]);
        $media1 = Media::factory()->create(['team_id' => $team->id]);
        $media2 = Media::factory()->create(['team_id' => $team->id]);
        $media1->tags()->attach($tag);

        $response = $this->actingAs($user)->getJson('/media?tags[]=' . $tag->id);

        $response->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $media1->id);
    });

    it('searches media by filename', function () {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $user->teams()->attach($team, ['role' => 'member']);
        $user->current_team_id = $team->id;
        $user->save();

        Media::factory()->create(['team_id' => $team->id, 'filename' => 'logo.jpg']);
        Media::factory()->create(['team_id' => $team->id, 'filename' => 'banner.jpg']);

        $response = $this->actingAs($user)->getJson('/media?search=logo');

        $response->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.filename', 'logo.jpg');
    });
});

describe('bulk operations', function () {
    it('deletes multiple media items', function () {
        Storage::fake('s3');
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $user->teams()->attach($team, ['role' => 'member']);
        $user->current_team_id = $team->id;
        $user->save();

        $media1 = Media::factory()->create(['team_id' => $team->id]);
        $media2 = Media::factory()->create(['team_id' => $team->id]);

        $response = $this->actingAs($user)->postJson('/media/bulk-delete', [
            'media_ids' => [$media1->id, $media2->id],
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseMissing('media', ['id' => $media1->id]);
        $this->assertDatabaseMissing('media', ['id' => $media2->id]);
    });

    it('adds tags to multiple media items', function () {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $user->teams()->attach($team, ['role' => 'member']);
        $user->current_team_id = $team->id;
        $user->save();

        $tag = MediaTag::factory()->create(['team_id' => $team->id]);
        $media1 = Media::factory()->create(['team_id' => $team->id]);
        $media2 = Media::factory()->create(['team_id' => $team->id]);

        $response = $this->actingAs($user)->postJson('/media/bulk-tag', [
            'media_ids' => [$media1->id, $media2->id],
            'tag_ids' => [$tag->id],
            'action' => 'add_tags',
        ]);

        $response->assertSuccessful();
        expect($media1->fresh()->tags)->toHaveCount(1);
        expect($media2->fresh()->tags)->toHaveCount(1);
    });
});
```

**Media Tag Tests** (`tests/Feature/MediaTagTest.php`):
```php
it('creates team-scoped media tags', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $user->current_team_id = $team->id;
    $user->save();

    $response = $this->actingAs($user)->postJson('/media-tags', [
        'name' => 'Screenshots',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('media_tags', [
        'name' => 'Screenshots',
        'team_id' => $team->id,
    ]);
});

it('prevents duplicate tag names per team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $user->current_team_id = $team->id;
    $user->save();

    MediaTag::factory()->create(['name' => 'Logo', 'team_id' => $team->id]);

    $response = $this->actingAs($user)->postJson('/media-tags', [
        'name' => 'Logo',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('deletes tag and removes from all media', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $user->current_team_id = $team->id;
    $user->save();

    $tag = MediaTag::factory()->create(['team_id' => $team->id]);
    $media = Media::factory()->create(['team_id' => $team->id]);
    $media->tags()->attach($tag);

    $response = $this->actingAs($user)->deleteJson("/media-tags/{$tag->id}");

    $response->assertSuccessful();
    $this->assertDatabaseMissing('media_tags', ['id' => $tag->id]);
    $this->assertDatabaseMissing('media_media_tag', ['media_tag_id' => $tag->id]);
});
```

### Browser Tests (Pest v4)

**Upload Flow Test** (`tests/Browser/MediaUploadTest.php`):
```php
it('uploads image via drag-and-drop', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $user->current_team_id = $team->id;
    $user->save();

    $this->actingAs($user);

    $page = visit('/media');

    $page->assertSee('Media Gallery')
        ->click('Upload Media')
        ->attach('file-input', __DIR__ . '/../fixtures/test-image.jpg')
        ->waitFor('.upload-item')
        ->assertSee('test-image.jpg')
        ->waitFor('.upload-complete', 10) // Wait up to 10s for upload
        ->assertSee('Upload complete');

    $this->assertDatabaseHas('media', [
        'filename' => 'test-image.jpg',
        'team_id' => $team->id,
    ]);
});

it('shows upload progress', function () {
    $page = visit('/media')
        ->click('Upload Media')
        ->attach('file-input', 'large-file.jpg')
        ->waitFor('.progress-bar')
        ->assertSee('%'); // Progress percentage shown
});
```

**Gallery Interaction Test** (`tests/Browser/MediaGalleryTest.php`):
```php
it('switches between list and gallery view', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $user->current_team_id = $team->id;
    $user->save();

    Media::factory()->count(5)->create(['team_id' => $team->id]);

    $this->actingAs($user);

    $page = visit('/media');

    $page->assertSee('Media Gallery')
        ->assertVisible('.media-card') // Gallery view by default
        ->click('[data-test="view-toggle"]')
        ->assertVisible('.media-list-item') // Switched to list
        ->assertNotVisible('.media-card');
});

it('selects multiple items and bulk deletes', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $user->current_team_id = $team->id;
    $user->save();

    Media::factory()->count(3)->create(['team_id' => $team->id]);

    $this->actingAs($user);

    $page = visit('/media');

    $page->check('[data-test="media-checkbox-1"]')
        ->check('[data-test="media-checkbox-2"]')
        ->assertSee('2 items selected')
        ->click('Delete Selected')
        ->assertSee('Are you sure?')
        ->click('Confirm')
        ->assertSee('Media deleted successfully');

    $this->assertDatabaseCount('media', 1); // Only 1 remains
});

it('filters by tags and date range', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $user->teams()->attach($team, ['role' => 'member']);
    $user->current_team_id = $team->id;
    $user->save();

    $tag = MediaTag::factory()->create(['name' => 'Logo', 'team_id' => $team->id]);
    $media1 = Media::factory()->create(['team_id' => $team->id]);
    $media1->tags()->attach($tag);
    Media::factory()->count(2)->create(['team_id' => $team->id]);

    $this->actingAs($user);

    $page = visit('/media');

    $page->click('[data-test="tag-filter"]')
        ->click('Logo')
        ->assertSee('1 result') // Only media with Logo tag shown
        ->assertSee($media1->filename);
});
```

### Unit Tests

**S3 Key Generation Test** (`tests/Unit/MediaTest.php`):
```php
it('generates unique S3 keys', function () {
    $media = new Media([
        'team_id' => 1,
        'mime_type' => 'image/jpeg',
    ]);

    $key1 = $media->generateS3Key();
    $key2 = $media->generateS3Key();

    expect($key1)->not->toBe($key2);
    expect($key1)->toStartWith('teams/1/images/');
    expect($key1)->toEndWith('.jpg');
});

it('determines correct file path for mime type', function () {
    $imageMedia = new Media(['mime_type' => 'image/png']);
    $pdfMedia = new Media(['mime_type' => 'application/pdf']);

    expect($imageMedia->getStorageDirectory())->toBe('images');
    expect($pdfMedia->getStorageDirectory())->toBe('documents');
});
```

### Test Mocking

```php
// Mock S3 in all tests to avoid hitting real AWS
beforeEach(function () {
    Storage::fake('s3');
});

// Mock presigned URL generation for faster tests
Http::fake([
    's3.amazonaws.com/*' => Http::response('', 200),
]);
```

### Test Coverage Goals

- **Feature Tests:** 100% coverage of all API endpoints
- **Browser Tests:** Cover critical user journeys
- **Unit Tests:** Cover helper methods and model logic
- **Authorization Tests:** Verify team scoping on all actions

---

## Implementation Phases

### Phase 1: Core Gallery (MVP) - This Implementation

**Backend:**
- ✅ Create migrations for `media`, `media_tags`, `media_media_tag` tables
- ✅ Create `Media` and `MediaTag` models with relationships
- ✅ Create factories for both models
- ✅ Create `MediaPolicy` and `MediaTagPolicy` for authorization
- ✅ Create Form Requests: `PresignMediaRequest`, `StoreMediaRequest`, `UpdateMediaRequest`, `BulkActionRequest`
- ✅ Implement `MediaController` with all 8 endpoints
- ✅ Implement `MediaTagController` with CRUD endpoints
- ✅ Create activity log events: `MediaUploaded`, `MediaUpdated`, `MediaDeleted`, `MediaBulkDeleted`
- ✅ Add routes to `routes/web.php`
- ✅ Implement S3 cleanup in `Media` model's `booted()` method

**Frontend:**
- ✅ Create `Media/Index.vue` with gallery and list views
- ✅ Create `Media/Show.vue` for single media detail
- ✅ Create `Media/Tags/Index.vue` for tag management
- ✅ Create 7 Vue components in `components/Media/`
- ✅ Implement presigned URL upload flow in `MediaUploader.vue`
- ✅ Add search, filter, and bulk action functionality
- ✅ Implement view mode toggle with localStorage persistence
- ✅ Add empty states for no media, no results, no tags

**Testing:**
- ✅ Write 20+ feature tests covering all endpoints
- ✅ Write 5+ browser tests for critical flows
- ✅ Write unit tests for helper methods
- ✅ Achieve >90% code coverage

**Configuration:**
- ✅ Set up AWS S3 bucket per Step 4 guide
- ✅ Configure IAM user and policy
- ✅ Update `.env` with AWS credentials
- ✅ Configure CORS on S3 bucket

**Deliverables:**
- Fully functional media gallery
- Upload, organize, search, and delete media
- Team-scoped with authorization
- Direct S3 uploads with progress tracking
- Comprehensive test coverage

### Phase 2: Content Integration (Future)

**Database:**
- Create `content_piece_media` pivot table
- Add polymorphic relationships for attaching media to any entity

**Backend:**
- Add `attachMedia()` and `detachMedia()` methods to `ContentPiece` model
- Create `MediaAttachmentController` for managing attachments
- Update `ContentPiecePolicy` to include media attachment permissions

**Frontend:**
- Create `MediaPicker.vue` modal component
- Integrate media picker into content editor
- Show attached media in content list view
- Add "Featured Image" selection

**Features:**
- Attach multiple media items to content pieces
- Set featured/cover image for content
- Preview attached images in content list
- Drag-and-drop image insertion in editor

### Phase 3: Advanced Features (Future)

**Thumbnail Generation:**
- Implement image processing with Intervention Image
- Generate small (150px), medium (500px), large (1200px) thumbnails
- Store in `teams/{team_id}/thumbnails/{size}/` directory
- Update `Media` model to include thumbnail URLs

**Storage Quotas:**
- Add `storage_limit_mb` and `storage_used_mb` to `teams` table
- Track storage usage on upload/delete
- Enforce limits in `PresignMediaRequest`
- Add storage usage dashboard to team settings

**AI-Powered Features:**
- Auto-tagging based on image recognition
- Alt text generation for accessibility
- Duplicate image detection
- Smart search by image content

**Additional Features:**
- Bulk download as ZIP file
- Image metadata extraction (EXIF, dimensions)
- Color palette extraction
- Usage analytics (most used images)
- Media library for reusable assets
- Version history for edited images

---

## File Checklist

### Backend Files to Create

**Models:**
- [ ] `app/Models/Media.php`
- [ ] `app/Models/MediaTag.php`

**Controllers:**
- [ ] `app/Http/Controllers/MediaController.php`
- [ ] `app/Http/Controllers/MediaTagController.php`

**Form Requests:**
- [ ] `app/Http/Requests/Media/PresignMediaRequest.php`
- [ ] `app/Http/Requests/Media/StoreMediaRequest.php`
- [ ] `app/Http/Requests/Media/UpdateMediaRequest.php`
- [ ] `app/Http/Requests/Media/BulkActionRequest.php`
- [ ] `app/Http/Requests/MediaTag/StoreMediaTagRequest.php`
- [ ] `app/Http/Requests/MediaTag/UpdateMediaTagRequest.php`

**Policies:**
- [ ] `app/Policies/MediaPolicy.php`
- [ ] `app/Policies/MediaTagPolicy.php`

**Events:**
- [ ] `app/Events/MediaUploaded.php`
- [ ] `app/Events/MediaUpdated.php`
- [ ] `app/Events/MediaDeleted.php`
- [ ] `app/Events/MediaBulkDeleted.php`

**Migrations:**
- [ ] `database/migrations/YYYY_MM_DD_create_media_table.php`
- [ ] `database/migrations/YYYY_MM_DD_create_media_tags_table.php`
- [ ] `database/migrations/YYYY_MM_DD_create_media_media_tag_table.php`

**Factories:**
- [ ] `database/factories/MediaFactory.php`
- [ ] `database/factories/MediaTagFactory.php`

**Tests:**
- [ ] `tests/Feature/MediaTest.php`
- [ ] `tests/Feature/MediaTagTest.php`
- [ ] `tests/Feature/MediaBulkActionsTest.php`
- [ ] `tests/Feature/MediaAuthorizationTest.php`
- [ ] `tests/Browser/MediaUploadTest.php`
- [ ] `tests/Browser/MediaGalleryTest.php`
- [ ] `tests/Unit/MediaTest.php`

**Routes:**
- [ ] Add media routes to `routes/web.php`

### Frontend Files to Create

**Pages:**
- [ ] `resources/js/Pages/Media/Index.vue`
- [ ] `resources/js/Pages/Media/Show.vue`
- [ ] `resources/js/Pages/Media/Tags/Index.vue`

**Components:**
- [ ] `resources/js/components/Media/MediaCard.vue`
- [ ] `resources/js/components/Media/MediaListItem.vue`
- [ ] `resources/js/components/Media/MediaUploader.vue`
- [ ] `resources/js/components/Media/MediaFilters.vue`
- [ ] `resources/js/components/Media/MediaBulkActions.vue`
- [ ] `resources/js/components/Media/MediaTagInput.vue`
- [ ] `resources/js/components/Media/MediaPreview.vue`

**TypeScript Types:**
- [ ] `resources/js/types/media.ts` (Media, MediaTag interfaces)

### Configuration Files to Update

- [ ] `config/filesystems.php` (verify S3 disk configuration)
- [ ] `.env` (add AWS credentials)
- [ ] `routes/web.php` (add media routes)

### Documentation Files

- [x] `docs/plans/media-gallery.md` (this file)

---

## Next Steps

1. **AWS Setup (30 minutes)**
   - Follow Section "AWS S3 Setup Guide" step-by-step
   - Create bucket, IAM user, configure CORS
   - Update `.env` with credentials
   - Test S3 connection with tinker

2. **Backend Implementation (4-6 hours)**
   - Run Artisan commands to generate models, controllers, policies
   - Write migrations and run them
   - Implement upload flow with presigned URLs
   - Write feature tests and ensure they pass

3. **Frontend Implementation (6-8 hours)**
   - Create Vue pages and components
   - Implement upload UI with progress tracking
   - Build gallery and list views
   - Add search, filter, and bulk actions
   - Test in browser

4. **Integration Testing (2-3 hours)**
   - Write browser tests with Pest v4
   - Test full upload flow end-to-end
   - Test multi-select and bulk operations
   - Test filters and search

5. **Code Review & Refinement (1-2 hours)**
   - Run `vendor/bin/pint --dirty` to format code
   - Run full test suite: `php artisan test`
   - Review UI/UX with stakeholders
   - Fix any issues found

6. **Deployment (1 hour)**
   - Update production `.env` with production AWS credentials
   - Run migrations on production
   - Deploy frontend assets: `npm run build`
   - Smoke test in production

**Total Estimated Time:** 14-20 hours

---

## Success Criteria

✅ Users can upload images and PDFs directly to S3
✅ Uploads show real-time progress
✅ Gallery has both list and grid views
✅ Search works by filename
✅ Filtering works by tags, file type, and date
✅ Users can select multiple items and bulk delete
✅ Users can add/remove tags in bulk
✅ All media is team-scoped (no cross-team access)
✅ Deleting media removes S3 files
✅ All endpoints have authorization checks
✅ Activity logs track media operations
✅ Tests cover >90% of code
✅ UI is responsive and accessible

---

## Future Considerations

**When to implement Phase 2 (Content Integration):**
- After team feedback on standalone gallery
- When content editors request media insertion
- Estimated: 1-2 weeks additional development

**When to implement Phase 3 (Advanced Features):**
- Storage quotas: When teams approach abuse or tiered pricing needed
- Thumbnail generation: When gallery performance degrades with large images
- AI features: When budget allows for AI API costs

**Alternative Technologies Considered:**
- Cloudinary (rejected: vendor lock-in, expensive for high volume)
- imgix (rejected: adds complexity, not needed without transformations)
- Local storage (rejected: not scalable, complicates deployments)

---

## Appendix: Code Snippets

### Media Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Media extends Model
{
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

    protected $casts = [
        'metadata' => 'array',
        'file_size' => 'integer',
    ];

    protected static function booted()
    {
        static::deleting(function ($media) {
            try {
                Storage::disk('s3')->delete($media->s3_key);
            } catch (\Exception $e) {
                \Log::error('S3 deletion failed', [
                    'media_id' => $media->id,
                    's3_key' => $media->s3_key,
                    'error' => $e->getMessage()
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
        return match($this->mime_type) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }

    public function getTemporaryUrl(int $expiryMinutes = 60): string
    {
        return Storage::disk('s3')->temporaryUrl(
            $this->s3_key,
            now()->addMinutes($expiryMinutes)
        );
    }
}
```

### MediaController - Presign Method

```php
public function presign(PresignMediaRequest $request)
{
    $validated = $request->validated();

    $media = new Media([
        'team_id' => auth()->user()->current_team_id,
        'mime_type' => $validated['mime_type'],
    ]);

    $s3Key = $media->generateS3Key();

    $client = Storage::disk('s3')->getClient();
    $bucket = config('filesystems.disks.s3.bucket');

    $formInputs = [
        'acl' => 'private',
        'Content-Type' => $validated['mime_type'],
        'key' => $s3Key,
    ];

    $options = [
        ['acl' => 'private'],
        ['bucket' => $bucket],
        ['starts-with', '$key', "teams/{$media->team_id}/"],
        ['eq', '$Content-Type', $validated['mime_type']],
        ['content-length-range', 0, $validated['file_size']],
    ];

    $postObject = new \Aws\S3\PostObjectV4(
        $client,
        $bucket,
        $formInputs,
        $options,
        '+15 minutes'
    );

    return response()->json([
        'url' => $postObject->getFormAttributes()['action'],
        'fields' => $postObject->getFormInputs(),
        's3_key' => $s3Key,
    ]);
}
```

---

**Document Status:** ✅ Complete and ready for implementation
**Last Updated:** 2025-01-21
**Author:** System Design (via Brainstorming Skill)