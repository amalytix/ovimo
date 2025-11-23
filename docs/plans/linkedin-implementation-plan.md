# LinkedIn Integration Implementation Plan

## Overview
Add LinkedIn OAuth authentication and content publishing to Ovimo, allowing teams to connect multiple LinkedIn profiles and publish content pieces (with images/PDFs) either immediately or scheduled for future delivery.

---

## Phase 1: Database Schema & Models

### 1.1 Create Social Integrations Table
**File:** `database/migrations/YYYY_MM_DD_create_social_integrations_table.php`

```php
Schema::create('social_integrations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // User who connected
    $table->enum('platform', ['linkedin']); // Extensible for instagram, twitter
    $table->string('platform_user_id');
    $table->string('platform_username')->nullable();
    $table->text('access_token'); // Encrypted
    $table->text('refresh_token')->nullable(); // Encrypted
    $table->timestamp('token_expires_at')->nullable();
    $table->json('scopes')->nullable();
    $table->json('profile_data')->nullable(); // name, picture, vanityName
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->index(['team_id', 'platform', 'is_active']);
    $table->unique(['team_id', 'platform', 'platform_user_id']);
});
```

### 1.2 Update Content Pieces Table
**File:** `database/migrations/YYYY_MM_DD_add_publishing_fields_to_content_pieces_table.php`

```php
Schema::table('content_pieces', function (Blueprint $table) {
    $table->json('publish_to_platforms')->nullable(); // ['linkedin' => integration_id]
    $table->json('published_platforms')->nullable(); // Results: ['linkedin' => ['post_id' => '...', 'published_at' => '...']]
    $table->timestamp('scheduled_publish_at')->nullable();
    $table->enum('publish_status', ['not_published', 'scheduled', 'publishing', 'published', 'failed'])->default('not_published');
});
```

### 1.3 Create Models
**Files:**
- `app/Models/SocialIntegration.php` - with encrypted access/refresh tokens
- Update `app/Models/ContentPiece.php` - add publishing fields to `$fillable` and `$casts`

### 1.4 Create Factories & Seeders
- `database/factories/SocialIntegrationFactory.php`
- `database/seeders/SocialIntegrationSeeder.php` (optional, for testing)

---

## Phase 2: OAuth Authentication Flow

### 2.1 Install Dependencies
- Laravel HTTP client (already included)
- No additional packages needed (pure Laravel implementation)

### 2.2 Configuration
**File:** `config/services.php`

```php
'linkedin' => [
    'client_id' => env('LINKEDIN_CLIENT_ID'),
    'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
    'redirect_uri' => env('LINKEDIN_REDIRECT_URI', env('APP_URL').'/integrations/linkedin/callback-member'),
    'scopes' => [
        'openid',
        'profile',
        'w_member_social',
        'r_basicprofile',
    ],
],
```

### 2.3 Create LinkedIn OAuth Service
**File:** `app/Services/LinkedIn/LinkedInOAuthService.php`

**Methods:**
- `generateAuthUrl()` - Create authorization URL with state & PKCE
- `exchangeCodeForToken($code, $codeVerifier)` - Exchange auth code for tokens
- `refreshAccessToken($refreshToken)` - Refresh expired tokens
- `getUserProfile($accessToken)` - Fetch user profile data
- `validateScopes($returnedScopes, $requiredScopes)` - Ensure required scopes granted

### 2.4 Create LinkedIn Controller
**File:** `app/Http/Controllers/Integrations/LinkedInController.php`

**Methods:**
- `redirect()` - Initiate OAuth, store state in session, redirect to LinkedIn
- `callback(Request $request)` - Handle callback, exchange code, store integration
- `disconnect(SocialIntegration $integration)` - Deactivate integration
- `index()` - List team's LinkedIn integrations

### 2.5 Create Routes
**File:** `routes/web.php`

```php
Route::middleware(['auth', 'verified', 'team.valid'])->group(function () {
    Route::prefix('integrations/linkedin')->name('integrations.linkedin.')->group(function () {
        Route::get('/', [LinkedInController::class, 'index'])->name('index');
        Route::get('/connect', [LinkedInController::class, 'redirect'])->name('connect');
        Route::get('/callback', [LinkedInController::class, 'callback'])->name('callback');
        Route::delete('/{integration}', [LinkedInController::class, 'disconnect'])->name('disconnect');
    });
});
```

### 2.6 Create Policy
**File:** `app/Policies/SocialIntegrationPolicy.php`

**Methods:**
- `viewAny($user)` - Can view team's integrations
- `view($user, $integration)` - Check team ownership
- `create($user)` - Can create integration
- `delete($user, $integration)` - Can disconnect (team ownership)

---

## Phase 3: LinkedIn Publishing Service

### 3.1 Create LinkedIn Publishing Service
**File:** `app/Services/LinkedIn/LinkedInPublishingService.php`

**Methods:**
- `publishPost(SocialIntegration $integration, ContentPiece $contentPiece)` - Main publishing method
- `uploadMedia(SocialIntegration $integration, Media $media)` - Upload image/PDF to LinkedIn
- `createPost(SocialIntegration $integration, $personId, $message, $mediaUrns)` - Create LinkedIn post
- `buildPostPayload($personId, $message, $mediaUrns)` - Format API payload
- `refreshTokenIfNeeded(SocialIntegration $integration)` - Auto-refresh expired tokens

### 3.2 Create Publishing Job
**File:** `app/Jobs/PublishContentToLinkedIn.php`

```php
class PublishContentToLinkedIn implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 300; // 5 minutes
    public int $timeout = 600; // 10 minutes

    public function __construct(
        public ContentPiece $contentPiece,
        public SocialIntegration $integration
    ) {}

    public function handle(LinkedInPublishingService $service)
    {
        // Update status to 'publishing'
        // Refresh token if needed
        // Upload media
        // Create post
        // Update content piece with results
        // Dispatch success event
    }

    public function failed(\Throwable $exception)
    {
        // Update status to 'failed'
        // Dispatch failure event
        // Log error
    }
}
```

### 3.3 Create Scheduled Job for Publishing
**File:** `app/Jobs/ProcessScheduledPublishing.php`

- Runs every minute via scheduler
- Finds content pieces where `scheduled_publish_at <= now()` and `publish_status = 'scheduled'`
- Dispatches `PublishContentToLinkedIn` jobs

### 3.4 Register Scheduled Job
**File:** `routes/console.php`

```php
Schedule::job(new ProcessScheduledPublishing)->everyMinute();
```

---

## Phase 4: Controller Updates

### 4.1 Update ContentPieceController
**File:** `app/Http/Controllers/ContentPieceController.php`

**New methods:**
- `publish(ContentPiece $contentPiece, Request $request)` - Publish immediately or schedule
  - Validates integration_id, schedule_at
  - Dispatches job immediately or updates scheduled_publish_at
  - Returns success response

### 4.2 Create Form Requests
**Files:**
- `app/Http/Requests/ContentPiece/PublishContentPieceRequest.php`
  - Validates: `integration_id` (exists, belongs to team), `schedule_at` (optional, future datetime)

### 4.3 Add Routes
**File:** `routes/web.php`

```php
Route::post('content-pieces/{contentPiece}/publish', [ContentPieceController::class, 'publish'])
    ->name('content-pieces.publish');
Route::get('content-pieces/{contentPiece}/publishing-status', [ContentPieceController::class, 'publishingStatus'])
    ->name('content-pieces.publishing-status'); // For polling
```

---

## Phase 5: Events & Activity Logging

### 5.1 Create Events
**Files:**
- `app/Events/LinkedInIntegrationConnected.php`
- `app/Events/LinkedInIntegrationDisconnected.php`
- `app/Events/ContentPublishedToLinkedIn.php`
- `app/Events/LinkedInPublishingFailed.php`

### 5.2 Create Listeners
**File:** `app/Listeners/LogActivityToDatabase.php` (update existing)

Add cases for new events:
- `integration.linkedin_connected`
- `integration.linkedin_disconnected`
- `content.published_to_linkedin`
- `content.linkedin_publish_failed`

---

## Phase 6: Frontend Implementation

### 6.1 Update Team Settings Page
**File:** `resources/js/Pages/settings/Index.vue`

**Add new section:**
- "Integrations" tab/section
- List connected LinkedIn profiles (avatar, name, username)
- "Connect LinkedIn" button
- "Disconnect" button for each integration

### 6.2 Create LinkedIn Connect Component
**File:** `resources/js/components/Integrations/LinkedInConnectButton.vue`

- Initiates OAuth flow on click
- Opens popup or redirects to LinkedIn
- Handles loading state

### 6.3 Update Content Piece Edit Page
**File:** `resources/js/Pages/ContentPieces/Edit.vue`

**Add "Publishing" tab:**
- Platform selection (LinkedIn dropdown - shows team's integrations)
- Schedule options:
  - "Publish now" button
  - "Schedule for later" with date/time picker
- Publishing status display (not_published, scheduled, publishing, published, failed)
- Published post link (if published)

### 6.4 Create Publishing Components
**Files:**
- `resources/js/components/Publishing/PublishingScheduler.vue` - Date/time picker
- `resources/js/components/Publishing/PublishingStatus.vue` - Status badge with icon
- `resources/js/components/Publishing/LinkedInIntegrationSelector.vue` - Dropdown of team integrations

### 6.5 Add Wayfinder Support
Update controllers to work with Wayfinder:
- Import actions in Vue: `import { publish } from '@/actions/App/Http/Controllers/ContentPieceController'`
- Use in forms: `<Form v-bind="publish.form(contentPiece.id)">`

---

## Phase 7: Testing

### 7.1 Feature Tests
**Files:**
- `tests/Feature/LinkedInIntegrationTest.php`
  - OAuth redirect generates valid URL with state
  - OAuth callback stores integration correctly
  - OAuth callback validates state parameter
  - Prevents accessing other team's integrations
  - Can disconnect integration (marks inactive)

- `tests/Feature/PublishContentToLinkedInTest.php`
  - Publishes content piece immediately
  - Schedules content piece for future
  - Uploads media before publishing
  - Refreshes expired tokens automatically
  - Handles LinkedIn API errors gracefully
  - Retries on temporary failures
  - Updates content piece with publish results
  - Dispatches success/failure events

### 7.2 Unit Tests
**Files:**
- `tests/Unit/LinkedInOAuthServiceTest.php`
  - Generates correct auth URL
  - Exchanges code for tokens
  - Refreshes access token
  - Validates scopes correctly

- `tests/Unit/LinkedInPublishingServiceTest.php`
  - Builds correct post payload
  - Formats media correctly
  - Handles single image
  - Handles PDF attachment

### 7.3 Browser Tests (Pest v4)
**File:** `tests/Browser/LinkedInIntegrationTest.php`
- Complete OAuth flow (mocked)
- Publishing workflow from UI
- Scheduling workflow
- Error state displays

---

## Phase 8: Security & Error Handling

### 8.1 Security Measures
- Encrypt `access_token` and `refresh_token` in database (Laravel encryption)
- Validate OAuth `state` parameter to prevent CSRF
- Use PKCE flow for added OAuth security
- Team-scoped authorization policies on all endpoints
- Rate limit OAuth endpoints

### 8.2 Error Handling
- Graceful LinkedIn API error handling
- User-friendly error messages
- Automatic token refresh on 401 responses
- Retry logic in queue jobs
- Activity logging for all errors

### 8.3 Validation
- Validate LinkedIn API responses
- Validate media types (images, PDFs only)
- Validate media size limits
- Validate scheduled publish dates (future only)
- Validate integration ownership

---

## Phase 9: Documentation & Polish

### 9.1 Update Architecture Documentation
**File:** `docs/architecture.md`

Add sections:
- Social Integrations domain model
- LinkedIn OAuth flow diagram
- Publishing workflow
- Scheduling mechanism

### 9.2 Create Migration Guide
**File:** `docs/linkedin-integration.md`

- How to get LinkedIn API credentials
- How to configure OAuth redirect URLs
- Environment variables needed
- User guide for connecting LinkedIn
- Troubleshooting guide

### 9.3 Code Formatting
- Run `vendor/bin/pint` on all PHP files
- Run `npm run build` to compile frontend assets

---

## File Structure Summary

```
app/
├── Events/
│   ├── LinkedInIntegrationConnected.php (new)
│   ├── LinkedInIntegrationDisconnected.php (new)
│   ├── ContentPublishedToLinkedIn.php (new)
│   └── LinkedInPublishingFailed.php (new)
├── Http/
│   ├── Controllers/
│   │   ├── ContentPieceController.php (update - add publish method)
│   │   └── Integrations/
│   │       └── LinkedInController.php (new)
│   └── Requests/
│       └── ContentPiece/
│           └── PublishContentPieceRequest.php (new)
├── Jobs/
│   ├── PublishContentToLinkedIn.php (new)
│   └── ProcessScheduledPublishing.php (new)
├── Models/
│   ├── ContentPiece.php (update - add publishing fields)
│   └── SocialIntegration.php (new)
├── Policies/
│   └── SocialIntegrationPolicy.php (new)
└── Services/
    └── LinkedIn/
        ├── LinkedInOAuthService.php (new)
        └── LinkedInPublishingService.php (new)

database/
├── factories/
│   └── SocialIntegrationFactory.php (new)
├── migrations/
│   ├── YYYY_MM_DD_create_social_integrations_table.php (new)
│   └── YYYY_MM_DD_add_publishing_fields_to_content_pieces_table.php (new)
└── seeders/
    └── SocialIntegrationSeeder.php (new)

resources/js/
├── components/
│   ├── Integrations/
│   │   └── LinkedInConnectButton.vue (new)
│   └── Publishing/
│       ├── LinkedInIntegrationSelector.vue (new)
│       ├── PublishingScheduler.vue (new)
│       └── PublishingStatus.vue (new)
└── Pages/
    ├── ContentPieces/
    │   └── Edit.vue (update - add Publishing tab)
    └── settings/
        └── Index.vue (update - add Integrations section)

tests/
├── Feature/
│   ├── LinkedInIntegrationTest.php (new)
│   └── PublishContentToLinkedInTest.php (new)
├── Unit/
│   ├── LinkedInOAuthServiceTest.php (new)
│   └── LinkedInPublishingServiceTest.php (new)
└── Browser/
    └── LinkedInIntegrationTest.php (new)

config/
└── services.php (update - add linkedin config)

routes/
├── web.php (update - add integration routes)
└── console.php (update - add scheduler)

docs/
├── architecture.md (update)
└── linkedin-integration.md (new)
```

---

## Implementation Order

1. **Phase 1** - Database schema (migrations, models, factories)
2. **Phase 2** - OAuth flow (service, controller, routes, policy)
3. **Phase 5** - Events & logging (before testing OAuth)
4. **Phase 7.1** - OAuth integration tests
5. **Phase 3** - Publishing service & jobs
6. **Phase 4** - Controller updates for publishing
7. **Phase 7.1** - Publishing tests
8. **Phase 6** - Frontend implementation
9. **Phase 7.3** - Browser tests
10. **Phase 8** - Security audit & error handling polish
11. **Phase 9** - Documentation & final polish

---

## Environment Variables Required

```env
LINKEDIN_CLIENT_ID=your_client_id
LINKEDIN_CLIENT_SECRET=your_client_secret
LINKEDIN_CLIENT_REDIRECT_URL=https://ovimo.ai/integrations/linkedin/callback-member
```

## Tokens for testing

I created these tokens which can be used for testing:

Access token:

AQVY_B0MrZUO22mJ6aZ45JeLDbbVC4HGG6YEhCfL7FKgjhXomN-dTk8VI716c9sfJzfLXI-JhWfWqIFzKT42nqZcy_TBIT8sdIgTDZ59rS3S5__R7g1wYPGJN5t1NFn3qwZ4tUl86YSxvOxxBZj1Em4fYerLX5iDZfUB4MjHPFnCqEbC378suFK7F_uKX8n81ftj63gKN5psgnJQCNNxQomY3Y6C6TsTJMkEDc5Qyk2JzfXolCDn9Pf-Brk1O_XS9uur1sRfjC5RbbI4Q9oAdBOHxUI17rxqyqBHnt-BL3D_3zv5tE2VgmG6DrB_LL-1w2rCjAa_X5MDhKyI5t8cE6unQ1JvwA

Refresh token:
AQVgZV3EObMCMVJJsCn12aWlSnp2c7rwhvhyleCZGr9pH1RsCb3pqKvWSlRp-HOxXsSlM5mFTH7oFpCvu6Y0gTZ7ghGjf5rheXb1WxaIs18x58OCgv2_SVh0ci0n3KpOvMEl9S8In5erwmsNObjc_dUNmhto9UoOfUlGyB852q6OSN3PJ66YFEHcyDWyJJmWPppYcag5AOnDD5Co2NdBdAprw-qmm-URHudb4qK--8L0A3Majn6nG5HSA0q_AKbJyQRg3LVOnFrkSHhYZAG4EjKumvLTm9Rw7rCfJfZszfSN1tIf-aCXxACgBhh9R0m_5TY_bqlAFH2BXKSXATneS8RPMOJCdw

Permissions: 

email, openid, profile, r_1st_connections_size, r_ads, r_ads_reporting, r_basicprofile, r_organization_admin, r_organization_social, rw_ads, rw_organization_admin, w_member_social, w_organization_social

But do not use it for testing post creation as this is a real LinkedIn member account.

---

## Success Criteria

✅ Users can connect multiple LinkedIn profiles per team via OAuth 2.0
✅ Users can publish content pieces with images or PDFs to LinkedIn immediately
✅ Users can schedule content pieces for future publishing
✅ Published posts appear on LinkedIn with correct formatting
✅ System handles token refresh automatically
✅ All actions are team-scoped and authorized properly
✅ Activity logs track all integration events
✅ Comprehensive test coverage (unit, feature, browser)
✅ System is extensible for future platforms (Instagram, Twitter)

---

## Notes

- **No carousel support** - Only single image or PDF per post (as requested)
- **Multi-profile support** - Teams can connect unlimited LinkedIn accounts
- **Both immediate and scheduled** publishing supported
- **Team settings location** - Integrations appear in team settings page
- **Extensible design** - `SocialIntegration` model supports future platforms via `platform` enum
- **Pure Laravel** - No external OAuth packages needed
- **Follows Ovimo patterns** - Team scoping, policies, jobs, events, activity logging
