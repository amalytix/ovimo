# Ovimo - Product Requirements Document

**Version:** 1.0
**Date:** November 16, 2025
**Author:** Product Team
**Status:** Draft

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Product Overview](#2-product-overview)
3. [User Stories & Personas](#3-user-stories--personas)
4. [Functional Requirements](#4-functional-requirements)
5. [Technical Architecture](#5-technical-architecture)
6. [Data Models](#6-data-models)
7. [API Design](#7-api-design)
8. [User Interface Specifications](#8-user-interface-specifications)
9. [Security & Compliance](#9-security--compliance)
10. [Performance Requirements](#10-performance-requirements)
11. [Testing Strategy](#11-testing-strategy)
12. [Deployment & Infrastructure](#12-deployment--infrastructure)
13. [Monitoring & Observability](#13-monitoring--observability)
14. [MVP Scope](#14-mvp-scope)
15. [Future Roadmap](#15-future-roadmap)
16. [Open Questions & Assumptions](#16-open-questions--assumptions)

---

## 1. Executive Summary

Ovimo is a Laravel-based SaaS application for news monitoring and AI-assisted content creation. The platform enables users to monitor various web sources (RSS feeds, XML sitemaps), automatically discover new content, generate AI summaries, and create derivative content pieces using customizable prompts and OpenAI integration.

### Key Value Propositions

- **Automated Monitoring:** Continuously track multiple content sources with configurable intervals
- **AI-Powered Summarization:** Automatically summarize discovered content using GPT-5.1
- **Content Creation Workflow:** Transform monitored content into new pieces for various channels (Blog, LinkedIn, YouTube)
- **Multi-Tenant Architecture:** Support for teams and collaborative workflows

---

## 2. Product Overview

### 2.1 Problem Statement

Content creators and marketers struggle to:
- Monitor multiple content sources efficiently
- Stay informed about industry news and trends
- Transform discovered insights into original content
- Maintain consistent content production workflows

### 2.2 Solution

Ovimo provides an end-to-end workflow:
1. **Monitor** - Track RSS feeds and XML sitemaps automatically
2. **Discover** - Find new posts with duplicate detection
3. **Summarize** - Generate AI summaries for quick comprehension
4. **Create** - Produce original content using customizable AI prompts
5. **Collaborate** - Share sources and content within teams

### 2.3 Target Users

- Content marketers
- Social media managers
- Bloggers and thought leaders
- Marketing agencies
- Business owners managing their online presence

---

## 3. User Stories & Personas

### 3.1 Primary Persona: Content Marketer

**Name:** Sarah, Marketing Manager
**Goal:** Stay on top of industry trends and produce regular thought leadership content

**User Story:**
> As a content marketer, I want to monitor competitor blogs and industry news sources so that I can quickly identify trending topics and create timely content for my company's LinkedIn and blog.

### 3.2 Key User Flows

#### Flow 1: Source Setup
1. User logs into Ovimo
2. Navigates to Sources page
3. Clicks "Add Source"
4. Selects source type (RSS/XML Sitemap)
5. Configures URL, monitoring interval, tags
6. System validates and saves source
7. Initial check is queued

#### Flow 2: Content Discovery
1. Background job checks source per schedule
2. New posts are identified (URL-based deduplication)
3. AI summarizes new posts
4. User receives webhook notification (if configured)
5. Posts appear in Posts page with bold styling (unread)

#### Flow 3: Content Creation
1. User browses Posts page
2. Filters by tags/sources to find relevant posts
3. Selects multiple related posts
4. Clicks "Create Content"
5. Fills content piece form (name, channel, language, prompt)
6. Clicks "Create with AI"
7. System assembles prompt with placeholders
8. Sends to OpenAI GPT-5.1
9. Displays generated content for editing
10. User copies final content to clipboard

---

## 4. Functional Requirements

### 4.1 Authentication & Authorization

#### FR-AUTH-001: User Registration
- Users register using Laravel Jetstream
- Each user automatically gets a "Personal" team
- Email verification is required before access

#### FR-AUTH-002: Team Management
- Users can belong to multiple teams
- Teams can have multiple users
- Team switching via Jetstream UI
- Roles: "admin" (system-wide), "member" (team-level)

#### FR-AUTH-003: Multi-Tenancy
- All data is scoped to teams
- Sources, posts, prompts, and content belong to teams
- Users see only their current team's data

### 4.2 Sources Management

#### FR-SRC-001: Source Types (MVP)
- Website (RSS Feed) - Parse standard RSS/Atom feeds
- Website (XML Sitemap) - Parse sitemap.xml for URLs

#### FR-SRC-002: Source Attributes
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| internal_name | string | Yes | User-friendly identifier |
| type | enum | Yes | RSS or XML_SITEMAP |
| url | string | Yes | Feed or sitemap URL |
| monitoring_interval | enum | Yes | EVERY_10_MIN, HOURLY, DAILY, etc. |
| is_active | boolean | Yes | Enable/disable monitoring |
| should_notify | boolean | Yes | Send webhook on new posts |
| auto_summarize | boolean | Yes | Enable AI summarization |
| team_id | foreign key | Yes | Owning team |
| tags | many-to-many | No | Categorization |
| last_checked_at | timestamp | No | Last successful check |

#### FR-SRC-003: Source Operations
- **List:** Paginated table with sorting and filtering
- **Create:** Form with validation
- **Update:** Edit all attributes
- **Delete:** Soft delete with confirmation modal
- **Immediate Check:** Manual trigger (high-priority queue)

### 4.3 Posts Management

#### FR-POST-001: Post Attributes
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| uri | string | Yes | Unique identifier (URL) |
| summary | text | No | AI-generated summary |
| source_id | foreign key | Yes | Parent source |
| is_read | boolean | Yes | Read/unread status (default: false) |
| is_hidden | boolean | Yes | Hidden status (default: false) |
| status | enum | Yes | NOT_RELEVANT or CREATE_CONTENT |
| found_at | timestamp | Yes | When first discovered |

#### FR-POST-002: Post List View
- **Columns:** Checkbox, Source Name, Tags, URI (linked), Found At, Summary (clamped), Status, Actions
- **Styling:** Unread posts in bold
- **Default Sort:** Newest first

#### FR-POST-003: Filtering
- By source(s)
- By tag(s)
- By keyword (matches URI or summary)
- By read/unread status
- Toggle to show hidden posts

#### FR-POST-004: Bulk Actions
- Hide selected posts
- Mark selected as read
- Create content from selected posts

#### FR-POST-005: Individual Actions
- Toggle hide/show
- Toggle read/unread
- Toggle status (NOT_RELEVANT ↔ CREATE_CONTENT)
- Create content (single post)

#### FR-POST-006: Auto-Hide
- Team-configurable setting: hide posts after X days
- Posts are hidden but never deleted (for duplicate detection)
- Hidden posts not searchable/filterable

### 4.4 Content Pieces

#### FR-CONTENT-001: Content Attributes
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| internal_name | string | Yes | User-friendly identifier |
| briefing_text | text | No | User's context/instructions |
| channel | enum | Yes | BLOG_POST, LINKEDIN_POST, YOUTUBE_SCRIPT |
| target_language | enum | Yes | GERMAN or ENGLISH |
| status | enum | Yes | NOT_STARTED, DRAFT, FINAL |
| prompt_id | foreign key | No | Selected prompt template |
| full_text | text | No | Generated/edited content |
| team_id | foreign key | Yes | Owning team |
| source_posts | many-to-many | Yes | Related posts |

#### FR-CONTENT-002: AI Generation
1. Load selected prompt template
2. Replace placeholders:
   - `### BRIEFING_TEXT ###` → Content piece briefing
   - `### POSTS_CONTENT ###` → Each post's URL and summary
   - `### TARGET_LANGUAGE ###` → "German" or "English"
3. Send to OpenAI GPT-5.1 via `/v1/responses` API
4. Save response to `full_text`
5. Track token usage

#### FR-CONTENT-003: Content Operations
- **List:** Table view with filtering
- **Create:** Form with linked posts preview (summary + URL)
- **Edit:** Update all fields, re-generate AI content
- **View:** Markdown rendering of full_text
- **Export:** Copy to clipboard

### 4.5 Prompts

#### FR-PROMPT-001: Prompt Attributes
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| internal_name | string | Yes | User-friendly identifier |
| channel | enum | Yes | BLOG_POST, LINKEDIN_POST, YOUTUBE_SCRIPT |
| prompt_text | text | Yes | Template with placeholders |
| team_id | foreign key | Yes | Owning team |

#### FR-PROMPT-002: Placeholder Validation
- System validates mandatory placeholders on save:
  - `### BRIEFING_TEXT ###`
  - `### POSTS_CONTENT ###`
  - `### TARGET_LANGUAGE ###`
- Validation error if any missing

#### FR-PROMPT-003: CRUD Operations
- Full CRUD via dedicated pages
- Filter by channel
- Preview placeholder locations

### 4.6 Notifications

#### FR-NOTIFY-001: Webhook Notifications
- Team-level webhook URL configuration
- POST request for each new post found
- Payload:
  ```json
  {
    "source_name": "Tech Blog",
    "post_url": "https://example.com/article",
    "found_at": "2025-11-16T12:00:00Z",
    "summary": "AI-generated summary..."
  }
  ```
- Queue-based with 3 retries, 60s delay between retries

#### FR-NOTIFY-002: Global Toggle
- Team can enable/disable all notifications
- Per-source notify flag respected

### 4.7 Token Usage & Limits

#### FR-TOKEN-001: Usage Tracking
- Log input + output tokens per request
- Track per user and per team
- Monthly aggregation

#### FR-TOKEN-002: Default Limits
- User: 1,000,000 tokens/month
- Team: 10,000,000 tokens/month
- Configurable at creation time

#### FR-TOKEN-003: Usage Display
- Dashboard cards showing:
  - Current month usage
  - Previous month usage
- Separate views for user and team

#### FR-TOKEN-004: Limit Enforcement
- Check limits before AI requests
- Reject request if limit exceeded
- Clear error message to user

---

## 5. Technical Architecture

### 5.1 Technology Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend Framework | Laravel | 12.x |
| Frontend Framework | Vue.js | 3.x |
| SPA Bridge | Inertia.js | 2.x |
| Styling | Tailwind CSS | 4.x |
| Database | MySQL/PostgreSQL | 8.x/15.x |
| Queue Driver | Redis/Database | - |
| Testing | Pest | 4.x |
| Code Formatting | Laravel Pint | 1.x |
| Authentication | Laravel Jetstream + Fortify | - |
| AI Integration | OpenAI API | GPT-5.1 |

### 5.2 Application Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Browser (Vue + Inertia)               │
└─────────────────────────┬───────────────────────────────┘
                          │
┌─────────────────────────▼───────────────────────────────┐
│                   Laravel Application                    │
├─────────────────────────────────────────────────────────┤
│  Controllers (Inertia)  │  Form Requests  │  Resources  │
├─────────────────────────────────────────────────────────┤
│                     Service Layer                        │
│  SourceMonitor  │  ContentGenerator  │  WebhookSender   │
├─────────────────────────────────────────────────────────┤
│                    Domain Models                         │
│  Team │ User │ Source │ Post │ Content │ Prompt │ Tag   │
├─────────────────────────────────────────────────────────┤
│              Background Jobs (Queue Workers)             │
│  CheckSourceJob  │  SummarizePostJob  │  SendWebhookJob │
├─────────────────────────────────────────────────────────┤
│                   External Services                      │
│            OpenAI API  │  RSS/Sitemap Fetching          │
└─────────────────────────────────────────────────────────┘
```

### 5.3 Queue Architecture

| Queue | Purpose | Priority |
|-------|---------|----------|
| `default` | Standard monitoring jobs | Normal |
| `immediate` | Manual/triggered checks | High |
| `ai` | OpenAI API calls | Normal |
| `webhooks` | Outbound webhook notifications | Normal |

**Job Configuration:**
- Max retries: 3
- Retry delay: 60 seconds
- Failed jobs logged to `failed_jobs` table

### 5.4 Scheduled Tasks

```php
// bootstrap/app.php or routes/console.php
Schedule::job(new CheckSourcesJob())->everyMinute();
Schedule::job(new AutoHidePostsJob())->daily();
Schedule::job(new ResetMonthlyTokenCountersJob())->monthly();
```

**CheckSourcesJob Logic:**
1. Query sources due for check (based on interval)
2. Dispatch individual CheckSourceJob for each
3. Respect queue priority for immediate checks

### 5.5 Service Layer Design

#### SourceMonitorService
```php
class SourceMonitorService
{
    public function check(Source $source): Collection;
    public function parseRssFeed(string $url): array;
    public function parseXmlSitemap(string $url): array;
    public function detectNewPosts(Source $source, array $urls): Collection;
}
```

#### ContentGeneratorService
```php
class ContentGeneratorService
{
    public function generate(ContentPiece $content): string;
    public function buildPrompt(ContentPiece $content): string;
    public function callOpenAI(string $prompt, User $user, Team $team): array;
    public function trackTokenUsage(User $user, Team $team, int $tokens): void;
}
```

#### WebhookService
```php
class WebhookService
{
    public function send(Post $post): void;
    public function buildPayload(Post $post): array;
}
```

---

## 6. Data Models

### 6.1 Entity Relationship Diagram

```
┌─────────┐     ┌─────────┐     ┌─────────┐
│  User   │────<│TeamUser │>────│  Team   │
└─────────┘     └─────────┘     └─────────┘
                                      │
                    ┌─────────────────┼─────────────────┐
                    │                 │                 │
               ┌────▼────┐      ┌─────▼─────┐    ┌─────▼─────┐
               │ Source  │      │  Prompt   │    │  Settings │
               └────┬────┘      └───────────┘    └───────────┘
                    │
               ┌────▼────┐
               │   Post  │
               └────┬────┘
                    │
               ┌────▼─────────┐
               │ContentPiece  │
               └──────────────┘

Source ←──>> SourceTag
Post ←──>> ContentPiecePost
Team ←──>> TokenUsageLog
```

### 6.2 Database Migrations

#### teams (Jetstream default + extensions)
```php
Schema::table('teams', function (Blueprint $table) {
    $table->boolean('notifications_enabled')->default(true);
    $table->string('webhook_url')->nullable();
    $table->integer('post_auto_hide_days')->nullable();
    $table->bigInteger('monthly_token_limit')->default(10000000);
});
```

#### sources
```php
Schema::create('sources', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained()->cascadeOnDelete();
    $table->string('internal_name');
    $table->enum('type', ['RSS', 'XML_SITEMAP']);
    $table->string('url');
    $table->enum('monitoring_interval', [
        'EVERY_10_MIN', 'EVERY_30_MIN', 'HOURLY',
        'EVERY_6_HOURS', 'DAILY', 'WEEKLY'
    ]);
    $table->boolean('is_active')->default(true);
    $table->boolean('should_notify')->default(false);
    $table->boolean('auto_summarize')->default(true);
    $table->timestamp('last_checked_at')->nullable();
    $table->timestamp('next_check_at')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['team_id', 'is_active', 'next_check_at']);
});
```

#### tags
```php
Schema::create('tags', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->timestamps();

    $table->unique(['team_id', 'name']);
});
```

#### source_tag (pivot)
```php
Schema::create('source_tag', function (Blueprint $table) {
    $table->foreignId('source_id')->constrained()->cascadeOnDelete();
    $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
    $table->primary(['source_id', 'tag_id']);
});
```

#### posts
```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('source_id')->constrained()->cascadeOnDelete();
    $table->string('uri', 2048)->unique();
    $table->text('summary')->nullable();
    $table->boolean('is_read')->default(false);
    $table->boolean('is_hidden')->default(false);
    $table->enum('status', ['NOT_RELEVANT', 'CREATE_CONTENT'])->default('NOT_RELEVANT');
    $table->timestamp('found_at');
    $table->timestamps();

    $table->index(['source_id', 'is_hidden', 'found_at']);
    $table->index(['source_id', 'is_read']);
});
```

#### prompts
```php
Schema::create('prompts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained()->cascadeOnDelete();
    $table->string('internal_name');
    $table->enum('channel', ['BLOG_POST', 'LINKEDIN_POST', 'YOUTUBE_SCRIPT']);
    $table->text('prompt_text');
    $table->timestamps();
});
```

#### content_pieces
```php
Schema::create('content_pieces', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained()->cascadeOnDelete();
    $table->foreignId('prompt_id')->nullable()->constrained()->nullOnDelete();
    $table->string('internal_name');
    $table->text('briefing_text')->nullable();
    $table->enum('channel', ['BLOG_POST', 'LINKEDIN_POST', 'YOUTUBE_SCRIPT']);
    $table->enum('target_language', ['GERMAN', 'ENGLISH']);
    $table->enum('status', ['NOT_STARTED', 'DRAFT', 'FINAL'])->default('NOT_STARTED');
    $table->longText('full_text')->nullable();
    $table->timestamps();
});
```

#### content_piece_post (pivot)
```php
Schema::create('content_piece_post', function (Blueprint $table) {
    $table->foreignId('content_piece_id')->constrained()->cascadeOnDelete();
    $table->foreignId('post_id')->constrained()->cascadeOnDelete();
    $table->primary(['content_piece_id', 'post_id']);
});
```

#### token_usage_logs
```php
Schema::create('token_usage_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->integer('input_tokens');
    $table->integer('output_tokens');
    $table->integer('total_tokens');
    $table->string('model');
    $table->string('operation'); // 'summarize', 'generate_content'
    $table->timestamp('created_at');

    $table->index(['team_id', 'created_at']);
    $table->index(['user_id', 'created_at']);
});
```

### 6.3 Model Relationships

```php
// Team.php
public function sources(): HasMany;
public function tags(): HasMany;
public function prompts(): HasMany;
public function contentPieces(): HasMany;
public function tokenUsageLogs(): HasMany;

// Source.php
public function team(): BelongsTo;
public function posts(): HasMany;
public function tags(): BelongsToMany;

// Post.php
public function source(): BelongsTo;
public function contentPieces(): BelongsToMany;

// ContentPiece.php
public function team(): BelongsTo;
public function prompt(): BelongsTo;
public function posts(): BelongsToMany;

// Prompt.php
public function team(): BelongsTo;
public function contentPieces(): HasMany;
```

---

## 7. API Design

### 7.1 Route Structure

All routes use Inertia.js for SPA-style navigation.

```php
// routes/web.php

// Dashboard
Route::get('/', DashboardController::class)->name('dashboard');

// Sources
Route::resource('sources', SourceController::class);
Route::post('sources/{source}/check', [SourceController::class, 'check'])->name('sources.check');

// Posts
Route::get('posts', [PostController::class, 'index'])->name('posts.index');
Route::patch('posts/{post}/read', [PostController::class, 'toggleRead'])->name('posts.read');
Route::patch('posts/{post}/hide', [PostController::class, 'toggleHide'])->name('posts.hide');
Route::patch('posts/{post}/status', [PostController::class, 'toggleStatus'])->name('posts.status');
Route::post('posts/bulk', [PostController::class, 'bulk'])->name('posts.bulk');

// Content
Route::resource('content', ContentPieceController::class);
Route::post('content/{content}/generate', [ContentPieceController::class, 'generate'])->name('content.generate');
Route::post('content/{content}/copy', [ContentPieceController::class, 'copy'])->name('content.copy');

// Prompts
Route::resource('prompts', PromptController::class);

// Settings
Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');

// Token Usage
Route::get('usage', TokenUsageController::class)->name('usage.index');
```

### 7.2 Controller Pattern

```php
class SourceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Sources/Index', [
            'sources' => Source::query()
                ->where('team_id', auth()->user()->currentTeam->id)
                ->with('tags')
                ->withCount('posts')
                ->paginate(15)
                ->through(fn ($source) => new SourceResource($source)),
            'tags' => Tag::where('team_id', auth()->user()->currentTeam->id)->get(),
        ]);
    }

    public function store(StoreSourceRequest $request): RedirectResponse
    {
        $source = Source::create([
            'team_id' => auth()->user()->currentTeam->id,
            ...$request->validated(),
        ]);

        $source->tags()->sync($request->tag_ids ?? []);

        CheckSourceJob::dispatch($source)->onQueue('immediate');

        return redirect()->route('sources.index')
            ->with('success', 'Source created successfully.');
    }
}
```

### 7.3 Form Request Validation

```php
class StoreSourceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'internal_name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['RSS', 'XML_SITEMAP'])],
            'url' => ['required', 'url', 'max:2048'],
            'monitoring_interval' => ['required', Rule::in([
                'EVERY_10_MIN', 'EVERY_30_MIN', 'HOURLY',
                'EVERY_6_HOURS', 'DAILY', 'WEEKLY'
            ])],
            'is_active' => ['boolean'],
            'should_notify' => ['boolean'],
            'auto_summarize' => ['boolean'],
            'tag_ids' => ['array'],
            'tag_ids.*' => ['exists:tags,id'],
        ];
    }
}
```

---

## 8. User Interface Specifications

### 8.1 Navigation Structure

```
┌─────────────────────────────────────────┐
│  Ovimo Logo    [Posts] [Sources]        │
│                [Content] [Prompts]      │
│                [Settings]               │
│                         [Team Switcher] │
│                         [User Menu]     │
└─────────────────────────────────────────┘
```

Default landing: Posts page

### 8.2 Sources Page

**Layout:** Data table with toolbar

**Table Columns:**
1. Internal Name (sortable)
2. Type (badge)
3. Tags (pill badges)
4. URL (truncated with tooltip)
5. Posts Count (sortable)
6. Last Checked (relative time)
7. Actions (dropdown menu)

**Toolbar:**
- Add Source button
- Filter by type
- Filter by tags
- Search by name

**Add/Edit Modal:**
- Form fields as per FR-SRC-002
- Tag multi-select
- Interval dropdown
- Toggle switches for booleans

### 8.3 Posts Page

**Layout:** Filterable data table with bulk actions

**Filter Bar:**
- Source multi-select
- Tag multi-select
- Keyword search input
- Read/Unread toggle
- Show Hidden toggle

**Table Columns:**
1. Checkbox
2. Source Name
3. Tags (pills)
4. Post URI (external link icon)
5. Found At (relative time)
6. Summary (3-line clamp, expand on click)
7. Status (dropdown/badge)
8. Actions (icon buttons)

**Bulk Actions Toolbar:**
- Appears when items selected
- Hide Selected
- Mark as Read
- Create Content from Selected

**Empty State:**
- Friendly message when no posts match filters
- Suggestion to adjust filters or add sources

### 8.4 Content Pieces Page

**Layout:** Data table

**Table Columns:**
1. Internal Name (link to detail)
2. Channel (badge)
3. Language
4. Status (badge with color)
5. Created At
6. Actions

**Detail View:**
- Header with name and status
- Briefing text section
- Linked posts (cards with summary preview)
- Full text area (markdown rendered)
- Edit button
- Copy to Clipboard button
- Re-generate with AI button

**Create/Edit Form:**
- Name input
- Briefing textarea
- Channel select
- Language select
- Status select
- Prompt select (filtered by channel)
- Post selection (from available posts)
- Save button
- Generate with AI button

### 8.5 Prompts Page

**Layout:** CRUD table

**Table Columns:**
1. Internal Name
2. Channel (badge)
3. Created At
4. Actions (Edit, Delete)

**Create/Edit Form:**
- Name input
- Channel select
- Prompt text textarea (large)
- Placeholder validation feedback (real-time)
- Placeholder helper text

### 8.6 Settings Page

**Team Settings:**
- Notifications enabled toggle
- Webhook URL input (with test button)
- Auto-hide posts after X days (number input)

### 8.7 Token Usage Dashboard

**Cards:**
- Current Month Usage (number with visual progress bar)
- Previous Month Usage (number)
- User Limit vs Team Limit breakdown

### 8.8 Design System

**Colors (Tailwind):**
- Primary: blue-600
- Success: green-600
- Warning: yellow-600
- Danger: red-600
- Neutral: gray-600

**Status Badges:**
- NOT_STARTED: gray
- DRAFT: yellow
- FINAL: green
- NOT_RELEVANT: gray
- CREATE_CONTENT: blue

**Dark Mode:** Support required (follow existing patterns)

**Responsive:** Mobile-first, breakpoints for tablet and desktop

---

## 9. Security & Compliance

### 9.1 Authentication

- Laravel Jetstream with Fortify
- Email verification required
- Session-based authentication (Inertia SPA)
- CSRF protection on all forms

### 9.2 Authorization

- Team-based data isolation
- All queries scoped to current team
- Middleware to verify team membership
- Policy classes for each resource

```php
class SourcePolicy
{
    public function view(User $user, Source $source): bool
    {
        return $user->currentTeam->id === $source->team_id;
    }
}
```

### 9.3 Data Protection

- All passwords hashed (bcrypt)
- OpenAI API key stored in environment variables
- No API keys stored per-user (MVP)
- HTTPS enforced in production

### 9.4 Input Validation

- All user input validated via Form Requests
- URL validation for sources
- Text sanitization for prompts
- Rate limiting on AI generation endpoints

### 9.5 Logging

- Authentication events logged
- AI API calls logged (without sensitive data)
- Failed jobs logged
- User actions auditable

---

## 10. Performance Requirements

### 10.1 Response Times

| Operation | Target | Maximum |
|-----------|--------|---------|
| Page load (Inertia) | < 200ms | 500ms |
| Source list (100 items) | < 300ms | 1s |
| Post list (1000 items) | < 500ms | 2s |
| AI generation | < 30s | 60s |
| Webhook delivery | < 5s | 30s |

### 10.2 Throughput

- Handle 100 concurrent users
- Process 1000 source checks/hour
- Support 10,000 posts/team
- Generate 100 content pieces/day

### 10.3 Database Optimization

- Indexes on frequently queried columns
- Eager loading to prevent N+1
- Pagination on all list views
- Query caching where appropriate

### 10.4 Queue Performance

- Dedicated workers per queue
- Horizontal scaling support
- Job batching for bulk operations
- Failed job retry logic

---

## 11. Testing Strategy

### 11.1 Testing Pyramid

```
        ┌─────────┐
        │  E2E    │  (Pest Browser Tests)
       ─┴─────────┴─
      ┌─────────────┐
      │ Integration │  (Feature Tests)
     ─┴─────────────┴─
    ┌─────────────────┐
    │    Unit Tests   │  (Unit Tests)
    └─────────────────┘
```

### 11.2 Unit Tests

**Location:** `tests/Unit/`

**Coverage:**
- Service classes (SourceMonitorService, ContentGeneratorService)
- Model methods and casts
- Validation rules
- Helper functions

**Example:**
```php
// tests/Unit/Services/SourceMonitorServiceTest.php
it('parses RSS feed correctly', function () {
    $service = new SourceMonitorService();
    $result = $service->parseRssFeed($rssXmlString);

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(10)
        ->and($result[0])->toHaveKeys(['url', 'title', 'published_at']);
});
```

### 11.3 Feature Tests

**Location:** `tests/Feature/`

**Coverage:**
- Controller actions (CRUD operations)
- Authentication flows
- Authorization policies
- Form validation
- Queue job dispatching

**Example:**
```php
// tests/Feature/SourceControllerTest.php
it('creates a source for the current team', function () {
    $user = User::factory()->withTeam()->create();

    $this->actingAs($user)
        ->post('/sources', [
            'internal_name' => 'Test Blog',
            'type' => 'RSS',
            'url' => 'https://example.com/feed.xml',
            'monitoring_interval' => 'DAILY',
        ])
        ->assertRedirect('/sources');

    $this->assertDatabaseHas('sources', [
        'team_id' => $user->currentTeam->id,
        'internal_name' => 'Test Blog',
    ]);
});
```

### 11.4 Browser Tests (Pest 4)

**Location:** `tests/Browser/`

**Coverage:**
- Critical user flows
- JavaScript interactions
- Dark mode rendering
- Form submissions
- Bulk actions

**Example:**
```php
// tests/Browser/PostsPageTest.php
it('allows bulk marking posts as read', function () {
    $user = User::factory()->withTeam()->create();
    $posts = Post::factory()->count(3)->create(['team_id' => $user->currentTeam->id]);

    $this->actingAs($user);

    $page = visit('/posts');

    $page->assertSee('Posts')
        ->check("post-checkbox-{$posts[0]->id}")
        ->check("post-checkbox-{$posts[1]->id}")
        ->click('Mark as Read')
        ->assertSee('2 posts marked as read');

    expect($posts[0]->fresh()->is_read)->toBeTrue();
    expect($posts[1]->fresh()->is_read)->toBeTrue();
});
```

### 11.5 API Testing

**Coverage:**
- OpenAI API integration (mocked)
- Webhook delivery (mocked external service)
- RSS/Sitemap parsing (sample files)

**Example:**
```php
it('calls OpenAI API with correct parameters', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Generated text']]],
            'usage' => ['total_tokens' => 150],
        ]),
    ]);

    $service = new ContentGeneratorService();
    $result = $service->callOpenAI('Test prompt', $user, $team);

    expect($result['text'])->toBe('Generated text');
    expect($result['tokens'])->toBe(150);

    Http::assertSent(function ($request) {
        return $request['model'] === 'gpt-5.1';
    });
});
```

### 11.6 Test Data

**Factories:**
- UserFactory (with team trait)
- TeamFactory
- SourceFactory
- PostFactory
- PromptFactory
- ContentPieceFactory
- TagFactory

**Seeders:**
- DatabaseSeeder orchestrates all
- DemoDataSeeder for development
- Realistic sample data

### 11.7 CI Pipeline

```yaml
# .github/workflows/tests.yml
- Run PHP linting (Pint)
- Run static analysis (if configured)
- Run unit tests
- Run feature tests
- Run browser tests
- Generate coverage report
```

---

## 12. Deployment & Infrastructure

### 12.1 Environment Configuration

**.env Variables:**
```env
# Application
APP_NAME=Ovimo
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://ovimo.example.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ovimo
DB_USERNAME=
DB_PASSWORD=

# Queue
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# OpenAI
OPENAI_API_KEY=
OPENAI_MODEL=gpt-5.1

# Token Limits
DEFAULT_USER_TOKEN_LIMIT=1000000
DEFAULT_TEAM_TOKEN_LIMIT=10000000
```

### 12.2 Production Checklist

- [ ] Set APP_ENV=production
- [ ] Set APP_DEBUG=false
- [ ] Configure proper database
- [ ] Set up Redis for queues
- [ ] Configure queue workers (Supervisor)
- [ ] Set up SSL certificate
- [ ] Configure email driver
- [ ] Set OpenAI API key
- [ ] Run migrations
- [ ] Cache config/routes/views
- [ ] Set up backups
- [ ] Configure monitoring

### 12.3 Queue Worker Configuration

**Supervisor Config:**
```ini
[program:ovimo-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/ovimo/artisan queue:work redis --queue=immediate,ai,webhooks,default --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/ovimo/storage/logs/worker.log
```

### 12.4 Scheduled Tasks

**Cron Entry:**
```cron
* * * * * cd /path/to/ovimo && php artisan schedule:run >> /dev/null 2>&1
```

---

## 13. Monitoring & Observability

### 13.1 Application Logging

**Channels:**
- Stack (daily files)
- Slack/Discord for critical errors
- Dedicated channel for AI operations

**Log Levels:**
- ERROR: API failures, job failures
- WARNING: Rate limits, validation issues
- INFO: User actions, job completions
- DEBUG: Development only

**Structured Logging:**
```php
Log::info('Source checked successfully', [
    'source_id' => $source->id,
    'new_posts' => $newPostsCount,
    'duration_ms' => $duration,
]);
```

### 13.2 Metrics to Track

**Application:**
- Request response times
- Error rates
- Queue job throughput
- Queue wait times
- Failed job count

**Business:**
- Sources monitored per team
- Posts discovered per day
- AI generations per day
- Token consumption trends
- Active users per day

### 13.3 Alerting

**Critical Alerts:**
- Application error rate > 1%
- Queue backup > 1000 jobs
- OpenAI API consistently failing
- Database connection failures

**Warning Alerts:**
- Slow response times (> 2s avg)
- Queue processing delays
- Token limit approaching

### 13.4 Health Checks

```php
// routes/web.php
Route::get('/health', function () {
    return [
        'status' => 'ok',
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'queue' => Queue::size('default'),
        'timestamp' => now(),
    ];
});
```

---

## 14. MVP Scope

### 14.1 Included in MVP

**Core Features:**
- [x] User registration and authentication (Jetstream)
- [x] Team management (multi-tenant)
- [x] Source management (RSS, XML Sitemap only)
- [x] Automated monitoring with configurable intervals
- [x] Post discovery and duplicate detection
- [x] AI summarization (optional per source)
- [x] Post filtering and management
- [x] Prompt templates with placeholder validation
- [x] Content piece creation with AI generation
- [x] Token usage tracking and limits
- [x] Webhook notifications for new posts
- [x] Copy to clipboard export

**Technical:**
- [x] Laravel 12 + Vue 3 + Inertia 2
- [x] Tailwind CSS 4 styling
- [x] Queue-based background processing
- [x] Redis for queue driver
- [x] Pest 4 for testing
- [x] Basic error handling and logging

### 14.2 Explicitly Out of MVP Scope

**Features:**
- [ ] Social media sources (LinkedIn, Twitter, Instagram, YouTube)
- [ ] Website regex scraping
- [ ] Email notifications (only webhooks)
- [ ] Comments on content pieces
- [ ] Content versioning (data model supports, UI not implemented)
- [ ] Full content storage (summaries only)
- [ ] Rich text editor (plaintext only)
- [ ] API rate limiting and advanced error handling
- [ ] Legal/compliance (robots.txt, ToS)
- [ ] Per-team OpenAI API keys
- [ ] Admin dashboard

**Technical:**
- [ ] Advanced caching strategies
- [ ] Full observability stack (APM)
- [ ] Horizontal scaling configuration
- [ ] CDN integration
- [ ] Backup automation
- [ ] Blue/green deployments

---

## 15. Future Roadmap

### Phase 2: Social Media Integration
- YouTube channel monitoring
- LinkedIn profile tracking
- Twitter/X feed monitoring
- Instagram profile monitoring
- OAuth integration for platforms

### Phase 3: Enhanced Content Features
- Rich text editor (Markdown/WYSIWYG)
- Content versioning with history
- Comments and collaboration
- Direct publishing integrations
- Content performance tracking

### Phase 4: Advanced Intelligence
- Trend detection across sources
- Content similarity scoring
- Automated content suggestions
- Multiple AI model support (Anthropic)
- Custom AI fine-tuning

### Phase 5: Enterprise Features
- SSO integration
- Advanced role-based permissions
- Audit logging
- Compliance reporting
- White-labeling

---

## 16. Open Questions & Assumptions

### 16.1 Open Questions

1. **Source Validation:** Should we validate that RSS/XML sitemap URLs are actually accessible and valid during source creation? What's the UX for invalid URLs?

Not in MVP.

2. **Summarization Failures:** When AI summarization fails after retries, should we:
   - Notify the user?
   - Retry with a different approach?
   - Allow manual summarization?

Let the user allow to add a manual summary.

3. **Post URI Uniqueness:** Is URI uniqueness global or per-source? Current assumption: global (prevents same article appearing from multiple sources).

Per Source. Per Team.

4. **Token Limit Enforcement:** When a user/team hits their token limit:
   - Should we allow overage with warnings?
   - Hard block until next month?
   - Allow admin override?

Allow overage with warnings

5. **Concurrent Editing:** If two team members edit the same content piece simultaneously, how to handle conflicts?

Do not handle conflicts yet. Assume there is only a single editor.

6. **Source Deactivation:** When a source is deactivated, should:
   - Existing posts remain visible?
   - Pending jobs be cancelled?
   - Summary be re-attempted when reactivated?

If a source is not active it means that it will not checked during future checks.

Existing posts can be found and should be shown. Pending jobs should get completed.

7. **Prompt Deletion:** If a prompt is deleted but content pieces reference it, should we:
   - Prevent deletion?
   - Nullify the reference?
   - Keep prompt but mark as "archived"?

A content piece should not directly reference the prompt.

8. **Timezone Handling:** How to handle timezones for:
   - Post discovery timestamps
   - Monitoring schedules
   - Auto-hide calculations

Use UTC for internal times and show it in user's timezone. Assume Berlin time as default.

9. **Webhook Retry Logic:** After 3 failed webhook attempts:
   - Mark as permanently failed?
   - Notify team admin?
   - Provide retry button?

Add an error log and mark as permaently failed.

10. **Tag Management:** Should tags be:
    - Pre-defined by admin?
    - Freely created by users?
    - Limited per team?

Freely defiend by users.

### 16.2 Assumptions

1. **Team Context:** All operations assume a user has an active team. Team switching reloads all data.

2. **Single Currency:** Token usage is measured in OpenAI tokens, not monetary cost (for MVP).

3. **English UI:** Application UI is in English; target_language only affects AI output.

4. **No Offline Support:** Application requires internet connectivity for all features.

5. **Modern Browsers:** Supporting Chrome, Firefox, Safari, Edge (latest versions only).

6. **Server Resources:** Adequate server resources for:
   - At least 4 queue workers
   - Redis for queue management
   - Database for thousands of posts

7. **OpenAI Availability:** OpenAI API is available and stable (no fallback provider in MVP).

8. **UTF-8 Throughout:** All text content stored and processed as UTF-8.

9. **No File Uploads:** No file attachment support (content is text-based only).

10. **Immediate Consistency:** Database transactions ensure immediate consistency (no eventual consistency patterns).

---

## Appendix A: Glossary

| Term | Definition |
|------|------------|
| Source | A monitored content origin (RSS feed, XML sitemap) |
| Post | A discovered content item from a source |
| Content Piece | User-generated content based on posts |
| Prompt | Template for AI content generation |
| Team | Multi-tenant organization unit |
| Token | OpenAI API usage measurement unit |
| Webhook | HTTP callback for notifications |

---

## Appendix B: References

- [Laravel 12 Documentation](https://laravel.com/docs/12.x)
- [Inertia.js v2 Guide](https://inertiajs.com/)
- [Vue 3 Composition API](https://vuejs.org/guide/)
- [Tailwind CSS v4](https://tailwindcss.com/)
- [Pest Testing Framework](https://pestphp.com/)
- [OpenAI API Reference](https://platform.openai.com/docs/api-reference)
- [Laravel Jetstream](https://jetstream.laravel.com/)
