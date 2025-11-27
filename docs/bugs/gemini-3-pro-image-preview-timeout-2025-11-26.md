# Gemini 3 Pro Image Preview Timeout Issue

**Date:** 2025-11-26
**Status:** Investigating
**Severity:** Medium
**Affected Model:** `gemini-3-pro-image-preview`

## Summary

The `gemini-3-pro-image-preview` model frequently times out when processing complex image generation prompts, while simpler prompts occasionally succeed. The `gemini-2.5-flash-image` model handles all prompts reliably. New information: the model works in at least one other app, so our request formatting or options may be at fault.

## Architecture

### Components Involved

1. **Frontend:** `resources/js/components/ContentPiece/ImagesTab.vue`
   - User selects image prompt template and aspect ratio
   - Triggers text prompt generation (OpenAI)
   - Triggers image generation (Gemini)
   - Polls for job completion status

2. **Controller:** `app/Http/Controllers/ImageGenerationController.php`
   - `store()` - Creates ImageGeneration record and generates text prompt
   - `generate()` - Dispatches `GenerateAIImage` job to queue

3. **Queue Job:** `app/Jobs/GenerateAIImage.php`
   - Calls `GeminiService::generateImage()`
   - Uploads result to S3
   - Tags media as "AI generated"
   - Updates ImageGeneration status

4. **Service:** `app/Services/GeminiService.php`
   - Builds model-specific request bodies
   - Makes HTTP request to Gemini API
   - Handles response parsing

### Request Format Differences

**gemini-2.5-flash-image (works reliably):**
```json
{
    "contents": [{
        "parts": [{"text": "prompt"}]
    }],
    "generationConfig": {
        "responseModalities": ["TEXT", "IMAGE"]
    }
}
```

**gemini-3-pro-image-preview (unreliable):**
```json
{
    "contents": [{
        "role": "user",
        "parts": [{"text": "prompt"}]
    }]
}
```

Note: The gemini-3 format was discovered through external code example. Google's documentation suggested using `generationConfig` with `imageConfig`, but that format caused immediate hangs with 0 bytes received.

## Test Results

### Test Command
```bash
php artisan gemini:test-image --model=MODEL --prompt="PROMPT"
```

### Simple Prompt Test
Prompt: "A serene mountain landscape at sunset"

| Model | Result | Time |
|-------|--------|------|
| gemini-2.5-flash-image | Success | 12.36s |
| gemini-3-pro-image-preview | Success | 19.08s |

### Complex Prompt Test
Prompt: "Close-up three-quarter view of a thoughtful shopper holding a smartphone, dozens of miniature product thumbnails (coffee machines, boxes) swirling in a spiral above the screen while a small luminous holographic assistant labeled Rufus — a friendly, semi‑transparent geometric avatar — reaches out and funnels the swirl into a single highlighted product..."

| Model | Result | Time |
|-------|--------|------|
| gemini-2.5-flash-image | Success | 7.09s |
| gemini-3-pro-image-preview | Timeout | 120s+ (0 bytes received) |

### Corporate/Technical Prompt Test
Prompt: "Create a professional, visually striking hero image for a blog post about artificial intelligence and machine learning in modern business applications..."

| Model | Result | Time |
|-------|--------|------|
| gemini-2.5-flash-image | Not tested | - |
| gemini-3-pro-image-preview | Timeout | 120s+ (0 bytes received) |

## Observations

1. **0 bytes received:** The timeout always shows "0 bytes received", indicating the API never starts responding, not that it's slow to generate.

2. **Prompt complexity matters:** Simple prompts sometimes work with gemini-3, but complex prompts consistently fail.

3. **No error response:** The API doesn't return an error - it simply never responds.

4. **Model is in preview:** The model name includes "preview" which may indicate instability.

5. **gemini-2.5-flash-image is faster:** Even when gemini-3 works, it's slower (19s vs 7-12s for gemini-2.5).

## Configuration

**Current production setting (recommended):**
```env
GEMINI_IMAGE_MODEL=gemini-2.5-flash-image
GEMINI_REQUEST_TIMEOUT=180
```

**To test gemini-3 (not recommended for production):**
```env
GEMINI_IMAGE_MODEL=gemini-3-pro-image-preview
GEMINI_REQUEST_TIMEOUT=180
```

## Files Modified During Investigation

- `app/Services/GeminiService.php` - Added model-specific request format handling
- `app/Jobs/GenerateAIImage.php` - Added logging, fixed `uploaded_by` constraint
- `app/Console/Commands/TestGeminiImageGeneration.php` - Created for testing
- `config/gemini.php` - Added configurable model and timeout

## Current Hypothesis (app-side)

- Our requests to Gemini 3 omit `generationConfig.imageConfig` (aspect ratio, image size) and rely on default modal configuration. The preview model may require explicit `responseModalities: ["IMAGE"]` plus `imageConfig`.
- Timeouts occur with 0 bytes returned, matching behavior when an invalid payload is accepted but never processed.

## Next Steps

1. Update Gemini request builder (service + test command) to include `generationConfig.responseModalities` and `generationConfig.imageConfig` (aspect ratio + size) for Gemini 3.
2. Retest complex prompts after deploying the change.
3. Keep production on `gemini-2.5-flash-image` until Gemini 3 proves stable under our requests.

## Recommendations

1. **Use `gemini-2.5-flash-image` for production** - It's faster and more reliable
2. **Monitor Google's release notes** - gemini-3 may stabilize over time
3. **Keep the test command** - `php artisan gemini:test-image` for future testing
4. **Consider fallback logic** - Could implement automatic fallback to gemini-2.5 if gemini-3 times out

## Related Links

- Google Gemini 3 Documentation: https://ai.google.dev/gemini-api/docs/gemini-3
- Gemini Models List: https://ai.google.dev/gemini-api/docs/models
