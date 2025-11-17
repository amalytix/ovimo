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
└── TokenUsageLogs (AI usage tracking)
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

## Backend Architecture

### Directory Structure

```
app/
├── Actions/Fortify/          # Authentication actions (user creation, password reset)
├── Console/Commands/         # Artisan commands (ScheduleSourceMonitoring)
├── Http/
│   ├── Controllers/          # Inertia page controllers
│   │   └── Settings/         # User settings controllers
│   ├── Middleware/           # Custom middleware (team validation, token limits)
│   └── Requests/             # Form validation requests
├── Jobs/                     # Queue jobs
│   ├── MonitorSource.php     # Fetches new posts from sources
│   ├── SummarizePost.php     # AI summarization
│   └── SendWebhookNotification.php
├── Models/                   # Eloquent models
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
- `SettingsController` - Team settings management
- `Settings/*` - User profile, password, 2FA settings

### Background Jobs

| Job | Trigger | Purpose |
|-----|---------|---------|
| `MonitorSource` | Scheduled via `ScheduleSourceMonitoring` | Parses source URL, creates new Posts |
| `SummarizePost` | When auto_summarize enabled | Uses AI to analyze and summarize post |
| `SendWebhookNotification` | On events (NEW_POSTS, etc.) | Delivers webhook payloads |

### Services Layer

- **OpenAIService**: Handles AI API interactions, token tracking
- **SourceParser**: Parses RSS/XML feeds and website content
- **WebContentExtractor**: Extracts readable content from web pages

### Key Business Logic

1. **Source Monitoring Flow**:
   ```
   ScheduleSourceMonitoring (cron)
   → Dispatches MonitorSource for due sources
   → Parses feed/website
   → Creates Post records
   → Optionally triggers SummarizePost
   → Sends webhook notifications
   ```

2. **Content Generation Flow**:
   ```
   User selects Posts + Prompt
   → ContentPieceController generates via OpenAIService
   → Logs token usage
   → Returns generated content
   ```

3. **Source Intervals**: `EVERY_10_MIN`, `EVERY_30_MIN`, `HOURLY`, `EVERY_6_HOURS`, `DAILY`, `WEEKLY`

4. **Post Status Values**: `NOT_RELEVANT`, plus relevancy_score tracking

## Frontend Architecture

### Stack

- **Vue 3** (Composition API, `<script setup>`)
- **Inertia.js v2** (SPA routing without API)
- **Tailwind CSS v4** (CSS-first configuration)
- **Reka UI** (headless accessible components)
- **shadcn/ui** (styled component wrappers)
- **Laravel Wayfinder** (type-safe route generation)

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
│   └── Usage/                # Token usage stats
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

### Pivot Tables

- `source_tag` - Sources ↔ Tags
- `content_piece_post` - ContentPieces ↔ Posts
- `team_user` - Teams ↔ Users (with role)

### Important Indexes

- `posts(source_id, uri)` - UNIQUE, prevents duplicates
- `posts(source_id, relevancy_score)` - Sorting by relevance
- `sources(team_id, is_active, next_check_at)` - Scheduling queries
- `token_usage_logs(team_id, created_at)` - Usage reporting

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

### AI Integration

Use `OpenAIService` for all AI operations, which automatically:
- Tracks token usage via `TokenUsageLog`
- Respects team/user token limits
- Handles API errors

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
