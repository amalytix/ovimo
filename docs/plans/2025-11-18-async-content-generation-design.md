# Async Content Piece Generation Design

**Date:** 2025-11-18
**Status:** Implemented

## Overview

Converted synchronous content piece generation to an asynchronous job-based system with polling UI updates. This eliminates blocking HTTP requests and provides better user experience through non-blocking generation with real-time status updates.

## Problem Statement

The original implementation generated content synchronously, causing several issues:
- **Blocking User Experience:** Users waited 30-300 seconds during generation (synchronous HTTP request)
- **Timeout Risk:** PHP execution time extended to 300s - risky for large content
- **No Progress Feedback:** Binary "Generating..." state with no intermediate updates
- **Poor Scalability:** Each request tied up a web server process for minutes

## Solution

Implemented asynchronous job-based generation with 3-second polling for status updates.

### Key Design Decisions

1. **Simple Status States:** QUEUED → PROCESSING → COMPLETED/FAILED
   - Rejected detailed progress tracking (fetching posts → building context → calling AI) as unnecessarily complex
   - Simple states provide clear feedback without implementation overhead

2. **3-Second Polling Interval**
   - Balanced between responsiveness and server load
   - Rejected 2-second (too chatty) and adaptive polling (overcomplicated)

3. **Stay on Create Page**
   - User sees status inline where they initiated the action
   - Rejected redirect-to-edit and redirect-to-list approaches

4. **Auto-Retry with Exponential Backoff**
   - 3 retries with 2min, 4min, 8min delays
   - Categorized errors: timeout vs rate limit vs general errors
   - User sees specific error messages after all retries exhausted

5. **Status Storage in content_pieces Table**
   - Added `generation_status`, `generation_error`, `generation_error_occurred_at` columns
   - Rejected separate job tracking table as over-engineered for this use case

6. **Concurrency Protection**
   - DB transaction with `lockForUpdate()` prevents multiple jobs for same content piece
   - User must wait for current generation to finish before re-triggering

## Architecture

### Database Schema

Added three columns to `content_pieces` table:

```php
$table->string('generation_status')->default('NOT_STARTED');
$table->text('generation_error')->nullable();
$table->timestamp('generation_error_occurred_at')->nullable();
```

**Status Values:**
- `NOT_STARTED` - Content piece created, no generation triggered
- `QUEUED` - Job dispatched, waiting to process
- `PROCESSING` - Job actively running
- `COMPLETED` - Successfully generated
- `FAILED` - Generation failed after all retries

**Separation from Editorial Status:**
- `generation_status` tracks job execution
- `status` (NOT_STARTED/DRAFT/FINAL) tracks editorial workflow
- A piece can be `status=DRAFT` and `generation_status=FAILED` (has old content, latest run failed)

### Backend Components

**GenerateContentPiece Job** (`app/Jobs/GenerateContentPiece.php`):
- Implements `ShouldQueue` with 3 retries, 120s backoff, 300s timeout
- Updates status to PROCESSING on start
- Sets `status=DRAFT` and `generation_status=COMPLETED` on success
- Categorizes errors in `failed()` method (timeout/rate limit/general)
- Dispatches `ContentPieceGenerated` and `ContentPieceGenerationFailed` events

**Controller Changes** (`ContentPieceController.php`):
- `store()` and `generate()` methods dispatch job instead of synchronous generation
- `generateContentForPiece()` uses DB transaction with `lockForUpdate()` for concurrency protection
- New `status()` method returns JSON for polling endpoint
- Returns session flash with polling metadata
- `create()` method extracts first selected post's title (`external_title ?? internal_title ?? uri`) and passes as `initialTitle` for pre-populating content piece name
- Fetches `external_title` and `internal_title` fields for posts to display proper titles in selection list

**Route:**
```php
Route::get('content-pieces/{content_piece}/status', [ContentPieceController::class, 'status'])
    ->name('content-pieces.status');
```

**Events:**
- `ContentPieceGenerated` (new) - dispatched on successful generation
- `ContentPieceGenerationFailed` (updated signature) - dispatched after all retries fail

**Activity Logging:**
- Logs `content_piece.generated` (info level) on success
- Logs `content_piece.generation_failed` (error level) on failure
- Both events handled by `LogActivityToDatabase` listener

### Frontend Components

**Create.vue Updates:**
- Added polling state management (isPolling, generationStatus, generatedContent, generationError, showSuccessMessage)
- Watches for `polling` prop from session flash
- Polls `/content-pieces/{id}/status` every 3 seconds
- Two-column layout matching Edit page:
  - Left column: Settings form (internal name, prompt, channel, language, briefing, source posts)
  - Right column: Generated content textarea (empty until content is generated, persists after completion)
- Top-right header area matches Edit page:
  - Polling/success status indicator (spinner + text or "✓ Generated")
  - Editorial status badge (Not Started/Draft/Final with color coding)
  - Status dropdown selector to set initial status
- Smooth transitions with `mode="out-in"` - previous status fades out before next fades in
- Generated content persists in textarea after completion
- Buttons disabled during polling
- Pre-populates internal_name with first selected post's title (`external_title || internal_title || uri`) when coming from Posts view
- Post selection list displays titles using same priority as Posts Index view
- Cleanup on component unmount (clears polling and success timeout)

**Edit.vue Updates:**
- Added identical polling implementation as Create.vue
- Status indicator positioned left of the Draft status badge (top-right area)
- Shows either polling status (spinner + "Queued..." / "Generating...") OR success status ("✓ Generated")
- No separate success message box - status appears inline with editorial status badge
- Smooth transitions with `mode="out-in"` - previous status fades out completely before next status fades in (300ms transitions)
- Polls status endpoint after clicking "Generate Content"
- Updates full_text field when generation completes
- Success status auto-hides after 5 seconds with smooth fade-out animation
- Cleanup on component unmount (clears polling and success timeout)

**HandleInertiaRequests Middleware:**
- Added `polling` to shared Inertia props
- Enables session flash polling metadata to reach Vue components

### Error Handling

**Timeout Handling:**
- OpenAI calls have 300s timeout
- Job has matching 300s timeout
- Retries with exponential backoff (2min, 4min, 8min)
- After 3 failures → FAILED with "OpenAI request timed out" message

**Rate Limit Handling:**
- Detects rate limit errors from OpenAI
- Categorizes as "OpenAI rate limit exceeded. Please try again later."
- User can manually retry later

**Network/API Errors:**
- Any uncaught exception → automatic retry
- After all retries → generic error message stored
- Activity log captures full exception details

**Edge Cases:**
- User leaves page: Job continues, status persisted in database
- Queue worker down: Job remains QUEUED until worker processes
- Multiple tabs: Each polls independently (harmless read-only GETs)
- Concurrent generation attempts: DB lock prevents race conditions

## Implementation Summary

### Files Created
- Migration: `add_async_generation_columns_to_content_pieces_table`
- Job: `app/Jobs/GenerateContentPiece.php`
- Event: `app/Events/ContentPieceGenerated.php`
- Tests: `tests/Feature/ContentPieceAsyncGenerationTest.php`

### Files Modified
- `app/Http/Controllers/ContentPieceController.php` (dispatch logic + status endpoint)
- `app/Models/ContentPiece.php` (added async fields to $fillable array)
- `resources/js/Pages/ContentPieces/Create.vue` (polling implementation)
- `resources/js/Pages/ContentPieces/Edit.vue` (polling implementation)
- `app/Http/Middleware/HandleInertiaRequests.php` (share polling flash data)
- `app/Listeners/LogActivityToDatabase.php` (event handlers)
- `app/Providers/AppServiceProvider.php` (event registration)
- `app/Events/ContentPieceGenerationFailed.php` (updated signature)
- `routes/web.php` (status route)

## Testing

Created 5 feature tests covering:
1. Job dispatch when creating with generate flag
2. Concurrent generation prevention
3. Status polling endpoint
4. Content returned when completed
5. Error returned when failed

All tests passing.

## Benefits

- **Non-blocking UX:** User can continue working, no 1-3 minute wait
- **Real-time Feedback:** 3-second polling shows live progress (Queued → Processing → Complete)
- **Auto-hiding Success Messages:** Success notifications fade out after 5 seconds to reduce clutter
- **Automatic Retry:** Transient failures handled gracefully (3 retries with exponential backoff)
- **Clear Error Messages:** Timeout vs rate limit vs API error categorization
- **Full Audit Trail:** Activity logs track all operations
- **Scalable Foundation:** Ready for batch generation feature

## Issues Found and Fixed

### Bug: generation_status Not Updating (Fixed 2025-11-18)

**Problem:** After implementation, `generation_status` remained "NOT_STARTED" even though content was being generated successfully.

**Root Cause:** The new async fields (`generation_status`, `generation_error`, `generation_error_occurred_at`) were added to the database migration but NOT added to the `$fillable` array in the ContentPiece model. Laravel's mass assignment protection silently ignored these fields when the job tried to update them.

**Symptoms:**

- Jobs executed successfully
- `full_text` and `status` updated correctly
- `generation_status` stayed at "NOT_STARTED"
- No errors in logs (silent failure)

**Fix:** Added the three async fields to `$fillable` array in `app/Models/ContentPiece.php`.

**Lesson:** Always update model's `$fillable` array when adding new columns to the database.

## Future Enhancements

- Batch generation: Select multiple posts, generate multiple content pieces
- Progress indicators: Show detailed stages if needed (e.g., "Fetching posts", "Building context", "Calling AI")
- WebSocket support: Replace polling with real-time updates for even better UX
