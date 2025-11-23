# Ovimo Application Architecture

This document provides a comprehensive overview of the Ovimo application architecture to help developers and AI agents understand the system quickly.

## Application Purpose

Ovimo is a **multi-tenant content monitoring and AI-powered content generation platform** that:

1. Monitors external content sources (RSS feeds, websites)
2. Automatically discovers and imports posts
3. Uses AI to analyze relevancy and generate summaries
4. Enables content creation based on discovered posts
5. Provides webhook integrations for external systems

## Core Domain Model

```
Team (tenant)
├── Users (many-to-many with roles)
├── Sources (content feeds to monitor)
│   ├── Posts (discovered content)
│   │   └── ContentPieces (AI-generated content)
│   └── Tags (categorization)
├── Prompts (AI prompt templates)
├── Webhooks (external integrations)
├── TokenUsageLogs (AI usage tracking)
└── ActivityLogs (audit trail for all team events)
```

### Entity Relationships

| Entity | Description | Key Relationships |
|--------|------------|-------------------|
| **Team** | Multi-tenant organization unit | Has owner (User), many Users, Sources, Tags, Prompts, ContentPieces, Webhooks |
| **User** | Application user | Belongs to Teams (many-to-many), has current_team_id |
| **Source** | Content feed to monitor (RSS, website) | Belongs to Team, has many Posts, many Tags |
| **Post** | Discovered content item | Belongs to Source, many ContentPieces (via pivot) |
| **ContentPiece** | AI-generated content | Belongs to Team and Prompt, many Posts |
| **Prompt** | AI prompt template for content generation | Belongs to Team, has many ContentPieces |
| **Tag** | Categorization label | Belongs to Team, many Sources (via pivot) |
| **Webhook** | External notification endpoint | Belongs to Team |
| **TokenUsageLog** | AI token consumption record | Belongs to Team and User |
| **ActivityLog** | Activity audit trail | Belongs to Team, optional User/Source/Post |

## Backend Architecture

### Directory Structure

```
app/
├── Actions/Fortify/          # Authentication actions (user creation, password reset)
├── Console/Commands/         # Artisan commands (ScheduleSourceMonitoring)
├── Events/                   # Application events (14 types for activity logging)
├── Http/
│   ├── Controllers/          # Inertia page controllers
│   │   └── Settings/         # User settings controllers
│   ├── Middleware/           # Custom middleware (team validation, token limits)
│   └── Requests/             # Form validation requests
├── Jobs/                     # Queue jobs
│   ├── MonitorSource.php     # Fetches new posts from sources
│   ├── SummarizePost.php     # AI summarization
│   ├── SendWebhookNotification.php
│   └── PruneOldActivityLogs.php  # Cleanup job (daily)
├── Listeners/                # Event listeners
│   └── LogActivityToDatabase.php  # Unified activity logging listener
├── Models/                   # Eloquent models
├── Observers/                # Model observers (UserObserver for 2FA detection)
├── Policies/                 # Authorization policies (SourcePolicy, PostPolicy, etc.)
├── Providers/                # Service providers (App, Fortify)
└── Services/                 # Business logic
    ├── OpenAIService.php     # AI integration
    ├── SourceParser.php      # Content feed parsing
    └── WebContentExtractor.php
```

### Controllers (Resource-based)

- `SourceController` - CRUD for content sources + manual check trigger
- `PostController` - List posts, bulk actions (hide, mark read, delete)
- `ContentPieceController` - CRUD + AI content generation
- `PromptController` - CRUD for AI prompt templates
- `WebhookController` - CRUD for webhook configurations + test
- `UsageController` - Token usage statistics
- `ActivityLogController` - View activity logs with filtering
- `SettingsController` - Team settings management
- `Settings/*` - User profile, password, 2FA settings

### Background Jobs

| Job | Trigger | Purpose | Deduplication |
|-----|---------|---------|---------------|
| `MonitorSource` | Scheduled via `ScheduleSourceMonitoring` | Parses source URL, creates new Posts | Database lock |
| `SummarizePost` | When auto_summarize enabled | Uses AI to analyze and summarize post | None |
| `SendWebhookNotification` | On events (NEW_POSTS, etc.) | Delivers webhook payloads | None |
| `PruneOldActivityLogs` | Scheduled daily at midnight | Deletes activity logs older than 30 days | None |

#### Queue Job Deduplication Pattern

When running multiple queue workers in parallel (e.g., `numprocs=4` in Laravel Forge), jobs can be processed by multiple workers simultaneously. For jobs that must only run once per entity, use **database-level pessimistic locking** instead of Laravel's `ShouldBeUnique` interface (which requires Redis/Memcached for atomic locks).

**Implementation Pattern** (see `MonitorSource` job):

```php
public function handle(...)
{
    // Acquire exclusive lock at start of job execution
    $locked = DB::transaction(function () {
        $entity = Model::where('id', $this->entity->id)
            ->lockForUpdate()
            ->first();

        if (!$entity) {
            return false;
        }

        // Check if already processed recently (deduplication window)
        if ($entity->last_checked_at && $entity->last_checked_at->gt(now()->subSeconds(30))) {
            Log::info("Job skipped: entity {$entity->id} was recently processed");
            return false;
        }

        // Mark as processing to prevent other workers
        $entity->update(['last_checked_at' => now()]);

        return true;
    });

    if (!$locked) {
        return; // Skip - another worker is handling this
    }

    // Reload entity and proceed with actual work
    $this->entity->refresh();

    // ... rest of job logic ...
}
```

**Key Points**:
- Uses `lockForUpdate()` for database-level row locking (works with any cache driver)
- Checks recent processing timestamp (30-second window) to catch duplicate jobs
- Updates timestamp immediately to prevent other workers from proceeding
- Returns early if another worker already processed the entity
- Works with multiple parallel workers without Redis/Memcached dependency

**When to Use**:
- Jobs that process specific entities (sources, posts, users)
- High-frequency scheduled jobs (every minute)
- Multiple queue workers running in parallel
- Database cache driver (not Redis/Memcached)

**When NOT to Use**:
- Jobs that should run multiple times concurrently
- Jobs without specific entity ownership
- Single queue worker deployments

### Services Layer

- **OpenAIService**: Handles AI API interactions, token tracking
- **SourceParser**: Parses RSS/XML feeds and website content
- **WebContentExtractor**: Extracts readable content from web pages

### Key Business Logic

1. **Source Monitoring Flow**:
   ```
   ScheduleSourceMonitoring (cron, every minute)
   → Queries active sources with next_check_at <= now()
   → Locks each source in transaction (lockForUpdate)
   → Updates next_check_at to reserve (prevents duplicate dispatch)
   → Dispatches MonitorSource job for each source

   MonitorSource job (in queue worker)
   → Acquires database lock on source
   → Checks if recently processed (30-second deduplication window)
   → Skips if another worker already processed
   → Parses feed/website and creates Post records
   → Updates source: last_checked_at, next_check_at, status tracking
   → Optionally triggers SummarizePost (if auto_summarize enabled)
   → Sends webhook notifications (if new posts found)
   ```

   **Scheduler Configuration** (`routes/console.php`):
   - Runs every minute with `withoutOverlapping()` to prevent concurrent scheduler instances
   - Limits to 500 sources per run to prevent catch-up storms
   - Uses `limit(500)->get()->each()` instead of `chunkById()` for better performance

2. **Content Generation Flow**:
   ```
   User selects Posts + Prompt
   → ContentPieceController generates via OpenAIService
   → Logs token usage
   → Returns generated content
   ```

3. **Activity Logging Flow** (Event-Driven):
   ```
   Application Event Triggered (14 event types)
   → Event dispatched (queued for async processing)
   → LogActivityToDatabase listener receives event
   → Extracts relevant data (team, user, metadata)
   → Creates ActivityLog record
   → Pruned after 30 days by scheduled job
   ```

4. **Source Intervals**: `EVERY_10_MIN`, `EVERY_30_MIN`, `HOURLY`, `EVERY_6_HOURS`, `DAILY`, `WEEKLY`

5. **Post Status Values**: `NOT_RELEVANT`, plus relevancy_score tracking

## Frontend Architecture

### Stack

- **Vue 3** (Composition API, `<script setup>`)
- **Inertia.js v2** (SPA routing without API)
- **Tailwind CSS v4** (CSS-first configuration)
- **Reka UI** (headless accessible components)
- **shadcn/ui** (styled component wrappers)
- **Laravel Wayfinder** (type-safe route generation)
- **Lucide Icons** (`lucide-vue-next`, wrapped for shorthand names)

### Directory Structure

```
resources/js/
├── Pages/                    # Inertia pages (route endpoints)
│   ├── auth/                 # Authentication pages
│   ├── settings/             # User settings
│   ├── Sources/              # Source CRUD pages
│   ├── Posts/                # Post management
│   ├── ContentPieces/        # Content generation
│   ├── Prompts/              # Prompt management
│   ├── Webhooks/             # Webhook configuration
│   ├── Usage/                # Token usage stats
│   └── ActivityLogs/         # Activity log viewer
├── layouts/                  # Page layout wrappers
│   ├── app/                  # AppSidebarLayout, AppHeaderLayout
│   ├── auth/                 # Authentication layouts
│   └── settings/             # Settings layout
├── components/               # Reusable Vue components
│   ├── ui/                   # shadcn/ui primitives (Button, Dialog, etc.)
│   └── *.vue                 # App-specific components
└── actions/                  # Wayfinder-generated route functions (auto-generated)
```

### Page Structure Pattern

```vue
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'

// Props from controller
defineProps<{
  items: Array<...>
}>()
</script>

<template>
  <AppLayout>
    <Head title="Page Title" />
    <!-- Page content -->
  </AppLayout>
</template>
```

### Form Patterns

1. **Inertia `<Form>` Component** (preferred):
   ```vue
   <Form action="/endpoint" method="post" #default="{ errors, processing }">
     <Input name="field" />
     <Button :disabled="processing">Submit</Button>
   </Form>
   ```

## Icon Usage (Frontend)

- Icons come from `lucide-vue-next`.
- Preferred: use the shared `Icon.vue` wrapper for kebab-cased names (`file-text`, `wand-sparkles`, `star`). The wrapper:
  - Tries multiple Lucide export candidates (`Name`, `NameIcon`, `LucideName`, `LucideNameIcon`).
  - Warns in dev when a name cannot be resolved.
  - Falls back to `HelpCircle` to avoid broken renders.
- If you need to guarantee a specific glyph (no fallback), import the component directly:
  ```vue
  <script setup lang="ts">
  import { WandSparkles } from 'lucide-vue-next';
  </script>

  <template>
    <WandSparkles class="h-4 w-4 text-gray-700" />
  </template>
  ```
- Discover icons by browsing `node_modules/lucide-vue-next/dist/esm/icons` or by listing exports:
  - `node -e "const icons = require('lucide-vue-next'); console.log(Object.keys(icons).slice(0, 200))"`

2. **useForm Helper** (programmatic control):
   ```vue
   const form = useForm({ field: '' })
   form.post('/endpoint')
   ```

3. **Wayfinder Integration**:
   ```vue
   import { store } from '@/actions/App/Http/Controllers/SourceController'
   <Form v-bind="store.form()">...</Form>
   ```

### Component Conventions

- **UI Primitives** (`components/ui/`): Direct wrappers around Reka UI
- **App Components** (`components/`): Composition of UI primitives
- **Reka UI Pattern**: Uses `modelValue`/`update:modelValue` (see `docs/ui-components.md`)

### Inertia Integration Patterns

#### CSRF Token Handling

Laravel requires CSRF tokens for all POST/PUT/PATCH/DELETE requests. When using Inertia:

1. **Add CSRF meta tag** in `resources/views/app.blade.php`:
   ```html
   <meta name="csrf-token" content="{{ csrf_token() }}">
   ```

2. **Inertia forms handle CSRF automatically** when using `useForm()` or `<Form>` component

3. **Manual form submissions** (e.g., for file downloads) must include the token:
   ```typescript
   const form = document.createElement('form');
   form.method = 'POST';
   form.action = '/endpoint';

   const csrfInput = document.createElement('input');
   csrfInput.type = 'hidden';
   csrfInput.name = '_token';
   csrfInput.value = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
   form.appendChild(csrfInput);

   document.body.appendChild(form);
   form.submit();
   ```

4. **Common CSRF errors**:
   - 419 "Page Expired" = Missing or invalid CSRF token
   - "CSRF token mismatch" = Token not properly included in request

#### Flash Messages

Flash messages pass one-time data from backend to frontend (success/error notifications).

1. **Share flash data** in `app/Http/Middleware/HandleInertiaRequests.php`:
   ```php
   public function share(Request $request): array
   {
       return [
           ...parent::share($request),
           'flash' => [
               'success' => $request->session()->get('success'),
               'error' => $request->session()->get('error'),
           ],
       ];
   }
   ```

2. **Set flash messages** in controllers:
   ```php
   return redirect('/route')->with('success', 'Operation completed!');
   return back()->with('error', 'Something went wrong.');
   ```

3. **Access flash messages** in Vue components:
   ```vue
   <script setup>
   import { usePage } from '@inertiajs/vue3'

   const page = usePage()
   </script>

   <template>
     <div v-if="page.props.flash?.success">
       {{ page.props.flash.success }}
     </div>
   </template>
   ```

4. **Flash messages are automatically cleared** after being displayed once

5. **Preserve query parameters** when redirecting with flash messages:
   ```php
   // Redirect to specific tab with flash message
   return redirect('/settings?tab=notifications')->with('success', 'Settings saved!');
   ```

## Database Schema

**Engine**: SQLite (development), configurable for production

### Core Tables

| Table | Purpose | Key Fields |
|-------|---------|-----------|
| `teams` | Multi-tenant organizations | `owner_id`, `relevancy_prompt`, `webhook_url`, `monthly_token_limit` |
| `users` | User accounts | `current_team_id`, `monthly_token_limit`, 2FA fields |
| `team_user` | Team membership pivot | `role` field |
| `sources` | Content feeds | `type`, `url`, `monitoring_interval`, `next_check_at`, `auto_summarize` |
| `posts` | Discovered content | `uri`, `relevancy_score`, `is_read`, `is_hidden`, `status` |
| `content_pieces` | Generated content | `channel`, `target_language`, `briefing_text`, `full_text` |
| `prompts` | AI prompt templates | `channel`, `prompt_text` |
| `tags` | Categorization | `name` (unique per team) |
| `webhooks` | External integrations | `event`, `url`, `secret`, `failure_count` |
| `token_usage_logs` | AI consumption tracking | `input_tokens`, `output_tokens`, `model`, `operation` |
| `activity_logs` | Activity audit trail | `event_type`, `level`, `description`, `metadata` (JSON), `ip_address`, `user_agent` |

### Pivot Tables

- `source_tag` - Sources ↔ Tags
- `content_piece_post` - ContentPieces ↔ Posts
- `team_user` - Teams ↔ Users (with role)

### Important Indexes

- `posts(source_id, uri)` - UNIQUE, prevents duplicates
- `posts(source_id, relevancy_score)` - Sorting by relevance
- `sources(team_id, is_active, next_check_at)` - Scheduling queries
- `token_usage_logs(team_id, created_at)` - Usage reporting
- `activity_logs(team_id, created_at)` - Log browsing and filtering
- `activity_logs(team_id, event_type, created_at)` - Filtered log queries

## Authentication & Authorization

- **Laravel Fortify** handles authentication (login, register, 2FA, password reset)
- **Team-based multi-tenancy**: User switches teams via `current_team_id`
- **Team roles**: Stored in `team_user.role` pivot field
- **Team scoping**: Most queries filter by `auth()->user()->currentTeam`

### Authorization Policies

All team-scoped models use Laravel Policies for authorization:

| Policy | Model | Custom Methods |
|--------|-------|----------------|
| `SourcePolicy` | Source | `check` (trigger monitoring) |
| `PostPolicy` | Post | Standard CRUD |
| `ContentPiecePolicy` | ContentPiece | `generate` (AI content generation) |
| `PromptPolicy` | Prompt | Standard CRUD |
| `WebhookPolicy` | Webhook | `test` (send test webhook) |
| `ActivityLogPolicy` | ActivityLog | `viewAny` only (read-only) |

**Usage in Controllers**:
```php
// Base Controller includes AuthorizesRequests trait
$this->authorize('view', $source);
$this->authorize('update', $contentPiece);
$this->authorize('generate', $contentPiece); // Custom method
```

**Policy Pattern** (checks team ownership):
```php
public function view(User $user, Source $source): bool
{
    return $source->team_id === $user->current_team_id;
}
```

### Middleware Stack

Custom middleware registered in `bootstrap/app.php`:

| Alias | Middleware | Purpose |
|-------|-----------|---------|
| `team.valid` | `EnsureValidTeamMembership` | Verifies user belongs to current team, auto-corrects invalid selection |
| `token.limit` | `EnsureTokenLimitNotExceeded` | Enforces monthly AI token limits before operations |

**Route Protection**:
```php
// All authenticated routes require valid team membership
Route::middleware(['auth', 'verified', 'team.valid'])->group(function () {
    // AI operations additionally check token limits
    Route::post('sources/analyze-webpage', ...)
        ->middleware('token.limit');
    Route::post('content-pieces/{content_piece}/generate', ...)
        ->middleware('token.limit');
});
```

**Team Membership Middleware** (`EnsureValidTeamMembership`):
- Validates user belongs to `current_team_id` (via pivot or ownership)
- Auto-switches to first valid team if invalid
- Returns 403 if user has no teams at all

**Token Limit Middleware** (`EnsureTokenLimitNotExceeded`):
- Checks monthly token usage against `team.monthly_token_limit`
- Returns 429 with usage statistics if exceeded
- Zero limit means unlimited (no restriction)

## Activity Logging System

The application uses an event-driven activity logging system to track all important actions across the application. Logs are team-scoped, automatically pruned after 30 days, and displayed in a filterable web UI.

### Architecture Overview

**Event-Driven Pattern**:
```
Action occurs → Event dispatched (queued) → LogActivityToDatabase listener → ActivityLog created
```

**Components**:
- **Events** (`app/Events/`) - 14 queued event classes capturing different activity types
- **Listener** (`app/Listeners/LogActivityToDatabase.php`) - Single unified listener handling all events
- **Model** (`app/Models/ActivityLog.php`) - Stores log records with team scoping
- **Controller** (`app/Http/Controllers/ActivityLogController.php`) - Displays logs with filtering
- **Observer** (`app/Observers/UserObserver.php`) - Detects 2FA changes via model observation
- **Cleanup Job** (`app/Jobs/PruneOldActivityLogs.php`) - Daily pruning of logs older than 30 days

### Event Categories

The system tracks 14 event types across 3 categories:

**User Events** (5):
- `user.login` - User login
- `user.2fa_enabled` - Two-factor authentication enabled
- `user.2fa_disabled` - Two-factor authentication disabled
- `user.password_changed` - Password changed
- `user.password_reset` - Password reset via email

**Domain Events** (4):
- `post.found` - New post discovered from source
- `source.created` - Source created
- `source.updated` - Source updated
- `source.deleted` - Source deleted

**Error/Warning Events** (5):
- `source.monitoring_failed` - Source monitoring job failed
- `content.generation_failed` - Content generation failed
- `openai.request_failed` - OpenAI API request failed
- `webhook.delivery_failed` - Webhook delivery failed
- `token.limit_exceeded` - Token limit exceeded

### Adding New Activity Log Events

Follow these steps to add a new logged event:

#### 1. Create the Event Class

```php
// app/Events/YourNewEvent.php
namespace App\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class YourNewEvent implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Team $team,
        public ?User $user = null,
        // Add any other context data needed
    ) {}
}
```

**Key Requirements**:
- Must implement `ShouldQueue` for async processing
- Use public constructor properties for auto-assignment
- Always include `$team` (required) and `$user` (optional when available)
- Add any additional context needed (source, post, error message, metadata)

#### 2. Add Event Type to ActivityLog Model

```php
// app/Models/ActivityLog.php
public const EVENT_TYPES = [
    // ... existing types ...
    'your.new_event' => 'Your New Event',  // key => display label
];
```

**Convention**: Use dot notation (category.action) for event type keys.

#### 3. Add Event Handler to Listener

```php
// app/Listeners/LogActivityToDatabase.php
public function handle(object $event): void
{
    $logData = match (true) {
        // ... existing handlers ...
        $event instanceof YourNewEvent => [
            'team_id' => $event->team->id,
            'user_id' => $event->user?->id,  // Nullable
            'event_type' => 'your.new_event',
            'level' => 'info',  // or 'warning', 'error'
            'description' => 'Description of what happened',
            'ip_address' => request()->ip(),  // Optional
            'user_agent' => request()->userAgent(),  // Optional
            'metadata' => [  // Optional JSON data
                'additional' => 'context',
            ],
        ],
        default => null,
    };

    if ($logData !== null) {
        ActivityLog::create($logData);
    }
}
```

**Field Guidelines**:
- `team_id` - Required, always from `$event->team->id`
- `user_id` - Optional, use `?->id` for nullable users
- `event_type` - Required, must match key in `EVENT_TYPES` constant
- `level` - Required, use `'info'`, `'warning'`, or `'error'`
- `description` - Optional, human-readable summary
- `source_id`, `post_id` - Optional, when event relates to these entities
- `ip_address`, `user_agent` - Optional, for user-initiated actions
- `metadata` - Optional, array of additional context (stored as JSON)

#### 4. Register Event in AppServiceProvider

```php
// app/Providers/AppServiceProvider.php
Event::listen([
    // ... existing events ...
    YourNewEvent::class,
], LogActivityToDatabase::class);
```

#### 5. Dispatch the Event

Dispatch from controllers, jobs, or other application code:

```php
// In your controller or job
event(new YourNewEvent($team, $user));
```

**Best Practices**:
- Dispatch after successful operations (not before)
- Include all relevant context in event constructor
- Use nullable types for optional data (`?User $user`)
- For delete operations, capture data before deletion

### Special Cases

**1. Laravel Authentication Events** (Login, PasswordReset):
- Handled in `FortifyServiceProvider::configureActivityLogging()`
- Listens to Laravel's built-in auth events
- Dispatches our custom events with team context

**2. Model Observation** (2FA Changes):
- `UserObserver` watches `two_factor_secret` field changes
- Dispatches `TwoFactorEnabled`/`TwoFactorDisabled` events
- Used because Fortify doesn't fire explicit 2FA events

**3. Delete Operations** (SourceDeleted):
- Capture source data before calling `$source->delete()`
- Pass captured data to event constructor
- Source model is unavailable after deletion

### Viewing Activity Logs

- **URL**: `/activity-logs` (requires authentication)
- **Filters**: Event type, date range (from/to)
- **Default**: Last 7 days of team's logs
- **Details**: Click "View Details" to see full context including JSON metadata
- **Retention**: Logs automatically deleted after 30 days

### Database Schema

```sql
activity_logs (
    id, team_id, user_id (nullable),
    event_type, level, description (nullable),
    source_id (nullable), post_id (nullable),
    ip_address (nullable), user_agent (nullable),
    metadata (JSON, nullable),
    created_at
)
```

**Indexes**:
- `(team_id, created_at)` - For browsing team logs
- `(team_id, event_type, created_at)` - For filtered queries

## Key Patterns for Agents

### Creating New Features

1. **Model**: `php artisan make:model --all` (includes migration, factory, seeder)
2. **Policy**: `php artisan make:policy ModelPolicy --model=Model` for authorization
3. **Controller**: Resource controller pattern with Form Requests and `$this->authorize()` calls
4. **Form Request**: Validation + custom messages in dedicated request class
5. **Vue Page**: Place in `resources/js/Pages/`, use layout wrapper
6. **Routes**: Add to `routes/web.php`, use Wayfinder for type-safe frontend imports
7. **Tests**: Create feature tests covering CRUD + authorization + validation

### Multi-Tenancy

Always scope queries to the current team:
```php
$team = auth()->user()->currentTeam;
$team->sources()->where(...);
```

### Media Gallery (Files + Tags)

**Purpose**: Team-scoped upload/view/manage of images and PDFs with tagging, bulk actions, and S3-backed storage.

**Server flow**
- Routes: `routes/web.php` (`media.*`, `media-tags.*`).
- Controller: `App\Http\Controllers\MediaController` lists, presigns uploads, stores metadata, updates tags/filename, bulk deletes/tags, shows details.
- Tag CRUD: `MediaTagController`.
- Policies: `MediaPolicy`, `MediaTagPolicy` enforce team isolation and auth.
- Requests: `PresignMediaRequest`, `StoreMediaRequest`, `UpdateMediaRequest`, `BulkActionRequest`, plus tag store/update requests handle validation.
- Model: `Media` belongs to `Team`, `uploader` (`User`), many `MediaTag` via pivot. `getTemporaryUrl()` builds signed read URLs.
- Listing: query scoped to `current_team_id`, filters (search, tags, file type, date range), sort (filename or uploaded date), pagination (20). Search normalizes diacritics (NFD/NFC) so umlauts match.
- Events: `MediaUploaded`, `MediaUpdated`, `MediaDeleted`, `MediaBulkDeleted` emit for activity/logging.

**Frontend flow**
- Page: `resources/js/Pages/Media/Index.vue` (grid/list, sort, filters, pagination).
  - Filters component (`MediaFilters.vue`) syncs search, tag, type, date, sort.
  - Tag picker (`MediaTagInput.vue`) supports inline tag creation, searchable chips, and high-contrast selection state.
  - Bulk selection: “Select page” plus per-card/list checkboxes; bottom bulk-action bar stays visible while items remain selected.
  - Sorting in list view via clickable table headers (File, Uploaded) with direction indicators.
  - Upload dialog: `MediaUploader` calls `/media/presign`, then posts metadata to `/media`.
  - Bulk actions: bulk delete, bulk add/remove tags.
  - Preview modal for images/PDFs uses temporary URLs.
- Detail page: `resources/js/Pages/Media/Show.vue` edits filename/tags and shows metadata.

**Upload + S3 presign**
- Presign endpoint: `MediaController@presign` creates a `Media` instance in memory to derive `s3_key` (`teams/{team_id}/...`) and returns a POST form (`PostObjectV4`) with ACL `private`, exact `Content-Type`, and `content-length-range`.
- Upload flow: frontend posts the file directly to S3 with returned `url`/`fields`, then calls `/media` with `s3_key`, `stored_filename`, `filename`, `mime_type`, `file_size`. Validation ensures `s3_key` is under the team prefix.
- Reads: `getTemporaryUrl()` signs GET access for previews (private bucket).

**S3/IAM setup checklist (from-scratch deployment)**
- Bucket: create private bucket (no public ACLs). Optional: enable versioning; enable default encryption (SSE-S3 or SSE-KMS).
- Folder convention: objects live under `teams/{team_id}/...` (images/documents). No special folder config required beyond IAM permissions.
- CORS (bucket): allow `POST`, `PUT`, `GET`, `HEAD` from your app origin; allow `Content-Type`, `acl`, `x-amz-*`; expose `ETag`. Keep `max-age` reasonable (e.g., 300).
- IAM user/role for app:
  - Permissions limited to the bucket: `s3:PutObject`, `s3:GetObject`, `s3:DeleteObject`, `s3:ListBucket` with prefix `teams/*`.
  - If using `PostObjectV4`, also allow `s3:PutObject` with `s3:ListBucket` for the prefix and `s3:GetBucketLocation`.
  - Deny public ACLs/policies (`s3:PutObjectAcl` not needed).
- Credentials: set in `.env` for `filesystems.s3` (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`). Ensure `filesystems.default` remains `s3`/`local` per environment, but media uses `s3` disk.
- CloudFront (optional): if using a CDN domain, ensure `Storage::disk('s3')->url()` is configured with `AWS_URL`.
- Size limits: presign enforces `content-length-range` using `file_size` from the request; adjust validation in `PresignMediaRequest` if needed.

### AI Integration

Use `OpenAIService` for all AI operations, which automatically:
- Tracks token usage via `TokenUsageLog`
- Respects team/user token limits
- Handles API errors

### Bulk Actions UI Pattern

The application uses a consistent bottom action bar pattern for list views that support bulk operations (delete, update status, etc.). This pattern provides a unified user experience across Content Pieces, Posts, and Media Gallery pages.

#### When to Use

Use this pattern for any list/table view where users need to perform operations on multiple items:
- Multi-select with checkboxes
- Bulk delete, update, or modify operations
- Actions that apply to 1+ selected items

#### Architecture Components

**1. Frontend State Management**

```vue
<script setup lang="ts">
// Selection state
const selectedIds = ref<number[]>([]);

// Computed select-all state
const allSelected = computed(() => {
    return props.items.data.length > 0 &&
           selectedIds.value.length === props.items.data.length;
});

// Toggle individual item
const toggleSelection = (id: number, checked: boolean) => {
    if (checked) {
        if (!selectedIds.value.includes(id)) {
            selectedIds.value.push(id);
        }
    } else {
        selectedIds.value = selectedIds.value.filter((selectedId) => selectedId !== id);
    }
};

// Toggle all items on current page
const toggleAll = (checked: boolean) => {
    if (checked) {
        selectedIds.value = props.items.data.map((item) => item.id);
    } else {
        selectedIds.value = [];
    }
};

// Clear selection when data changes (e.g., pagination)
watch(() => props.items.data, () => {
    selectedIds.value = [];
});
</script>
```

**2. Template Structure**

```vue
<template>
    <!-- Table/Grid with checkboxes -->
    <table>
        <thead>
            <tr>
                <!-- Select-all checkbox in header -->
                <th class="px-4 py-3">
                    <Checkbox
                        :model-value="allSelected"
                        @update:model-value="toggleAll($event === true)"
                    />
                </th>
                <!-- Other columns... -->
            </tr>
        </thead>
        <tbody>
            <tr v-for="item in items.data" :key="item.id">
                <!-- Individual checkbox in row -->
                <td class="px-4 py-4">
                    <Checkbox
                        :model-value="selectedIds.includes(item.id)"
                        @update:model-value="(checked: boolean) => toggleSelection(item.id, checked)"
                    />
                </td>
                <!-- Other columns... -->
            </tr>
        </tbody>
    </table>

    <!-- Bottom action bar component -->
    <BulkActionsComponent
        :count="selectedIds.length"
        @action="handleAction"
        @clear="selectedIds = []"
    />
</template>
```

**3. Bottom Action Bar Component**

Create a dedicated component (e.g., `ContentPieceBulkActions.vue`, `PostBulkActions.vue`):

```vue
<script setup lang="ts">
defineProps<{
    count: number;
}>();

const emit = defineEmits<{
    (event: 'delete'): void;
    (event: 'update-status'): void;
    (event: 'clear'): void;
    // Add other actions as needed
}>();
</script>

<template>
    <div
        v-if="count > 0"
        class="fixed inset-x-0 bottom-0 z-50 border-t border-gray-200 bg-white/90 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90"
    >
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-3">
            <div class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ count }} item{{ count === 1 ? '' : 's' }} selected
            </div>
            <div class="flex items-center gap-3">
                <Button variant="outline" size="sm" @click="emit('update-status')">
                    Update Status
                </Button>
                <Button variant="destructive" size="sm" @click="emit('delete')">
                    Delete Selected
                </Button>
                <Button variant="ghost" size="sm" @click="emit('clear')">
                    Clear
                </Button>
            </div>
        </div>
    </div>
</template>
```

**4. Action Handlers**

```vue
<script setup lang="ts">
import axios from 'axios';
import { toast } from 'vue-sonner';

const handleBulkDelete = async () => {
    if (selectedIds.value.length === 0) return;

    if (!confirm(`Are you sure you want to delete ${selectedIds.value.length} item(s)?`)) {
        return;
    }

    try {
        await axios.post('/endpoint/bulk-delete', {
            item_ids: selectedIds.value
        });

        toast.success('Items deleted successfully');
        selectedIds.value = [];

        // Refresh data (use Inertia router or reload)
        router.reload({ only: ['items'] });
    } catch (error) {
        console.error(error);
        toast.error('Unable to delete items right now.');
    }
};
</script>
```

**5. Backend Routes**

```php
// routes/web.php
Route::post('items/bulk-delete', [ItemController::class, 'bulkDelete'])
    ->name('items.bulk-delete');
Route::post('items/bulk-update-status', [ItemController::class, 'bulkUpdateStatus'])
    ->name('items.bulk-update-status');
```

**6. Backend Controller Methods**

```php
// app/Http/Controllers/ItemController.php
public function bulkDelete(BulkActionRequest $request): JsonResponse
{
    $validated = $request->validated();
    $teamId = auth()->user()->current_team_id;

    // Get only items belonging to the current team
    $items = Item::where('team_id', $teamId)
        ->whereIn('id', $validated['item_ids'])
        ->get();

    // Authorize each item (respects policies)
    foreach ($items as $item) {
        $this->authorize('delete', $item);
        $item->delete();
    }

    return response()->json([
        'message' => 'Items deleted successfully.'
    ]);
}

public function bulkUpdateStatus(BulkActionRequest $request): JsonResponse
{
    $validated = $request->validated();
    $teamId = auth()->user()->current_team_id;

    $items = Item::where('team_id', $teamId)
        ->whereIn('id', $validated['item_ids'])
        ->get();

    foreach ($items as $item) {
        $this->authorize('update', $item);
        $item->update(['status' => $validated['status']]);
    }

    return response()->json([
        'message' => 'Status updated successfully.'
    ]);
}
```

**7. Form Request Validation**

```php
// app/Http/Requests/BulkActionRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        $rules = [
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'exists:items,id'],
        ];

        // Add conditional validation based on route
        if ($this->routeIs('items.bulk-update-status')) {
            $rules['status'] = ['required', 'in:DRAFT,FINAL,NOT_STARTED'];
        }

        return $rules;
    }
}
```

**8. Test Coverage**

```php
// tests/Feature/ItemBulkActionsTest.php
it('allows bulk delete of multiple items', function () {
    [$user, $team] = createUserWithTeam();

    $item1 = Item::factory()->for($team)->create();
    $item2 = Item::factory()->for($team)->create();
    $item3 = Item::factory()->for($team)->create();

    $response = $this->actingAs($user)->postJson('/items/bulk-delete', [
        'item_ids' => [$item1->id, $item2->id],
    ]);

    $response->assertOk();
    $response->assertJson(['message' => 'Items deleted successfully.']);

    expect(Item::find($item1->id))->toBeNull();
    expect(Item::find($item2->id))->toBeNull();
    expect(Item::find($item3->id))->not->toBeNull();
});

it('prevents deleting items from another team', function () {
    [$user, $team] = createUserWithTeam();
    [$otherUser, $otherTeam] = createUserWithTeam();

    $item = Item::factory()->for($team)->create();
    $otherItem = Item::factory()->for($otherTeam)->create();

    $response = $this->actingAs($user)->postJson('/items/bulk-delete', [
        'item_ids' => [$item->id, $otherItem->id],
    ]);

    // Should succeed for team item but skip other team's item
    $response->assertOk();
    expect(Item::find($item->id))->toBeNull();
    expect(Item::find($otherItem->id))->not->toBeNull();
});

it('validates bulk delete requires array of IDs', function () {
    [$user, $team] = createUserWithTeam();

    $response = $this->actingAs($user)->postJson('/items/bulk-delete', [
        'item_ids' => 'invalid',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['item_ids']);
});
```

#### Key Design Decisions

**Bottom vs. Top Action Bar**
- **Bottom** (preferred): Stays visible while scrolling, better use of screen real estate
- Only appears when items are selected (conditional `v-if="count > 0"`)

**Selection State**
- Checkboxes use Reka UI `Checkbox` component with `modelValue`/`update:modelValue`
- Select-all only affects current page (not all pages in pagination)
- Selection clears on page change to avoid confusion

**Authorization**
- Backend always validates team ownership via `where('team_id', $teamId)`
- Each item authorized individually via policies before operation
- Silently skips items user can't modify (doesn't fail entire operation)

**User Feedback**
- Toast notifications for success/error (via `vue-sonner`)
- Confirmation dialogs for destructive actions (delete)
- Count display shows number of selected items

**Styling**
- Fixed positioning: `fixed inset-x-0 bottom-0 z-50`
- Backdrop blur: `bg-white/90 backdrop-blur` for frosted glass effect
- Dark mode support: `dark:border-gray-800 dark:bg-gray-900/90`
- Max width container: `mx-auto max-w-6xl` matches main content width

#### Examples in Codebase

- **Content Pieces**: `resources/js/pages/ContentPieces/Index.vue` + `resources/js/components/ContentPiece/ContentPieceBulkActions.vue`
- **Posts**: `resources/js/pages/Posts/Index.vue` + `resources/js/components/Post/PostBulkActions.vue`
- **Media Gallery**: `resources/js/pages/Media/Index.vue` (inline implementation)

### Testing

- Use Pest (not PHPUnit directly)
- Feature tests for HTTP endpoints
- Factories for all models
- Run: `php artisan test --filter=TestName`
- **Test coverage includes**: CRUD operations, authorization (policies), validation, team isolation, middleware behavior
- **Test patterns**: Guest access denied, team scoping enforced, authorization forbidden for other teams, validation errors returned

## Configuration Files

- `config/fortify.php` - Authentication features
- `bootstrap/app.php` - Middleware, exceptions, routing
- `bootstrap/providers.php` - Service provider registration
- `vite.config.js` - Frontend build configuration

## Getting Started Checklist

1. Review domain model relationships above
2. Check existing controllers for patterns (`app/Http/Controllers/`)
3. Look at sibling files when creating new ones
4. Use `search-docs` tool for Laravel/Inertia documentation
5. Follow shadcn/ui patterns in `components/ui/`
6. Reference `docs/ui-components.md` for form field patterns
7. Run `vendor/bin/pint --dirty` before committing PHP
8. Run `npm run build` after frontend changes
9. Write tests for new functionality

## Related Documentation

- [UI Components Architecture](./ui-components.md) - Reka UI patterns and common pitfalls
- [CLAUDE.md](../CLAUDE.md) - Code conventions and tool usage
- [AGENTS.md](../AGENTS.md) - Agent-specific instructions
