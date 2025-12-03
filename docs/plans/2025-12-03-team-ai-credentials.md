# Plan: Move AI API Keys from .env to Team Settings

## Overview

Move OpenAI and Google Gemini API keys and settings from environment variables to per-team configuration in the Team Settings "AI" tab.

## Design Decisions (User-Confirmed)

- **No fallback**: Teams must configure their own API keys. Features disabled without keys.
- **Key display**: Show masked key like `****...proj-abc` when configured.
- **Warnings**: Show inline where feature is used (e.g., image generation panel).

---

## Implementation Steps

### Step 1: Database Migration

**Create**: `database/migrations/2025_12_03_120000_add_ai_credentials_to_teams_table.php`

```php
Schema::table('teams', function (Blueprint $table) {
    $table->text('openai_api_key')->nullable()->after('relevancy_prompt');
    $table->string('openai_model', 50)->default('gpt-5-mini')->after('openai_api_key');
    $table->text('gemini_api_key')->nullable()->after('openai_model');
    $table->string('gemini_image_model', 100)->default('gemini-3-pro-image-preview')->after('gemini_api_key');
    $table->string('gemini_image_size', 10)->default('1K')->after('gemini_image_model');
});
```

- Add a `down()` that drops all five columns.
- Confirm `teams` has `relevancy_prompt`; if not, adjust the `after()` placement (e.g., after `negative_keywords`).

### Step 2: Model Changes

- Add new fields to `$fillable` and encrypted casts in `casts()`.
- Update `Database\Factories\TeamFactory` (and any seeds) to set sensible defaults so factory-built teams still pass validation.

Helper methods (with short-key guard):
```php
public function hasOpenAIConfigured(): bool { return filled($this->openai_api_key); }
public function hasGeminiConfigured(): bool { return filled($this->gemini_api_key); }
public function getMaskedOpenAIKey(): ?string {
    if (!$this->openai_api_key) return null;
    $key = $this->openai_api_key;
    return '****...' . substr($key, -min(8, strlen($key)));
}
public function getMaskedGeminiKey(): ?string {
    if (!$this->gemini_api_key) return null;
    $key = $this->gemini_api_key;
    return '****...' . substr($key, -min(8, strlen($key)));
}
```

### Step 3: Create Exception Class

**Create**: `app/Exceptions/AINotConfiguredException.php`

```php
<?php

namespace App\Exceptions;

use Exception;

class AINotConfiguredException extends Exception
{
    public function __construct(
        string $message,
        public string $provider,
        public string $settingsUrl = '/team-settings?tab=ai'
    ) {
        parent::__construct($message);
    }
}
```

### Step 4: Service Construction Strategy (align with current DI)

Current services are container-resolved and read keys/models from `config()`. To keep DI and testing intact:

1) Add `configureForTeam(string $apiKey, ?string $model = null, ?string $imageSize = null)` on services to set credentials per team; call it before use, or
2) Introduce `AIServiceFactory` that resolves services from the container and configures them instead of using `new`.

Pick one approach and apply consistently across controllers/jobs; avoid `new OpenAIService`/`new GeminiService` to preserve bindings.

Default behaviors to preserve unless intentionally changed:
- `summarizePost` uses `gpt-5-mini`.
- `analyzeWebpage` uses `gpt-5.1`.
- `generateContent` uses `config('openai.model', 'gpt-5.1')` unless a team override is provided.

### Step 5: Adapt OpenAIService

- Add the `configureForTeam` helper that rebuilds the client with team API key and stores the chosen model.
- `generateContent` should use the configured model if set; otherwise fallback to existing config default.

### Step 6: Adapt GeminiService

- Add `configureForTeam` to set API key, image model, and image size per team.
- Confirm allowed `imageSize` values in the Gemini API. If only `1K/2K/4K` are valid, enforce those; otherwise align validation and defaults to the supported list.

### Step 7: Update Jobs

- `SummarizePost`, `GenerateContentPiece`, `GenerateAIImage`: inject the factory (or configure services) and short-circuit gracefully when provider not configured. Update signatures accordingly. Keep token-limit checks and existing retry/backoff logic.

### Step 8: Update Controllers

- `ImageGenerationController` and `SourceController`: use factory/configured services; return 422 JSON with `settings_url` when provider missing. Consider a small helper/trait to keep the response shape consistent.

### Step 9: Update SettingsController

- Include in `index()` payload:
  - `openai_api_key_masked`, `openai_model`, `gemini_api_key_masked`, `gemini_image_model`, `gemini_image_size`, `has_openai`, `has_gemini`.
- Do **not** send raw keys to the client.

### Step 10: Update Form Request Validation

Add rules:
```php
'openai_api_key' => ['nullable', 'string', 'max:500'],
'openai_model' => ['nullable', 'string', 'max:50'],
'gemini_api_key' => ['nullable', 'string', 'max:500'],
'gemini_image_model' => ['nullable', 'string', 'max:100'],
'gemini_image_size' => ['nullable', 'string', 'in:1K,2K,4K'], // adjust once API list is confirmed
```

Allow empty strings to clear keys (convert to null before save).

### Step 11: Update Frontend - Settings Page

- Extend the `Team` interface with masked keys, models, sizes, and booleans.
- Update `settingsForm` to include AI fields; keep key inputs empty by default to “keep existing”. Add a “Clear key” control to submit null.
- AI tab additions:
  - Status badges showing configured/ not configured (using masked key).
  - Inputs: OpenAI key (password), model; Gemini key, image model, image size select.
  - Helpful links to provider key pages.
- Keep existing copy; add inline validation messages and disable Save while processing.

### Step 12: Add Inline Warnings

- Add reusable warning block for AI-dependent UI (image generation, content generation, source analysis). Link to `/team-settings?tab=ai`.

### Step 13: Tests

- Feature tests for updating AI settings (save, keep existing, clear key, validation errors).
- Job tests to ensure skipping when unconfigured and success when configured.
- Controller tests for 422 responses when provider missing.
- If a Gemini test command exists, optionally add `--team` for targeted credentials.

---

## Files to Create

| File | Purpose |
|------|---------|
| `database/migrations/2025_12_03_120000_add_ai_credentials_to_teams_table.php` | Add columns |
| `app/Exceptions/AINotConfiguredException.php` | Custom exception |
| *(Optional)* `app/Services/AIServiceFactory.php` | Configure per-team services without `new` |

## Files to Modify

| File | Changes |
|------|---------|
| `app/Models/Team.php` | Add fields, casts, helpers |
| `database/factories/TeamFactory.php` | Add defaults for new fields |
| `app/Services/OpenAIService.php` | Add `configureForTeam`, honor per-team model |
| `app/Services/GeminiService.php` | Add `configureForTeam`, honor per-team image settings |
| `app/Jobs/SummarizePost.php` | Use configured service/factory, skip when missing |
| `app/Jobs/GenerateContentPiece.php` | Same pattern |
| `app/Jobs/GenerateAIImage.php` | Same pattern |
| `app/Http/Controllers/ImageGenerationController.php` | Return 422 + settings URL when missing |
| `app/Http/Controllers/SourceController.php` | Same for analyzeWebpage |
| `app/Http/Controllers/SettingsController.php` | Include masked AI fields in payload |
| `app/Http/Requests/UpdateTeamSettingsRequest.php` | Add AI validation rules |
| `resources/js/Pages/settings/Index.vue` | Add AI key/model UI, status badges, clear-key flow |

## Testing Checklist

1. Migration runs successfully (up/down).
2. API keys stored encrypted; clearing removes value.
3. Masked keys display correctly; raw keys never sent to client.
4. New API key can be saved and preserved across reload.
5. Services use team-scoped credentials and existing defaults are preserved.
6. Jobs skip gracefully when not configured; log reason.
7. Controllers return 422 with `settings_url` when provider missing.
8. Frontend shows warnings and status badges when keys missing.
9. Image generation flow works with team Gemini key.
10. Post summarization/content generation works with team OpenAI key.
