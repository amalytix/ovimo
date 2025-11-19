# Content Publishing Calendar - Implementation Plan (Revised)

## Guardrails
- Keep multi-tenant boundaries: always scope queries to `auth()->user()->current_team_id` and reuse existing policies.
- Reuse existing UI stack (AppLayout + shadcn-like `ui` components in `resources/js/components/ui`). Avoid adding new dependencies; lean on native `Date`/`Intl` or `@vueuse/core` helpers already installed instead of pulling `dayjs`.
- Match current naming: prompts expose `internal_name`, not `name`; ContentPiece output keys must align with `ContentPieceController@index` expectations.
- Stay inside existing structure (`resources/js/pages/ContentPieces/*.vue`, no new base folders). Keep Tailwind v4 utility style consistent with current views.
- Sorting default should remain predictable for users: unscheduled first, then nearest publish date, then newest created.

## Phase 1: Database & Model
1) **Migration**: add `published_at` (timezone-aware) with index after `generation_status`.
```bash
php artisan make:migration add_published_at_to_content_pieces_table --table=content_pieces --no-interaction
```
```php
Schema::table('content_pieces', function (Blueprint $table) {
    $table->timestampTz('published_at')->nullable()->after('generation_status');
    $table->index('published_at');
});
```
Include the reverse drop (index + column) in `down()`.

2) **Model** (`app/Models/ContentPiece.php`):
- Add `published_at` to `$fillable`.
- Add a `casts()` method (preferred convention for Laravel 12) returning at least `published_at => 'immutable_datetime'` and `generation_error_occurred_at => 'immutable_datetime'`.
- Consider a local scope `scopeOrderedForPublishing()` encapsulating "unscheduled first, then by publish date asc, then newest created" to reuse in controllers and tests.

3) **Factory** (`database/factories/ContentPieceFactory.php`):
- Default: `'published_at' => fake()->optional(0.3)->dateTimeBetween('now', '+30 days')`.
- Add two states: `scheduled()` (sets a future datetime) and `unscheduled()` (null).

## Phase 2: Validation & Controller
1) **Form Requests** (`StoreContentPieceRequest`, `UpdateContentPieceRequest`):
- Add rule `published_at` => `['nullable', 'date', 'after_or_equal:now']`.
- Keep custom error for past dates (`published_at.after_or_equal`).

2) **`ContentPieceController@index`**:
- Authorize via policy if needed (`$this->authorize('viewAny', ContentPiece::class);`).
- Apply existing filters plus optional `view` (`list|week|month`) and optional date window (`start_date`, `end_date`) for calendar queries.
- Use the shared `ordered` scope (or inline ordering) to sort: unscheduled first, then publish date asc, then `created_at` desc.
- When serializing, include `published_at` (ISO string) and `published_at_human` via `diffForHumans()`, and keep `prompt_name` sourced from `internal_name`.

3) **`ContentPieceController@calendar`** (new JSON endpoint):
- Validate `view` in `['week','month']` and `date` as `date`.
- Calculate range with `CarbonImmutable` using start-of-week Monday (`startOfWeek(CarbonInterface::MONDAY)`), and month boundaries for month view.
- Query team-scoped scheduled pieces within range, ordered with the same ordering, and return grouped by date: `{ events: Record<Y-m-d, [...]>, start_date, end_date }` with ISO datetimes for the frontend.

4) **Routes** (`routes/web.php`):
- Add `Route::get('content-pieces/calendar', [ContentPieceController::class, 'calendar'])->name('content-pieces.calendar');` inside the auth group.
- After changing routes, run Wayfinder generation (`php artisan wayfinder:generate --no-interaction`) so TS route helpers stay in sync.

## Phase 3: Forms (Create & Edit)
- In `resources/js/pages/ContentPieces/Create.vue` and `Edit.vue`, add a `Publish date & time` field using existing `Label`, `Input`, and `Button` components. Use `type="datetime-local"` with `:min` bound to `useNow()` (`@vueuse/core`) formatted as `YYYY-MM-DDTHH:mm`.
- Normalize incoming value from the server to `datetime-local` format via a helper (`formatDateTimeLocal(isoString)`) and send the raw `datetime-local` string back; Laravel will cast it to UTC.
- Add a `Clear`/`Remove schedule` button that nulls the field.
- Surface validation errors next to the field consistent with existing form error display.

## Phase 4: List View Enhancements
- Keep the current table structure but wrap it in `overflow-x-auto` for small screens.
- Add a `Publish Date` column showing formatted date + relative time; show `Unscheduled` in muted text when null.
- Expose sorting for publish date via query params (`sort_by=published_at`, `sort_direction=asc|desc`) and wire a toggle button in the column header. Default sort stays `published_at asc` with nulls first as defined in the controller scope.

## Phase 5: Calendar Views (Inertia)
- Add lightweight month/week views in `resources/js/pages/ContentPieces/CalendarMonth.vue` and `CalendarWeek.vue` (same folder, no new base directories). Use the reference layouts in `docs/plans/calendar-month-view.vue` and `calendar-week-view.vue` but swap `dayjs` for native `Date` helpers or `@vueuse/core` utilities (`useDateFormat`, `useNow`).
- In `Index.vue`, add a simple toggle (List | Month | Week) that switches between the existing list table and the new calendar components. Preserve filters/search when switching by passing query params through `router.get` and `preserveState`/`preserveScroll`.
- Calendar components should:
  - Fetch data from `content-pieces.calendar` with `view` and `date` params.
  - Show a loading state (use existing spinner/placeholder pattern if available; otherwise a simple text state).
  - Present events as links to edit pages using Inertia `Link` + route helper (`route('content-pieces.edit', id)`).
  - Handle mobile gracefully (stacked days, tap to open day details modal or list).

## Phase 6: Tests
- Feature tests only (no Browser suite exists yet). Add `tests/Feature/ContentPieceCalendarTest.php` using the existing `createUserWithTeam()` helper and Pest datasets for validation edge cases.
- Cover:
  - Can create/update with a valid future `published_at` and leave it null.
  - Past datetime rejected with proper error message.
  - Index ordering: unscheduled first, then nearest publish date.
  - Calendar endpoint returns only scheduled items within range and respects team scoping.
  - List view Inertia payload includes `published_at` fields.
- Use factories with new `scheduled()`/`unscheduled()` states for clarity.

## Phase 7: Delivery Checklist
- Run migration: `php artisan migrate`.
- Generate Wayfinder routes after adding the calendar endpoint.
- Run targeted tests: `php artisan test --filter=ContentPieceCalendar` (and the existing ContentPiece suite if touched).
- Format PHP: `vendor/bin/pint --dirty`.
- Build frontend assets locally: `npm run build`.
- Verify UI manually for desktop + mobile widths and dark mode for list + calendar views.
