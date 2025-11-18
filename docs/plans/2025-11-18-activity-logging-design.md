# Activity Logging System Design

**Date:** 2025-11-18
**Status:** Approved for Implementation

## Overview

Add comprehensive activity logging for user actions, domain events, and system errors. Users view their team's last 7 days of activity through a web interface with filtering capabilities.

## Requirements

### Events to Log

**User Events:**
- User logged in
- User changed 2FA settings (enabled/disabled)
- Password reset
- Password changed

**Domain Events:**
- New post found
- Source created
- Source updated
- Source deleted

**Error/Warning Events:**
- Source monitoring failed
- Content piece generation failed
- OpenAI API request failed
- Webhook delivery failed
- Token limit exceeded

### Log Data Structure

Each log event captures:
- `event_type` (e.g., "user.login", "source.created", "post.found")
- `user_id` (nullable - system events may have no user)
- `team_id` (required - all logs are team-scoped)
- `level` ("info", "warning", "error")
- `description` (optional human-readable text)
- `source_id` (nullable - if applicable)
- `post_id` (nullable - if applicable)
- `ip_address` (nullable - for login events)
- `user_agent` (nullable - for login events)
- `metadata` (nullable JSON - flexible additional context)
- `created_at` (timestamp)

### User Interface

- New "Logs" menu item (last position in navigation)
- Table view with columns: Date/Time, Event Type, User, Description, Level (badge), Actions
- "View Details" button opens modal showing full log details including formatted metadata JSON
- Filters: Event type dropdown, Date range picker (from/to)
- Default view: Last 7 days, sorted by created_at descending
- Team-scoped: Users only see logs for their current team
- Pagination: 50 logs per page

### Data Retention

- Automated cleanup job deletes logs older than 30 days
- Runs daily at midnight
- Keeps database size manageable

## Architecture

### Database Schema

**Table:** `activity_logs`

```php
Schema::create('activity_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->string('event_type');
    $table->string('level')->default('info');
    $table->text('description')->nullable();

    // Contextual references
    $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();

    // Enhanced context
    $table->string('ip_address')->nullable();
    $table->text('user_agent')->nullable();
    $table->json('metadata')->nullable();

    $table->timestamp('created_at');

    // Performance indexes
    $table->index(['team_id', 'created_at']);
    $table->index(['team_id', 'event_type', 'created_at']);
});
```

**Model Relationships:**
- Belongs to Team (required)
- Belongs to User (nullable)
- Belongs to Source (nullable)
- Belongs to Post (nullable)

### Events & Listeners Pattern

**Architecture:** Single unified listener handles all activity logging.

**Event Registration:**
```php
Event::listen([
    // User events (5)
    UserLoggedIn::class,
    TwoFactorEnabled::class,
    TwoFactorDisabled::class,
    PasswordChanged::class,
    PasswordReset::class,

    // Domain events (4)
    PostFound::class,
    SourceCreated::class,
    SourceUpdated::class,
    SourceDeleted::class,

    // Error/warning events (5)
    SourceMonitoringFailed::class,
    ContentPieceGenerationFailed::class,
    OpenAIRequestFailed::class,
    WebhookDeliveryFailed::class,
    TokenLimitExceeded::class,
], LogActivityToDatabase::class);
```

**Listener:** `LogActivityToDatabase` (queued)
- Maps events to ActivityLog data
- Handles all event types in one place
- Queued for async processing (doesn't slow down requests)

### Event Dispatch Points

**User Authentication Events:**
- Hook into Laravel Fortify events in `FortifyServiceProvider`
- `UserLoggedIn`: After successful login
- `PasswordChanged`, `PasswordReset`: Fortify password events
- `TwoFactorEnabled`, `TwoFactorDisabled`: Fortify 2FA events

**Domain Events:**
- `SourceCreated`, `SourceUpdated`, `SourceDeleted`: Dispatch in `SourceController` after operations
- `PostFound`: Dispatch in `MonitorSource` job after creating new post

**Error Events:**
- `SourceMonitoringFailed`: Dispatch in `MonitorSource` job catch blocks
- `ContentPieceGenerationFailed`: Dispatch in `ContentPieceController` or generation job
- `OpenAIRequestFailed`: Dispatch in `OpenAIService` catch blocks
- `WebhookDeliveryFailed`: Dispatch in `SendWebhookNotification` job catch blocks
- `TokenLimitExceeded`: Dispatch in `EnsureTokenLimitNotExceeded` middleware

### Frontend Implementation

**Controller:** `ActivityLogController`
```php
public function index(Request $request)
{
    $team = auth()->user()->currentTeam;

    $logs = $team->activityLogs()
        ->with(['user', 'source', 'post'])
        ->when($request->event_type, fn($q, $type) => $q->where('event_type', $type))
        ->whereBetween('created_at', [
            $request->from ?? now()->subDays(7),
            $request->to ?? now()
        ])
        ->latest('created_at')
        ->paginate(50);

    return Inertia::render('ActivityLogs/Index', [
        'logs' => $logs,
        'filters' => $request->only(['event_type', 'from', 'to']),
        'eventTypes' => ActivityLog::EVENT_TYPES,
    ]);
}
```

**Vue Page:** `resources/js/Pages/ActivityLogs/Index.vue`
- Uses existing UI components (Table, Select, DatePicker, Dialog/Modal, Badge)
- Filter bar with event type dropdown and date range pickers
- Table with clickable "View Details" buttons
- Modal displays full log data with formatted JSON metadata

**Route:** `Route::get('/logs', [ActivityLogController::class, 'index'])->name('logs.index');`

**Navigation:** Add "Logs" item to `AppSidebarLayout.vue` (last position)

### Automated Cleanup

**Job:** `PruneOldActivityLogs` (queued)
```php
public function handle(): void
{
    $cutoffDate = now()->subDays(30);
    ActivityLog::where('created_at', '<', $cutoffDate)->delete();
}
```

**Schedule:** `Schedule::job(new PruneOldActivityLogs())->dailyAt('00:00');`

## Event Details

### User Events

**1. UserLoggedIn**
- Level: `info`
- Description: "User logged in"
- Context: IP address, user agent
- Dispatch: `FortifyServiceProvider` login callback

**2. TwoFactorEnabled**
- Level: `info`
- Description: "Two-factor authentication enabled"
- Dispatch: Fortify 2FA enabled event

**3. TwoFactorDisabled**
- Level: `info`
- Description: "Two-factor authentication disabled"
- Dispatch: Fortify 2FA disabled event

**4. PasswordChanged**
- Level: `info`
- Description: "Password changed"
- Dispatch: Fortify password update event

**5. PasswordReset**
- Level: `info`
- Description: "Password reset via email"
- Dispatch: Fortify password reset event

### Domain Events

**6. PostFound**
- Level: `info`
- Description: "New post found: '{post_title}'"
- Context: source_id, post_id
- Metadata: `['post_title' => ..., 'post_url' => ...]`
- Dispatch: `MonitorSource` job after creating post

**7. SourceCreated**
- Level: `info`
- Description: "Source created: '{source_name}'"
- Context: source_id
- Metadata: `['source_name' => ..., 'source_type' => ...]`
- Dispatch: `SourceController@store`

**8. SourceUpdated**
- Level: `info`
- Description: "Source updated: '{source_name}'"
- Context: source_id
- Metadata: `['source_name' => ...]`
- Dispatch: `SourceController@update`

**9. SourceDeleted**
- Level: `info`
- Description: "Source deleted: '{source_name}'"
- Context: Store source_id before deletion
- Metadata: `['source_name' => ...]`
- Dispatch: `SourceController@destroy`

### Error/Warning Events

**10. SourceMonitoringFailed**
- Level: `error`
- Description: "Failed to monitor source '{source_name}': {error_message}"
- Context: source_id
- Metadata: `['exception' => ..., 'source_url' => ...]`
- Dispatch: `MonitorSource` job catch block

**11. ContentPieceGenerationFailed**
- Level: `error`
- Description: "Failed to generate content piece '{name}': {error_message}"
- Metadata: `['exception' => ..., 'prompt_id' => ..., 'post_count' => ...]`
- Dispatch: Content generation catch blocks

**12. OpenAIRequestFailed**
- Level: `error`
- Description: "OpenAI API request failed for operation '{operation}': {error_message}"
- Metadata: `['operation' => 'summarize|generate|analyze', 'model' => ..., 'status_code' => ...]`
- Dispatch: `OpenAIService` catch blocks

**13. WebhookDeliveryFailed**
- Level: `warning`
- Description: "Failed to deliver webhook '{webhook_name}': {error_message}"
- Metadata: `['webhook_url' => ..., 'event' => ..., 'status_code' => ...]`
- Dispatch: `SendWebhookNotification` job catch blocks

**14. TokenLimitExceeded**
- Level: `warning`
- Description: "Monthly token limit exceeded for team"
- Metadata: `['limit' => ..., 'current_usage' => ...]`
- Dispatch: `EnsureTokenLimitNotExceeded` middleware

## Implementation Checklist

### Backend
- [ ] Create migration for `activity_logs` table
- [ ] Create `ActivityLog` model with relationships and casts
- [ ] Create 14 event classes (User: 5, Domain: 4, Error: 5)
- [ ] Create `LogActivityToDatabase` listener (queued)
- [ ] Register events and listener in `AppServiceProvider` or `EventServiceProvider`
- [ ] Hook into Fortify events in `FortifyServiceProvider` (5 user events)
- [ ] Dispatch domain events in `SourceController` (3 events)
- [ ] Dispatch `PostFound` in `MonitorSource` job
- [ ] Dispatch error events in appropriate catch blocks
- [ ] Create `PruneOldActivityLogs` job
- [ ] Schedule cleanup job in `routes/console.php` or `bootstrap/app.php`
- [ ] Create `ActivityLogController` with index method
- [ ] Add route to `routes/web.php`
- [ ] Create ActivityLog policy (team scoping)

### Frontend
- [ ] Create `resources/js/Pages/ActivityLogs/Index.vue`
- [ ] Build filter bar (event type dropdown, date range pickers)
- [ ] Build logs table with pagination
- [ ] Create modal/dialog for viewing full log details
- [ ] Format metadata JSON in modal
- [ ] Add "Logs" navigation item to `AppSidebarLayout.vue` (last position)
- [ ] Generate Wayfinder routes (`php artisan wayfinder:generate`)

### Testing
- [ ] Feature tests for `ActivityLogController` (filtering, pagination, team scoping)
- [ ] Test event dispatching (verify events create logs)
- [ ] Test `LogActivityToDatabase` listener
- [ ] Test `PruneOldActivityLogs` job
- [ ] Test authorization policy
- [ ] Browser test for logs page UI and filtering

### Code Quality
- [ ] Run `vendor/bin/pint --dirty`
- [ ] Run `npm run build`
- [ ] Run feature tests with filter

## Design Rationale

**Why Events & Listeners?**
- Decoupled: Logging doesn't pollute feature code
- Maintainable: Single listener handles all logging logic
- Extensible: Easy to add new events without touching existing code
- Async: Queued listener doesn't slow down user requests

**Why Enhanced Context (IP, User Agent, Metadata)?**
- Security auditing for login events
- Error debugging with full exception details
- Flexible metadata JSON prevents schema changes later

**Why 30-Day Retention?**
- Balances useful history with database size
- Matches common compliance requirements
- Automated cleanup prevents manual maintenance

**Why Team-Scoped?**
- Multi-tenant isolation (users only see their team's activity)
- Consistent with existing application architecture
- Simple authorization (team membership check)

**Why Modal for Details?**
- Cleaner UI than expandable rows
- Better for displaying formatted JSON
- Easier to implement with existing UI components
