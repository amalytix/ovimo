# Webhook Source Type Design

**Date:** 2025-01-19
**Feature:** Add WEBHOOK source type for generic webhook integrations

## Overview

Add a new `WEBHOOK` source type that allows users to integrate with external webhook services (like n8n). The application will make scheduled POST requests to the webhook URL with keywords, and the webhook will return a list of posts to be ingested.

## User Flow

1. User creates a new source and selects "Webhook" as the type
2. User provides:
   - Webhook URL (must accept POST requests)
   - Keywords (one per line in textarea)
3. On scheduled checks, the system:
   - Converts keywords to JSON array
   - POSTs to webhook URL with `{"keywords": ["keyword1", "keyword2"]}`
   - Webhook returns `{"data": [{"title": "...", "url": "...", ...}]}`
   - Creates Post records with metadata stored as JSON

## Database Schema Changes

### Migration 1: Add metadata to posts table

```php
Schema::table('posts', function (Blueprint $table) {
    $table->json('metadata')->nullable()->after('relevancy_score');
});
```

**Purpose:** Store additional fields from webhook responses (subreddit, created, id, date, selftext, etc.)

### Migration 2: Add failure tracking to sources table

```php
Schema::table('sources', function (Blueprint $table) {
    $table->unsignedTinyInteger('consecutive_failures')->default(0)->after('last_checked_at');
    $table->timestamp('failed_at')->nullable()->after('consecutive_failures');
});
```

**Purpose:** Track failed webhook calls and auto-disable sources after 3 consecutive failures

### Migration 3: Add WEBHOOK to source type enum

Add `WEBHOOK` as a valid source type enum value.

### Existing Fields Reused

- `url` - Stores the webhook URL
- `keywords` - Stores comma-separated keywords (converted to JSON array for POST body)

## Backend Implementation

### SourceParser Service

**File:** `app/Services/SourceParser.php`

Add new case to `parse()` method:

```php
public function parse(Source $source): array
{
    return match ($source->type) {
        'RSS' => $this->parseRss($source),
        'XML_SITEMAP' => $this->parseXmlSitemap($source),
        'WEBSITE' => $this->parseWebsite($source),
        'WEBHOOK' => $this->parseWebhook($source), // New!
    };
}
```

**New parseWebhook() method:**

1. Convert comma-separated keywords to JSON array: `explode(',', $source->keywords)`
2. Trim whitespace from each keyword
3. Make HTTP POST request to `$source->url` with:
   - Timeout: 60 seconds
   - Body: `{"keywords": ["keyword1", "keyword2"]}`
   - Headers: `Content-Type: application/json`
4. Parse JSON response and extract `data` array
5. Transform each item to: `{title, uri, metadata}`
   - `title` from `title` field
   - `uri` from `url` field
   - `metadata` contains all other fields as JSON
6. Return array of transformed items

**Error Handling:**

- HTTP errors/timeouts: Throw exception (triggers retry)
- Invalid JSON: Log warning, return empty array
- Missing `data` field: Log warning, return empty array

### MonitorSource Job Updates

**File:** `app/Jobs/MonitorSource.php`

**Retry Configuration:**

```php
public int $tries = 3; // Total attempts
public int $backoff = 300; // 5 minutes between retries
```

**Updated Flow:**

1. Check if source is active
2. Call `SourceParser::parse($source)` (may throw exception)
3. **On success:**
   - Reset failure tracking: `consecutive_failures = 0`, `failed_at = null`
   - Process posts (with metadata)
   - Update `last_checked_at` and `next_check_at`
4. **On exception:**
   - Increment `consecutive_failures`
   - Set `failed_at` timestamp
   - If `consecutive_failures >= 3`: Set `is_active = false`
   - Re-throw exception to trigger Laravel retry
   - Log/notify user about disabled source

**Post Creation with Metadata:**

```php
$post = $source->posts()->firstOrCreate(
    ['uri' => $item['uri']],
    [
        'external_title' => $item['title'] ?? null,
        'metadata' => $item['metadata'] ?? null, // New!
        'status' => 'NOT_RELEVANT',
        'found_at' => now(),
    ]
);
```

## Frontend Implementation

### Source Form Updates

**File:** `resources/js/pages/Sources/Index.vue` (or equivalent)

**WEBHOOK Type Fields:**

1. **Webhook URL** (text input)
   - Label: "Webhook URL"
   - Placeholder: "https://your-webhook-service.com/webhook/..."
   - Help text: "Must accept POST requests"
   - Validation: Required, valid URL, max 2048 chars
   - Binds to: `url` field

2. **Keywords** (textarea)
   - Label: "Keywords"
   - Placeholder: "Enter keywords (one per line)\namvisor\namalytix\ninsightleap"
   - Description: "These keywords will be sent to your webhook endpoint"
   - Binds to: `keywords` field
   - Processing: Split by newlines, join with commas before save

3. **Expected Response Format** (help/info section)

   Display collapsible help text:

   ```
   Your webhook must return a JSON response with a "data" array.
   Each item must have at least "title" and "url" fields.

   Example response:
   {
     "data": [
       {
         "title": "Example Post Title",
         "url": "https://example.com/post/123",
         "created": 1741793734,
         "id": "abc123"
       }
     ]
   }
   ```

**Conditional Fields:**

Hide these fields for WEBHOOK type:
- `css_selector_title` (WEBSITE only)
- `css_selector_link` (WEBSITE only)

**Source Type Options:**

```
- RSS Feed
- XML Sitemap
- Website
- Webhook
```

**Status Indicators:**

For WEBHOOK sources, display:
- `consecutive_failures` count if > 0
- "Disabled due to failures" badge if `is_active = false` and `failed_at` is recent

## Testing Strategy

### Unit Tests

**File:** `tests/Unit/Services/SourceParserTest.php`

1. Test successful webhook parsing
2. Test HTTP timeout handling
3. Test HTTP error handling
4. Test invalid JSON response
5. Test missing `data` field
6. Test keyword conversion (comma-separated → JSON array)

### Feature Tests

**File:** `tests/Feature/WebhookSourceTest.php`

1. Test complete webhook source monitoring flow
2. Test failure and retry logic
3. Test auto-disable after 3 failures
4. Test post metadata storage
5. Test consecutive_failures reset on success

### Manual Testing Checklist

- [ ] Create WEBHOOK source through UI
- [ ] Verify keywords textarea works (one per line)
- [ ] Trigger manual check
- [ ] Verify POST request sent correctly
- [ ] Test with valid webhook response
- [ ] Test with timeout
- [ ] Verify failure counter increments
- [ ] Verify auto-disable after 3 failures

## Example Webhook Integration

**Request to webhook:**

```json
POST https://n8n.amalytix.net/webhook/78d4a901-9be4-496c-828f-bd38b6e6ad70
Content-Type: application/json

{
  "keywords": [
    "amvisor",
    "amalytix",
    "insightleap"
  ]
}
```

**Expected response:**

```json
{
  "data": [
    {
      "subreddit": "r/rubyonremote",
      "title": "AMVisor GmbH is hiring remotely a PHP-Developer (m/w/d).",
      "url": "https://rubyonremote.com/jobs/66436-php-developer",
      "created": 1741793734,
      "id": "1j9mta4",
      "date": "12.3.2025",
      "selftext": "AMVisor GmbH is hiring..."
    }
  ]
}
```

**Database result:**

Post record created with:
- `external_title`: "AMVisor GmbH is hiring remotely a PHP-Developer (m/w/d)."
- `uri`: "https://rubyonremote.com/jobs/66436-php-developer"
- `metadata`: `{"subreddit": "r/rubyonremote", "created": 1741793734, "id": "1j9mta4", "date": "12.3.2025", "selftext": "AMVisor GmbH is hiring..."}`

## Implementation Notes

- Use existing `keywords` field (comma-separated) rather than new column
- Store all extra webhook fields in `metadata` JSON column
- Reuse existing retry/failure patterns from SendWebhookNotification job
- 60-second timeout allows webhook services time to process requests
- Auto-disable prevents infinite retry loops for broken webhooks
