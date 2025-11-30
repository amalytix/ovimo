# Admin Interface Design

## Overview

A comprehensive admin interface for the multi-tenant SaaS application Ovimo, enabling administrators to:

- Help users with typical support requests
- Monitor system stability and performance
- Detect fair use violations and over-usage
- Track platform growth

## Requirements

- Users can be designated as admins via `is_admin` boolean field
- Admins access the Admin Area via a sidebar menu entry below "Usage"
- User ID 1 is automatically set as admin via migration

---

## Page Structure

```
Admin Area (/admin)
├── Dashboard (/admin)
├── Users (/admin/users)
│   └── Edit User (/admin/users/{id}/edit)
├── Teams (/admin/teams)
│   └── Edit Team (/admin/teams/{id}/edit)
└── System Health (/admin/system-health)
    ├── Job Queue (/admin/system-health/jobs)
    ├── Source Health (/admin/system-health/sources)
    └── Error Logs (/admin/system-health/errors)
```

**Total: 9 pages**

---

## Database Changes

### Users Table

```php
+ is_admin (boolean, default: false)
+ is_active (boolean, default: true)
```

### Teams Table

```php
+ is_active (boolean, default: true)
```

### Migration Seeder

```php
// Set user ID 1 as admin
UPDATE users SET is_admin = true WHERE id = 1;
```

---

## Authorization

### Admin Middleware

Create `EnsureUserIsAdmin` middleware:
- Check `auth()->user()->is_admin === true`
- Redirect non-admins to dashboard with error message

### Inactive User Enforcement

- Modify login logic: reject login if `is_active === false`
- Display message: "Your account has been deactivated. Contact support."

### Inactive Team Enforcement

- `MonitorSource` job: skip execution if team `is_active === false`
- Token-consuming operations: check team active status before processing
- Display UI message when user attempts features on inactive team

---

## Page Specifications

### 1. Admin Dashboard

The central overview page showing key metrics from all areas.

#### Layout

```
┌─────────────────────────────────────────────────────────────────────┐
│  ADMIN DASHBOARD                                                     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  PLATFORM OVERVIEW                                                   │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐│
│  │ Total Users  │ │ Total Teams  │ │ New Signups  │ │ Logins (7d)  ││
│  │     156      │ │      43      │ │   12 (7d)    │ │     89       ││
│  └──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘│
│                                                                      │
│  SYSTEM HEALTH                                                       │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐│
│  │ Pending Jobs │ │ Failed Jobs  │ │ Failing      │ │ Errors (24h) ││
│  │     23       │ │    3 (24h)   │ │ Sources: 5   │ │     12       ││
│  │   ● Green    │ │   ● Yellow   │ │   ● Yellow   │ │   ● Green    ││
│  └──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘│
│                                                                      │
│  USAGE & FAIR USE                                                    │
│  ┌──────────────┐ ┌──────────────┐ ┌─────────────────────────────┐  │
│  │ Tokens Today │ │ Tokens (7d)  │ │ Source Checks               │  │
│  │   1.2M       │ │    8.4M      │ │ Today: 2,340 | 7d: 15,230   │  │
│  └──────────────┘ └──────────────┘ └─────────────────────────────┘  │
│                                                                      │
│  ┌─────────────────────────────────┐ ┌─────────────────────────────┐│
│  │ TOP 5 TEAMS BY TOKEN USAGE (7d) │ │ TEAMS APPROACHING LIMIT     ││
│  │                                  │ │ (>80% of monthly quota)     ││
│  │ 1. Acme Corp      2.1M (21%)    │ │                             ││
│  │ 2. Beta Inc       1.8M (18%)    │ │ • Gamma LLC - 92%           ││
│  │ 3. Gamma LLC      1.4M (14%)    │ │ • Delta Co - 85%            ││
│  │ 4. Delta Co       0.9M (9%)     │ │                             ││
│  │ 5. Echo Ltd       0.7M (7%)     │ │ (2 teams)                   ││
│  └─────────────────────────────────┘ └─────────────────────────────┘│
│                                                                      │
│  ┌─────────────────────────────────┐ ┌─────────────────────────────┐│
│  │ TOKEN USAGE (LAST 28 DAYS)      │ │ RECENT REGISTRATIONS        ││
│  │                                  │ │                             ││
│  │  ▄▄▆▇█▇▆▅▄▃▄▅▆▇█▇▆▅▄▅▆▇▆▅▄▃▄▅  │ │ • john@new.com      2h ago  ││
│  │  ─────────────────────────────  │ │ • jane@startup.io   1d ago  ││
│  │  Nov 2                   Nov 30  │ │ • bob@agency.com    2d ago  ││
│  │                                  │ │ • alice@corp.net    3d ago  ││
│  │                                  │ │ • chris@dev.org     5d ago  ││
│  └─────────────────────────────────┘ └─────────────────────────────┘│
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

#### Metrics Specifications

**Platform Overview (Row 1):**

| Metric | Source | Purpose |
|--------|--------|---------|
| Total Users | `count(users)` | Platform size |
| Total Teams | `count(teams)` | Multi-tenancy scale |
| New Signups (7d) | `users where created_at >= 7 days ago` | Growth indicator |
| Logins (7d) | `distinct user_id from sessions where last_activity >= 7 days ago` | Engagement |

**System Health (Row 2):**

| Metric | Source | Warning | Critical |
|--------|--------|---------|----------|
| Pending Jobs | `count(jobs)` | >100 | >500 |
| Failed Jobs (24h) | `count(failed_jobs) last 24h` | >10 | >50 |
| Failing Sources | `sources where consecutive_failures > 0` | >5% | >10% |
| Errors (24h) | `activity_logs where level='error' last 24h` | >50 | >200 |

**Usage (Row 3):**

| Metric | Source |
|--------|--------|
| Tokens Today | `sum(total_tokens) from token_usage_logs today` |
| Tokens (7d) | `sum(total_tokens) from token_usage_logs last 7 days` |
| Source Checks Today/7d | `count(activity_logs) where event_type='source.checked'` |

**Tables (Row 4):**

| Widget | Data |
|--------|------|
| Top 5 Teams by Token Usage | Teams ordered by `sum(total_tokens)` last 7 days |
| Teams Approaching Limit | Teams where `current_usage / monthly_token_limit > 0.8` |

**Chart & List (Row 5):**

| Widget | Data |
|--------|------|
| Token Usage Graph (28d) | Daily `sum(total_tokens)` as bar/line chart |
| Recent Registrations | Last 5 users ordered by `created_at desc` |

#### Interactivity

- Total Users → Users List page
- Total Teams → Teams List page
- System Health cards → Respective System Health sub-page
- Team names in tables → Team Edit page
- User emails in Recent Registrations → User Edit page

---

### 2. Users List

**Route:** `/admin/users`

#### Table Columns

| Column | Data | Notes |
|--------|------|-------|
| Email | `email` | Primary identifier, clickable to edit |
| Name | `name` | |
| Status | `is_active` | Badge: Active (green) / Inactive (red) |
| Last Login | `sessions.last_activity` | Relative time ("2 hours ago") |
| Teams | count of team memberships | Badge with number |
| Sources | count across all teams | |
| Posts | count across all teams | |
| Source Checks (7d) | from activity logs | |
| Token Usage (7d) | from `token_usage_logs` | |

#### Actions Column

- **Edit** (pencil icon) → User Edit page
- **Impersonate** (user-switch icon) → Start impersonation session

#### Search & Filters

- Search by email or name
- Filter by: Status (Active/Inactive), Has errors (last 24h), Approaching token limit

#### Sorting

- Default: Last login (most recent first)
- Sortable columns: Email, Name, Last Login, Sources, Posts, Token Usage

---

### 3. User Edit

**Route:** `/admin/users/{id}/edit`

#### User Information Section

| Field | Type | Notes |
|-------|------|-------|
| Name | Text input | |
| Email | Text input | |
| Email Verified | Checkbox | Manually verify if needed |
| Status | Toggle | Active/Inactive |
| Admin | Toggle | Only super admins can change |
| Password | Button | "Send Reset Email" |

#### Account Stats (Read-only Cards)

- Created: `created_at`
- Last Login: from sessions
- Token Usage This Month: used / limit (progress bar)
- 2FA Status: Enabled/Disabled

#### Team Memberships Table

| Column | Data |
|--------|------|
| Team | Team name |
| Role | Owner/Member |
| Sources | Count |
| Posts | Count |
| Actions | View Team link |

#### Recent Activity

- Last 10 entries from `activity_logs` for this user
- Simple timeline: timestamp, event_type, description

#### Quick Actions

- **Impersonate User** - Enter user's session
- **Force Logout** - Terminate all sessions
- **Send Password Reset** - Trigger password reset email

---

### 4. Teams List

**Route:** `/admin/teams`

#### Table Columns

| Column | Data | Notes |
|--------|------|-------|
| Team Name | `name` | Clickable to edit |
| Status | `is_active` | Badge: Active (green) / Inactive (red) |
| Owner | Owner user's email | Link to user edit |
| Members | count from `team_user` | |
| Sources | count (active/total) | e.g. "8/12" |
| Posts | total count | |
| Content Pieces | total count | |
| Source Checks (7d) | from activity logs | |
| Token Usage (7d) | from `token_usage_logs` | With % of limit |

#### Actions Column

- **Edit** (pencil icon) → Team Edit page

#### Search & Filters

- Search by team name or owner email
- Filter by: Status (Active/Inactive), Approaching token limit (>80%)

#### Sorting

- Default: Token Usage 7d (highest first)
- Sortable: Name, Members, Sources, Posts, Token Usage

---

### 5. Team Edit

**Route:** `/admin/teams/{id}/edit`

#### Team Information Section

| Field | Type | Notes |
|-------|------|-------|
| Team Name | Text input | |
| Owner | Dropdown | Select from team members, enables transfer |
| Status | Toggle | Active/Inactive with warning message |

**Warning when inactive:** "Inactive teams cannot check sources or use AI features"

#### Limits & Quotas Section

| Field | Type | Current Column |
|-------|------|----------------|
| Monthly Token Limit | Number input | `monthly_token_limit` |
| Post Auto-Hide Days | Number input | `post_auto_hide_days` |

#### Team KPIs (Read-only Cards)

| Metric | Source |
|--------|--------|
| Created | `created_at` |
| Members | count |
| Active Sources | count where `is_active = true` |
| Total Posts | count |
| Content Pieces | count |
| Token Usage This Month | used / limit (progress bar) |
| Source Checks Today | count |
| Source Checks (7d) | count |

#### Team Members Table

| Column | Data |
|--------|------|
| User | Name |
| Email | Email address |
| Role | Owner/Member |
| Last Login | Relative time |
| Actions | View User link |

#### Sources Overview (Top 10 by Activity)

| Column | Data |
|--------|------|
| Source | Name |
| Type | RSS/Website |
| Status | Active/Failing badge |
| Last Check | Relative time |
| Posts Found | Total count |
| Failures | `consecutive_failures` |

#### Recent Activity

- Last 10 entries from `activity_logs` filtered to this team

---

### 6. System Health Overview

**Route:** `/admin/system-health`

Quick-glance page showing system status with links to detailed views.

#### Health Status Cards

| Card | Metric | Warning | Critical |
|------|--------|---------|----------|
| Job Queue | Pending jobs count | >100 | >500 |
| Failed Jobs | Count last 24h | >10 | >50 |
| Source Health | % of sources failing | >5% | >10% |
| Error Rate | Errors last 24h | >50 | >200 |

Each card displays: current value, status color (green/yellow/red), link to detail page.

---

### 7. Job Queue

**Route:** `/admin/system-health/jobs`

#### Queue Status Cards

- Pending Jobs (total count)
- Reserved Jobs (currently processing)
- Failed Jobs (last 24h)
- Oldest Job Age (minutes waiting)

#### Pending Jobs Table

| Column | Data |
|--------|------|
| Queue | Queue name (default, high, low) |
| Count | Number of jobs |
| Oldest | Age of oldest job |

#### Failed Jobs Table

| Column | Data |
|--------|------|
| Job Type | Extracted from payload |
| Queue | Queue name |
| Failed At | Timestamp |
| Error | Truncated exception message |
| Actions | View, Retry, Delete |

#### Actions

- **View**: Modal showing full exception + payload
- **Retry**: Re-queue the job
- **Delete**: Remove from failed jobs
- **Bulk actions**: Retry All, Delete All (with confirmation)

---

### 8. Source Health

**Route:** `/admin/system-health/sources`

#### Summary Cards

- Total Active Sources
- Currently Failing (`consecutive_failures > 0`)
- Auto-Disabled (`consecutive_failures >= 3`)
- Overdue Checks (`next_check_at < now`)

#### Failing Sources Table

| Column | Data |
|--------|------|
| Source | Name |
| Team | Team name (link to team) |
| Type | RSS/Website |
| Failures | `consecutive_failures` (red badge if ≥3) |
| Last Error | `last_run_error` (truncated) |
| Last Check | Relative time |
| Actions | View, Retry, Edit |

#### Actions

- **View**: Link to source in team context
- **Retry**: Trigger immediate source check
- **Edit**: Quick modal to update URL or reset failure count

#### Filters

- Status: All / Failing / Auto-Disabled
- Type: All / RSS / Website
- Search by source name or team name

---

### 9. Error Logs

**Route:** `/admin/system-health/errors`

#### Summary

- Total errors last 24h
- Total errors last 7 days
- Most common error type

#### Error Logs Table

| Column | Data |
|--------|------|
| Time | Timestamp |
| Level | Badge (Error=red, Warning=yellow) |
| Event Type | `activity_logs.event_type` |
| Team | Team name (link) |
| Description | Truncated message |
| Actions | View (expand metadata) |

#### Filters

- Level: All / Error / Warning
- Event Type: Dropdown of known types
- Team: Search/select
- Date Range: Last 24h / 7 days / 30 days

---

## Impersonation Feature

### Flow

1. Admin clicks "Impersonate" on user row
2. Store original admin ID in session (`impersonator_id`)
3. Log in as target user
4. Display banner: "Impersonating {user} - [Exit]"
5. Exit returns to admin session

### Audit Logging

- Log `admin.impersonation_started` with: admin_id, target_user_id, timestamp
- Log `admin.impersonation_ended` with: admin_id, target_user_id, duration

---

## Files to Create

### Backend

```
app/Http/Controllers/Admin/
├── DashboardController.php
├── UserController.php
├── TeamController.php
├── SystemHealth/
│   ├── OverviewController.php
│   ├── JobQueueController.php
│   ├── SourceHealthController.php
│   └── ErrorLogController.php
└── ImpersonationController.php

app/Http/Middleware/
└── EnsureUserIsAdmin.php

app/Services/
└── AdminDashboardService.php
```

### Frontend

```
resources/js/pages/Admin/
├── Dashboard.vue
├── Users/
│   ├── Index.vue
│   └── Edit.vue
├── Teams/
│   ├── Index.vue
│   └── Edit.vue
└── SystemHealth/
    ├── Index.vue
    ├── JobQueue.vue
    ├── SourceHealth.vue
    └── ErrorLogs.vue

resources/js/components/Admin/
├── StatCard.vue
├── HealthCard.vue
├── TokenUsageChart.vue
└── ImpersonationBanner.vue
```

---

## Future Enhancements (Not in Scope)

The following features were considered but deferred for future implementation:

- Alert/notification system for critical thresholds
- Dedicated Abuse Prevention pages with risk scoring
- Dedicated Growth Insights pages with cohort analysis
- Email notifications for admins
- Webhook alerting to external services
- User communication tools (in-app messages)
- Advanced search with saved filters
- Bulk user/team operations
- User session management (view/terminate individual sessions)
- Audit trail for all admin actions
