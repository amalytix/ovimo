# Admin Interface Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build an admin interface for managing users, teams, and monitoring system health in the Ovimo multi-tenant SaaS.

**Architecture:** Add `is_admin` and `is_active` fields to users/teams, create admin middleware, build 9 Vue pages with corresponding controllers. Admin routes grouped under `/admin` prefix with middleware protection.

**Tech Stack:** Laravel 12, Inertia.js v2, Vue 3, Tailwind CSS v4, Pest v4

---

## Task 1: Database Migrations

**Files:**
- Create: `database/migrations/2024_11_30_000001_add_admin_fields_to_users_table.php`
- Create: `database/migrations/2024_11_30_000002_add_is_active_to_teams_table.php`

**Step 1: Create users migration**

Run:
```bash
php artisan make:migration add_admin_fields_to_users_table --table=users --no-interaction
```

**Step 2: Edit users migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('current_team_id');
            $table->boolean('is_active')->default(true)->after('is_admin');
        });

        // Make user ID 1 an admin
        DB::table('users')->where('id', 1)->update(['is_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'is_active']);
        });
    }
};
```

**Step 3: Create teams migration**

Run:
```bash
php artisan make:migration add_is_active_to_teams_table --table=teams --no-interaction
```

**Step 4: Edit teams migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('owner_id');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
```

**Step 5: Run migrations**

Run:
```bash
php artisan migrate
```

**Step 6: Commit**

```bash
git add database/migrations/
git commit -m "feat(admin): add is_admin, is_active fields to users and teams"
```

---

## Task 2: Update User and Team Models

**Files:**
- Modify: `app/Models/User.php`
- Modify: `app/Models/Team.php`

**Step 1: Update User model fillable and casts**

In `app/Models/User.php`, update the `$fillable` array:

```php
protected $fillable = [
    'name',
    'email',
    'password',
    'current_team_id',
    'monthly_token_limit',
    'is_admin',
    'is_active',
];
```

Update the `casts()` method:

```php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_confirmed_at' => 'datetime',
        'monthly_token_limit' => 'integer',
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
    ];
}
```

Add helper method:

```php
public function isAdmin(): bool
{
    return $this->is_admin === true;
}
```

**Step 2: Update Team model fillable and casts**

In `app/Models/Team.php`, update the `$fillable` array:

```php
protected $fillable = [
    'name',
    'owner_id',
    'is_active',
    'post_auto_hide_days',
    'monthly_token_limit',
    'relevancy_prompt',
    'positive_keywords',
    'negative_keywords',
];
```

Update the `casts()` method:

```php
protected function casts(): array
{
    return [
        'post_auto_hide_days' => 'integer',
        'monthly_token_limit' => 'integer',
        'is_active' => 'boolean',
    ];
}
```

**Step 3: Update UserFactory**

In `database/factories/UserFactory.php`, add to the `definition()` array:

```php
'is_admin' => false,
'is_active' => true,
```

Add state method:

```php
public function admin(): static
{
    return $this->state(fn (array $attributes) => [
        'is_admin' => true,
    ]);
}

public function inactive(): static
{
    return $this->state(fn (array $attributes) => [
        'is_active' => false,
    ]);
}
```

**Step 4: Update TeamFactory**

In `database/factories/TeamFactory.php`, add to the `definition()` array:

```php
'is_active' => true,
```

Add state method:

```php
public function inactive(): static
{
    return $this->state(fn (array $attributes) => [
        'is_active' => false,
    ]);
}
```

**Step 5: Commit**

```bash
git add app/Models/User.php app/Models/Team.php database/factories/
git commit -m "feat(admin): update User and Team models with admin/active fields"
```

---

## Task 3: Admin Middleware

**Files:**
- Create: `app/Http/Middleware/EnsureUserIsAdmin.php`
- Modify: `bootstrap/app.php`
- Create: `tests/Feature/Admin/AdminMiddlewareTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/Admin/AdminMiddlewareTest.php`:

```php
<?php

use App\Models\User;

test('non-admin users cannot access admin routes', function () {
    [$user, $team] = createUserWithTeam();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('admin users can access admin routes', function () {
    [$user, $team] = createUserWithTeam();
    $user->update(['is_admin' => true]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertSuccessful();
});

test('guests are redirected to login for admin routes', function () {
    $this->get('/admin')
        ->assertRedirect('/login');
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/AdminMiddlewareTest.php -v`

Expected: FAIL (routes don't exist yet)

**Step 3: Create middleware**

Run:
```bash
php artisan make:middleware EnsureUserIsAdmin --no-interaction
```

Edit `app/Http/Middleware/EnsureUserIsAdmin.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'Access denied. Admin privileges required.');
        }

        return $next($request);
    }
}
```

**Step 4: Register middleware alias in bootstrap/app.php**

Add to the `$middleware->alias()` array:

```php
$middleware->alias([
    'token.limit' => EnsureTokenLimitNotExceeded::class,
    'team.valid' => EnsureValidTeamMembership::class,
    'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
]);
```

**Step 5: Commit**

```bash
git add app/Http/Middleware/EnsureUserIsAdmin.php bootstrap/app.php tests/Feature/Admin/
git commit -m "feat(admin): add EnsureUserIsAdmin middleware"
```

---

## Task 4: Admin Routes and Dashboard Controller

**Files:**
- Create: `routes/admin.php`
- Modify: `bootstrap/app.php`
- Create: `app/Http/Controllers/Admin/DashboardController.php`
- Create: `app/Services/AdminDashboardService.php`

**Step 1: Create admin routes file**

Create `routes/admin.php`:

```php
<?php

use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
});
```

**Step 2: Register admin routes in bootstrap/app.php**

Update the `withRouting()` section:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    then: function () {
        Route::middleware('web')->group(base_path('routes/admin.php'));
    },
)
```

Add at the top of the file:

```php
use Illuminate\Support\Facades\Route;
```

**Step 3: Create AdminDashboardService**

Run:
```bash
php artisan make:class Services/AdminDashboardService --no-interaction
```

Edit `app/Services/AdminDashboardService.php`:

```php
<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Source;
use App\Models\Team;
use App\Models\TokenUsageLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    public function getPlatformOverview(): array
    {
        return [
            'total_users' => User::count(),
            'total_teams' => Team::count(),
            'new_signups_7d' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'logins_7d' => DB::table('sessions')
                ->where('last_activity', '>=', now()->subDays(7)->timestamp)
                ->distinct('user_id')
                ->count('user_id'),
        ];
    }

    public function getSystemHealth(): array
    {
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs24h = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDay())
            ->count();
        $totalSources = Source::where('is_active', true)->count();
        $failingSources = Source::where('consecutive_failures', '>', 0)->count();
        $errors24h = ActivityLog::where('level', 'error')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return [
            'pending_jobs' => $pendingJobs,
            'pending_jobs_status' => $this->getHealthStatus($pendingJobs, 100, 500),
            'failed_jobs_24h' => $failedJobs24h,
            'failed_jobs_status' => $this->getHealthStatus($failedJobs24h, 10, 50),
            'failing_sources' => $failingSources,
            'failing_sources_percentage' => $totalSources > 0 ? round(($failingSources / $totalSources) * 100, 1) : 0,
            'failing_sources_status' => $this->getHealthStatus(
                $totalSources > 0 ? ($failingSources / $totalSources) * 100 : 0,
                5,
                10
            ),
            'errors_24h' => $errors24h,
            'errors_status' => $this->getHealthStatus($errors24h, 50, 200),
        ];
    }

    public function getUsageStats(): array
    {
        $tokensToday = TokenUsageLog::where('created_at', '>=', now()->startOfDay())
            ->sum('total_tokens');
        $tokens7d = TokenUsageLog::where('created_at', '>=', now()->subDays(7))
            ->sum('total_tokens');
        $sourceChecksToday = ActivityLog::where('event_type', 'source.checked')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
        $sourceChecks7d = ActivityLog::where('event_type', 'source.checked')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            'tokens_today' => $tokensToday,
            'tokens_7d' => $tokens7d,
            'source_checks_today' => $sourceChecksToday,
            'source_checks_7d' => $sourceChecks7d,
        ];
    }

    public function getTopTeamsByTokenUsage(int $limit = 5): array
    {
        $totalTokens7d = TokenUsageLog::where('created_at', '>=', now()->subDays(7))
            ->sum('total_tokens');

        return Team::query()
            ->select('teams.id', 'teams.name')
            ->selectRaw('COALESCE(SUM(token_usage_logs.total_tokens), 0) as tokens_used')
            ->leftJoin('token_usage_logs', function ($join) {
                $join->on('teams.id', '=', 'token_usage_logs.team_id')
                    ->where('token_usage_logs.created_at', '>=', now()->subDays(7));
            })
            ->groupBy('teams.id', 'teams.name')
            ->orderByDesc('tokens_used')
            ->limit($limit)
            ->get()
            ->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'tokens_used' => (int) $team->tokens_used,
                'percentage' => $totalTokens7d > 0 ? round(($team->tokens_used / $totalTokens7d) * 100, 1) : 0,
            ])
            ->toArray();
    }

    public function getTeamsApproachingLimit(): array
    {
        return Team::query()
            ->select('teams.id', 'teams.name', 'teams.monthly_token_limit')
            ->selectRaw('COALESCE(SUM(token_usage_logs.total_tokens), 0) as tokens_used')
            ->leftJoin('token_usage_logs', function ($join) {
                $join->on('teams.id', '=', 'token_usage_logs.team_id')
                    ->where('token_usage_logs.created_at', '>=', now()->startOfMonth());
            })
            ->whereNotNull('teams.monthly_token_limit')
            ->groupBy('teams.id', 'teams.name', 'teams.monthly_token_limit')
            ->havingRaw('COALESCE(SUM(token_usage_logs.total_tokens), 0) >= teams.monthly_token_limit * 0.8')
            ->orderByDesc('tokens_used')
            ->get()
            ->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'percentage' => $team->monthly_token_limit > 0
                    ? round(($team->tokens_used / $team->monthly_token_limit) * 100, 0)
                    : 0,
            ])
            ->toArray();
    }

    public function getDailyTokenUsage(int $days = 28): array
    {
        return TokenUsageLog::query()
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('SUM(total_tokens) as tokens')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'tokens' => (int) $row->tokens,
            ])
            ->toArray();
    }

    public function getRecentRegistrations(int $limit = 5): array
    {
        return User::query()
            ->select('id', 'name', 'email', 'created_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'email' => $user->email,
                'created_at' => $user->created_at->diffForHumans(),
            ])
            ->toArray();
    }

    private function getHealthStatus(float $value, float $warningThreshold, float $criticalThreshold): string
    {
        if ($value >= $criticalThreshold) {
            return 'critical';
        }
        if ($value >= $warningThreshold) {
            return 'warning';
        }

        return 'healthy';
    }
}
```

**Step 4: Create DashboardController**

Run:
```bash
mkdir -p app/Http/Controllers/Admin
```

Create `app/Http/Controllers/Admin/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private AdminDashboardService $dashboardService
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'platformOverview' => $this->dashboardService->getPlatformOverview(),
            'systemHealth' => $this->dashboardService->getSystemHealth(),
            'usageStats' => $this->dashboardService->getUsageStats(),
            'topTeams' => $this->dashboardService->getTopTeamsByTokenUsage(),
            'teamsApproachingLimit' => $this->dashboardService->getTeamsApproachingLimit(),
            'dailyTokenUsage' => $this->dashboardService->getDailyTokenUsage(),
            'recentRegistrations' => $this->dashboardService->getRecentRegistrations(),
        ]);
    }
}
```

**Step 5: Run tests**

Run: `php artisan test tests/Feature/Admin/AdminMiddlewareTest.php -v`

Expected: 2 passing, 1 may fail (needs Vue page)

**Step 6: Commit**

```bash
git add routes/admin.php bootstrap/app.php app/Http/Controllers/Admin/ app/Services/AdminDashboardService.php
git commit -m "feat(admin): add admin routes and dashboard controller"
```

---

## Task 5: Admin Dashboard Vue Page

**Files:**
- Create: `resources/js/pages/Admin/Dashboard.vue`
- Create: `resources/js/components/Admin/StatCard.vue`
- Create: `resources/js/components/Admin/HealthCard.vue`

**Step 1: Create StatCard component**

Create `resources/js/components/Admin/StatCard.vue`:

```vue
<script setup lang="ts">
interface Props {
    title: string;
    value: string | number;
    href?: string;
}

defineProps<Props>();

const formatNumber = (num: number | string) => {
    if (typeof num === 'string') return num;
    return new Intl.NumberFormat().format(num);
};
</script>

<template>
    <component
        :is="href ? 'a' : 'div'"
        :href="href"
        class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
        :class="{ 'transition-colors hover:bg-gray-50 dark:hover:bg-gray-700': href }"
    >
        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ title }}</div>
        <div class="mt-2 text-3xl font-bold">{{ formatNumber(value) }}</div>
    </component>
</template>
```

**Step 2: Create HealthCard component**

Create `resources/js/components/Admin/HealthCard.vue`:

```vue
<script setup lang="ts">
interface Props {
    title: string;
    value: string | number;
    status: 'healthy' | 'warning' | 'critical';
    href?: string;
}

defineProps<Props>();

const formatNumber = (num: number | string) => {
    if (typeof num === 'string') return num;
    return new Intl.NumberFormat().format(num);
};

const statusColors = {
    healthy: 'bg-green-500',
    warning: 'bg-yellow-500',
    critical: 'bg-red-500',
};
</script>

<template>
    <component
        :is="href ? 'a' : 'div'"
        :href="href"
        class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
        :class="{ 'transition-colors hover:bg-gray-50 dark:hover:bg-gray-700': href }"
    >
        <div class="flex items-center justify-between">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ title }}</div>
            <span class="inline-block h-3 w-3 rounded-full" :class="statusColors[status]"></span>
        </div>
        <div class="mt-2 text-3xl font-bold">{{ formatNumber(value) }}</div>
    </component>
</template>
```

**Step 3: Create Admin Dashboard page**

Create `resources/js/pages/Admin/Dashboard.vue`:

```vue
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import HealthCard from '@/components/Admin/HealthCard.vue';
import StatCard from '@/components/Admin/StatCard.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

interface Props {
    platformOverview: {
        total_users: number;
        total_teams: number;
        new_signups_7d: number;
        logins_7d: number;
    };
    systemHealth: {
        pending_jobs: number;
        pending_jobs_status: 'healthy' | 'warning' | 'critical';
        failed_jobs_24h: number;
        failed_jobs_status: 'healthy' | 'warning' | 'critical';
        failing_sources: number;
        failing_sources_status: 'healthy' | 'warning' | 'critical';
        errors_24h: number;
        errors_status: 'healthy' | 'warning' | 'critical';
    };
    usageStats: {
        tokens_today: number;
        tokens_7d: number;
        source_checks_today: number;
        source_checks_7d: number;
    };
    topTeams: Array<{
        id: number;
        name: string;
        tokens_used: number;
        percentage: number;
    }>;
    teamsApproachingLimit: Array<{
        id: number;
        name: string;
        percentage: number;
    }>;
    dailyTokenUsage: Array<{
        date: string;
        tokens: number;
    }>;
    recentRegistrations: Array<{
        id: number;
        email: string;
        created_at: string;
    }>;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin', href: '/admin' }];

const formatNumber = (num: number) => {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return new Intl.NumberFormat().format(num);
};
</script>

<template>
    <Head title="Admin Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Admin Dashboard</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Platform overview and system health.</p>
            </div>

            <!-- Platform Overview -->
            <div class="mb-8">
                <h2 class="mb-4 text-lg font-semibold">Platform Overview</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <StatCard title="Total Users" :value="platformOverview.total_users" href="/admin/users" />
                    <StatCard title="Total Teams" :value="platformOverview.total_teams" href="/admin/teams" />
                    <StatCard title="New Signups (7d)" :value="platformOverview.new_signups_7d" />
                    <StatCard title="Logins (7d)" :value="platformOverview.logins_7d" />
                </div>
            </div>

            <!-- System Health -->
            <div class="mb-8">
                <h2 class="mb-4 text-lg font-semibold">System Health</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <HealthCard
                        title="Pending Jobs"
                        :value="systemHealth.pending_jobs"
                        :status="systemHealth.pending_jobs_status"
                        href="/admin/system-health/jobs"
                    />
                    <HealthCard
                        title="Failed Jobs (24h)"
                        :value="systemHealth.failed_jobs_24h"
                        :status="systemHealth.failed_jobs_status"
                        href="/admin/system-health/jobs"
                    />
                    <HealthCard
                        title="Failing Sources"
                        :value="systemHealth.failing_sources"
                        :status="systemHealth.failing_sources_status"
                        href="/admin/system-health/sources"
                    />
                    <HealthCard
                        title="Errors (24h)"
                        :value="systemHealth.errors_24h"
                        :status="systemHealth.errors_status"
                        href="/admin/system-health/errors"
                    />
                </div>
            </div>

            <!-- Usage Stats -->
            <div class="mb-8">
                <h2 class="mb-4 text-lg font-semibold">Usage</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <StatCard title="Tokens Today" :value="formatNumber(usageStats.tokens_today)" />
                    <StatCard title="Tokens (7d)" :value="formatNumber(usageStats.tokens_7d)" />
                    <div class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Source Checks</div>
                        <div class="mt-2 text-xl font-bold">
                            Today: {{ formatNumber(usageStats.source_checks_today) }} |
                            7d: {{ formatNumber(usageStats.source_checks_7d) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tables Row -->
            <div class="mb-8 grid grid-cols-1 gap-8 lg:grid-cols-2">
                <!-- Top Teams -->
                <div class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="mb-4 text-lg font-semibold">Top 5 Teams by Token Usage (7d)</h2>
                    <div v-if="topTeams.length > 0" class="space-y-3">
                        <div v-for="(team, index) in topTeams" :key="team.id" class="flex items-center justify-between">
                            <Link :href="`/admin/teams/${team.id}/edit`" class="hover:underline">
                                {{ index + 1 }}. {{ team.name }}
                            </Link>
                            <span class="text-gray-500 dark:text-gray-400">
                                {{ formatNumber(team.tokens_used) }} ({{ team.percentage }}%)
                            </span>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-500 dark:text-gray-400">No usage data yet.</div>
                </div>

                <!-- Teams Approaching Limit -->
                <div class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="mb-4 text-lg font-semibold">Teams Approaching Limit (&gt;80%)</h2>
                    <div v-if="teamsApproachingLimit.length > 0" class="space-y-3">
                        <div v-for="team in teamsApproachingLimit" :key="team.id" class="flex items-center justify-between">
                            <Link :href="`/admin/teams/${team.id}/edit`" class="hover:underline">
                                {{ team.name }}
                            </Link>
                            <span class="font-semibold" :class="team.percentage >= 95 ? 'text-red-500' : 'text-yellow-500'">
                                {{ team.percentage }}%
                            </span>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-500 dark:text-gray-400">No teams approaching limit.</div>
                </div>
            </div>

            <!-- Chart and Recent Registrations -->
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <!-- Token Usage Chart -->
                <div class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="mb-4 text-lg font-semibold">Token Usage (Last 28 Days)</h2>
                    <div v-if="dailyTokenUsage.length > 0" class="overflow-x-auto">
                        <div class="flex min-w-full items-end gap-1" style="height: 150px">
                            <div
                                v-for="day in dailyTokenUsage"
                                :key="day.date"
                                class="relative flex flex-1 flex-col items-center"
                                :title="`${day.date}: ${formatNumber(day.tokens)} tokens`"
                            >
                                <div
                                    class="w-full rounded-t bg-blue-500 transition-all hover:bg-blue-600"
                                    :style="{
                                        height: `${Math.max(4, (day.tokens / Math.max(...dailyTokenUsage.map((d) => d.tokens))) * 140)}px`,
                                    }"
                                ></div>
                            </div>
                        </div>
                        <div class="mt-2 flex justify-between text-xs text-gray-500">
                            <span>{{ dailyTokenUsage[0]?.date }}</span>
                            <span>{{ dailyTokenUsage[dailyTokenUsage.length - 1]?.date }}</span>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-500 dark:text-gray-400">No usage data yet.</div>
                </div>

                <!-- Recent Registrations -->
                <div class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="mb-4 text-lg font-semibold">Recent Registrations</h2>
                    <div v-if="recentRegistrations.length > 0" class="space-y-3">
                        <div v-for="user in recentRegistrations" :key="user.id" class="flex items-center justify-between">
                            <Link :href="`/admin/users/${user.id}/edit`" class="hover:underline">
                                {{ user.email }}
                            </Link>
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ user.created_at }}</span>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-500 dark:text-gray-400">No recent registrations.</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
```

**Step 4: Build frontend**

Run:
```bash
npm run build
```

**Step 5: Run all tests**

Run: `php artisan test tests/Feature/Admin/AdminMiddlewareTest.php -v`

Expected: All tests pass

**Step 6: Commit**

```bash
git add resources/js/pages/Admin/ resources/js/components/Admin/
git commit -m "feat(admin): add dashboard Vue page with stat and health cards"
```

---

## Task 6: Add Admin Link to Sidebar

**Files:**
- Modify: `resources/js/components/AppSidebar.vue`

**Step 1: Update AppSidebar to include admin link for admin users**

Update the props interface and add computed admin check:

```vue
<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Activity, BarChart3, FileText, Image, LayoutGrid, MessageSquare, PenTool, Rss, Settings, Shield } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();

const isAdmin = computed(() => page.props.auth?.user?.is_admin === true);

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Sources',
        href: '/sources',
        icon: Rss,
    },
    {
        title: 'Posts',
        href: '/posts',
        icon: FileText,
    },
    {
        title: 'Prompts',
        href: '/prompts',
        icon: MessageSquare,
    },
    {
        title: 'Content',
        href: '/content-pieces',
        icon: PenTool,
    },
    {
        title: 'Media',
        href: '/media',
        icon: Image,
    },
    {
        title: 'Settings',
        href: '/team-settings',
        icon: Settings,
    },
];

const footerNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Logs',
            href: '/activity-logs',
            icon: Activity,
        },
        {
            title: 'Usage',
            href: '/usage',
            icon: BarChart3,
        },
    ];

    if (isAdmin.value) {
        items.push({
            title: 'Admin',
            href: '/admin',
            icon: Shield,
        });
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
```

**Step 2: Update HandleInertiaRequests to share is_admin**

Check and update `app/Http/Middleware/HandleInertiaRequests.php` to include `is_admin` in shared user data.

Find the `share()` method and ensure the user object includes `is_admin`:

```php
'auth' => [
    'user' => $request->user() ? [
        'id' => $request->user()->id,
        'name' => $request->user()->name,
        'email' => $request->user()->email,
        'is_admin' => $request->user()->is_admin,
        // ... other fields
    ] : null,
],
```

**Step 3: Build and test**

Run:
```bash
npm run build
```

**Step 4: Commit**

```bash
git add resources/js/components/AppSidebar.vue app/Http/Middleware/HandleInertiaRequests.php
git commit -m "feat(admin): add admin link to sidebar for admin users"
```

---

## Task 7: Users List Page

**Files:**
- Create: `app/Http/Controllers/Admin/UserController.php`
- Create: `resources/js/pages/Admin/Users/Index.vue`
- Create: `tests/Feature/Admin/UserManagementTest.php`
- Modify: `routes/admin.php`

**Step 1: Write failing tests**

Create `tests/Feature/Admin/UserManagementTest.php`:

```php
<?php

use App\Models\Source;
use App\Models\User;

test('admin can view users list', function () {
    [$admin, $team] = createUserWithTeam();
    $admin->update(['is_admin' => true]);

    User::factory()->count(5)->create();

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Index')
            ->has('users.data', 6) // 5 + admin
        );
});

test('users list shows correct stats', function () {
    [$admin, $team] = createUserWithTeam();
    $admin->update(['is_admin' => true]);

    [$user, $userTeam] = createUserWithTeam();
    Source::factory()->count(3)->create(['team_id' => $userTeam->id]);

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Index')
            ->where('users.data.0.sources_count', 3)
        );
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/UserManagementTest.php -v`

Expected: FAIL

**Step 3: Create UserController**

Create `app/Http/Controllers/Admin/UserController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::query()
            ->select('users.*')
            ->selectSub(
                fn ($q) => $q->from('team_user')->whereColumn('team_user.user_id', 'users.id')->selectRaw('count(*)'),
                'teams_count'
            )
            ->selectSub(
                fn ($q) => $q->from('sources')
                    ->join('team_user', 'sources.team_id', '=', 'team_user.team_id')
                    ->whereColumn('team_user.user_id', 'users.id')
                    ->selectRaw('count(*)'),
                'sources_count'
            )
            ->selectSub(
                fn ($q) => $q->from('posts')
                    ->join('sources', 'posts.source_id', '=', 'sources.id')
                    ->join('team_user', 'sources.team_id', '=', 'team_user.team_id')
                    ->whereColumn('team_user.user_id', 'users.id')
                    ->selectRaw('count(*)'),
                'posts_count'
            )
            ->selectSub(
                fn ($q) => $q->from('token_usage_logs')
                    ->whereColumn('token_usage_logs.user_id', 'users.id')
                    ->where('token_usage_logs.created_at', '>=', now()->subDays(7))
                    ->selectRaw('COALESCE(sum(total_tokens), 0)'),
                'tokens_7d'
            )
            ->selectSub(
                fn ($q) => $q->from('sessions')
                    ->whereColumn('sessions.user_id', 'users.id')
                    ->selectRaw('MAX(last_activity)'),
                'last_login_at'
            );

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filters
        if ($request->input('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('status') === 'inactive') {
            $query->where('is_active', false);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'last_login_at');
        $sortDir = $request->input('sort_dir', 'desc');

        match ($sortBy) {
            'email' => $query->orderBy('email', $sortDir),
            'name' => $query->orderBy('name', $sortDir),
            'sources_count' => $query->orderBy('sources_count', $sortDir),
            'posts_count' => $query->orderBy('posts_count', $sortDir),
            'tokens_7d' => $query->orderBy('tokens_7d', $sortDir),
            default => $query->orderByRaw("last_login_at IS NULL, last_login_at {$sortDir}"),
        };

        return Inertia::render('Admin/Users/Index', [
            'users' => $query->paginate(20)->withQueryString()->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'is_admin' => $user->is_admin,
                'teams_count' => (int) $user->teams_count,
                'sources_count' => (int) $user->sources_count,
                'posts_count' => (int) $user->posts_count,
                'tokens_7d' => (int) $user->tokens_7d,
                'last_login_at' => $user->last_login_at
                    ? now()->setTimestamp($user->last_login_at)->diffForHumans()
                    : 'Never',
            ]),
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }
}
```

**Step 4: Add routes**

Update `routes/admin.php`:

```php
<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
});
```

**Step 5: Create Vue page**

Create `resources/js/pages/Admin/Users/Index.vue`:

```vue
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, UserCheck } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface User {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    is_admin: boolean;
    teams_count: number;
    sources_count: number;
    posts_count: number;
    tokens_7d: number;
    last_login_at: string;
}

interface Props {
    users: {
        data: User[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search: string;
        status: string;
        sort_by: string;
        sort_dir: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Users', href: '/admin/users' },
];

const search = ref(props.filters.search);
const status = ref(props.filters.status);

const formatNumber = (num: number) => new Intl.NumberFormat().format(num);

const applyFilters = () => {
    router.get('/admin/users', {
        search: search.value || undefined,
        status: status.value || undefined,
        sort_by: props.filters.sort_by,
        sort_dir: props.filters.sort_dir,
    }, { preserveState: true });
};

const sort = (column: string) => {
    const newDir = props.filters.sort_by === column && props.filters.sort_dir === 'asc' ? 'desc' : 'asc';
    router.get('/admin/users', {
        search: search.value || undefined,
        status: status.value || undefined,
        sort_by: column,
        sort_dir: newDir,
    }, { preserveState: true });
};

watch([search], () => {
    const timeout = setTimeout(applyFilters, 300);
    return () => clearTimeout(timeout);
});
</script>

<template>
    <Head title="Users - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Users</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage all users on the platform.</p>
            </div>

            <!-- Filters -->
            <div class="mb-6 flex flex-wrap items-center gap-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name or email..."
                    class="rounded-lg border px-4 py-2 dark:border-gray-700 dark:bg-gray-800"
                />
                <select
                    v-model="status"
                    @change="applyFilters"
                    class="rounded-lg border px-4 py-2 dark:border-gray-700 dark:bg-gray-800"
                >
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-lg border dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                @click="sort('email')"
                            >
                                Email
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                @click="sort('name')"
                            >
                                Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Status
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                @click="sort('last_login_at')"
                            >
                                Last Login
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Teams
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                @click="sort('sources_count')"
                            >
                                Sources
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                @click="sort('posts_count')"
                            >
                                Posts
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                @click="sort('tokens_7d')"
                            >
                                Tokens (7d)
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        <tr v-for="user in users.data" :key="user.id">
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                {{ user.email }}
                                <span v-if="user.is_admin" class="ml-2 rounded bg-purple-100 px-2 py-0.5 text-xs text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                    Admin
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ user.name }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span
                                    class="inline-flex rounded-full px-2 text-xs font-semibold leading-5"
                                    :class="user.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'"
                                >
                                    {{ user.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ user.last_login_at }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ user.teams_count }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ formatNumber(user.sources_count) }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ formatNumber(user.posts_count) }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-500 dark:text-gray-400">
                                {{ formatNumber(user.tokens_7d) }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                <div class="flex items-center justify-end gap-2">
                                    <Link
                                        :href="`/admin/users/${user.id}/edit`"
                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400"
                                        title="Edit"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                    <a
                                        :href="`/admin/impersonate/${user.id}`"
                                        class="text-gray-600 hover:text-gray-900 dark:text-gray-400"
                                        title="Impersonate"
                                    >
                                        <UserCheck class="h-4 w-4" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="users.links.length > 3" class="mt-4 flex justify-center gap-1">
                <template v-for="link in users.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded px-3 py-1 text-sm"
                        :class="link.active ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-800'"
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="rounded bg-gray-50 px-3 py-1 text-sm text-gray-400 dark:bg-gray-900"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
```

**Step 6: Build and run tests**

Run:
```bash
npm run build
php artisan test tests/Feature/Admin/UserManagementTest.php -v
```

Expected: Tests pass

**Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/UserController.php resources/js/pages/Admin/Users/ routes/admin.php tests/Feature/Admin/UserManagementTest.php
git commit -m "feat(admin): add users list page with search, filters, and sorting"
```

---

## Task 8: User Edit Page

**Files:**
- Modify: `app/Http/Controllers/Admin/UserController.php`
- Create: `app/Http/Requests/Admin/UpdateUserRequest.php`
- Create: `resources/js/pages/Admin/Users/Edit.vue`
- Modify: `routes/admin.php`

**Step 1: Add test cases**

Add to `tests/Feature/Admin/UserManagementTest.php`:

```php
test('admin can view user edit page', function () {
    [$admin, $team] = createUserWithTeam();
    $admin->update(['is_admin' => true]);

    [$user, $userTeam] = createUserWithTeam();

    $this->actingAs($admin)
        ->get("/admin/users/{$user->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Edit')
            ->has('user')
            ->where('user.id', $user->id)
        );
});

test('admin can update user', function () {
    [$admin, $team] = createUserWithTeam();
    $admin->update(['is_admin' => true]);

    [$user, $userTeam] = createUserWithTeam();

    $this->actingAs($admin)
        ->put("/admin/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => $user->email,
            'is_active' => false,
            'is_admin' => false,
        ])
        ->assertRedirect('/admin/users');

    expect($user->fresh())
        ->name->toBe('Updated Name')
        ->is_active->toBeFalse();
});

test('admin can toggle user active status', function () {
    [$admin, $team] = createUserWithTeam();
    $admin->update(['is_admin' => true]);

    [$user, $userTeam] = createUserWithTeam();
    expect($user->is_active)->toBeTrue();

    $this->actingAs($admin)
        ->put("/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => false,
            'is_admin' => false,
        ])
        ->assertRedirect();

    expect($user->fresh()->is_active)->toBeFalse();
});
```

**Step 2: Create form request**

Run:
```bash
php artisan make:request Admin/UpdateUserRequest --no-interaction
```

Edit `app/Http/Requests/Admin/UpdateUserRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'is_active' => ['required', 'boolean'],
            'is_admin' => ['required', 'boolean'],
        ];
    }
}
```

**Step 3: Add edit and update methods to UserController**

Add to `app/Http/Controllers/Admin/UserController.php`:

```php
use App\Http\Requests\Admin\UpdateUserRequest;
use Illuminate\Http\RedirectResponse;

public function edit(User $user): Response
{
    $user->load(['teams' => function ($query) {
        $query->withCount('sources', 'users');
    }]);

    $lastLogin = DB::table('sessions')
        ->where('user_id', $user->id)
        ->max('last_activity');

    $tokenUsageThisMonth = $user->tokenUsageLogs()
        ->where('created_at', '>=', now()->startOfMonth())
        ->sum('total_tokens');

    $recentActivity = DB::table('activity_logs')
        ->where('user_id', $user->id)
        ->orderByDesc('created_at')
        ->limit(10)
        ->get(['event_type', 'description', 'created_at'])
        ->map(fn ($log) => [
            'event_type' => $log->event_type,
            'description' => $log->description,
            'created_at' => \Carbon\Carbon::parse($log->created_at)->diffForHumans(),
        ]);

    return Inertia::render('Admin/Users/Edit', [
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'is_admin' => $user->is_admin,
            'email_verified_at' => $user->email_verified_at?->toDateTimeString(),
            'created_at' => $user->created_at->toDateTimeString(),
            'two_factor_enabled' => ! is_null($user->two_factor_confirmed_at),
        ],
        'stats' => [
            'last_login' => $lastLogin ? now()->setTimestamp($lastLogin)->diffForHumans() : 'Never',
            'token_usage_this_month' => $tokenUsageThisMonth,
            'token_limit' => $user->monthly_token_limit,
        ],
        'teams' => $user->teams->map(fn ($team) => [
            'id' => $team->id,
            'name' => $team->name,
            'role' => $team->pivot->role,
            'sources_count' => $team->sources_count,
            'users_count' => $team->users_count,
        ]),
        'recentActivity' => $recentActivity,
    ]);
}

public function update(UpdateUserRequest $request, User $user): RedirectResponse
{
    $user->update($request->validated());

    return redirect()->route('admin.users.index')
        ->with('success', 'User updated successfully.');
}
```

**Step 4: Add routes**

Update `routes/admin.php`:

```php
Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
```

**Step 5: Create Edit Vue page**

Create `resources/js/pages/Admin/Users/Edit.vue`:

```vue
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface Props {
    user: {
        id: number;
        name: string;
        email: string;
        is_active: boolean;
        is_admin: boolean;
        email_verified_at: string | null;
        created_at: string;
        two_factor_enabled: boolean;
    };
    stats: {
        last_login: string;
        token_usage_this_month: number;
        token_limit: number | null;
    };
    teams: Array<{
        id: number;
        name: string;
        role: string;
        sources_count: number;
        users_count: number;
    }>;
    recentActivity: Array<{
        event_type: string;
        description: string;
        created_at: string;
    }>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Users', href: '/admin/users' },
    { title: props.user.email, href: `/admin/users/${props.user.id}/edit` },
];

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    is_active: props.user.is_active,
    is_admin: props.user.is_admin,
});

const submit = () => {
    form.put(`/admin/users/${props.user.id}`);
};

const formatNumber = (num: number) => new Intl.NumberFormat().format(num);
</script>

<template>
    <Head :title="`Edit User: ${user.email}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Edit User</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ user.email }}</p>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                <!-- Main Form -->
                <div class="lg:col-span-2">
                    <form @submit.prevent="submit" class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-4 text-lg font-semibold">User Information</h2>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="mt-1 block w-full rounded-lg border px-4 py-2 dark:border-gray-600 dark:bg-gray-700"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">{{ form.errors.name }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    class="mt-1 block w-full rounded-lg border px-4 py-2 dark:border-gray-600 dark:bg-gray-700"
                                />
                                <p v-if="form.errors.email" class="mt-1 text-sm text-red-500">{{ form.errors.email }}</p>
                            </div>

                            <div class="flex items-center gap-6">
                                <label class="flex items-center gap-2">
                                    <input v-model="form.is_active" type="checkbox" class="rounded" />
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Active</span>
                                </label>

                                <label class="flex items-center gap-2">
                                    <input v-model="form.is_admin" type="checkbox" class="rounded" />
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Admin</span>
                                </label>
                            </div>

                            <div class="flex items-center gap-4 pt-4">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                                >
                                    Save Changes
                                </button>
                                <Link href="/admin/users" class="text-gray-600 hover:underline dark:text-gray-400">
                                    Cancel
                                </Link>
                            </div>
                        </div>
                    </form>

                    <!-- Team Memberships -->
                    <div class="mt-8 rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-4 text-lg font-semibold">Team Memberships</h2>
                        <div v-if="teams.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Team</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Role</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium uppercase text-gray-500">Sources</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium uppercase text-gray-500">Members</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="team in teams" :key="team.id">
                                        <td class="px-4 py-2 text-sm">{{ team.name }}</td>
                                        <td class="px-4 py-2 text-sm capitalize">{{ team.role }}</td>
                                        <td class="px-4 py-2 text-center text-sm">{{ team.sources_count }}</td>
                                        <td class="px-4 py-2 text-center text-sm">{{ team.users_count }}</td>
                                        <td class="px-4 py-2 text-right text-sm">
                                            <Link :href="`/admin/teams/${team.id}/edit`" class="text-blue-600 hover:underline">
                                                View
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="text-sm text-gray-500 dark:text-gray-400">No team memberships.</p>
                    </div>

                    <!-- Recent Activity -->
                    <div class="mt-8 rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-4 text-lg font-semibold">Recent Activity</h2>
                        <div v-if="recentActivity.length > 0" class="space-y-3">
                            <div v-for="(activity, index) in recentActivity" :key="index" class="flex items-start gap-3 text-sm">
                                <span class="text-gray-400">{{ activity.created_at }}</span>
                                <span class="font-medium">{{ activity.event_type }}</span>
                                <span class="text-gray-600 dark:text-gray-400">{{ activity.description }}</span>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-500 dark:text-gray-400">No recent activity.</p>
                    </div>
                </div>

                <!-- Sidebar Stats -->
                <div class="space-y-6">
                    <div class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-4 text-lg font-semibold">Account Stats</h2>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Created</dt>
                                <dd class="font-medium">{{ user.created_at }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Last Login</dt>
                                <dd class="font-medium">{{ stats.last_login }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Email Verified</dt>
                                <dd class="font-medium">{{ user.email_verified_at ? 'Yes' : 'No' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm text-gray-500 dark:text-gray-400">2FA Enabled</dt>
                                <dd class="font-medium">{{ user.two_factor_enabled ? 'Yes' : 'No' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Token Usage (This Month)</dt>
                                <dd class="font-medium">
                                    {{ formatNumber(stats.token_usage_this_month) }}
                                    <span v-if="stats.token_limit" class="text-gray-500">
                                        / {{ formatNumber(stats.token_limit) }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-4 text-lg font-semibold">Quick Actions</h2>
                        <div class="space-y-2">
                            <a
                                :href="`/admin/impersonate/${user.id}`"
                                class="block w-full rounded-lg border px-4 py-2 text-center hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700"
                            >
                                Impersonate User
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
```

**Step 6: Build and run tests**

Run:
```bash
npm run build
php artisan test tests/Feature/Admin/UserManagementTest.php -v
```

Expected: Tests pass

**Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/UserController.php app/Http/Requests/Admin/ resources/js/pages/Admin/Users/Edit.vue routes/admin.php tests/Feature/Admin/UserManagementTest.php
git commit -m "feat(admin): add user edit page with form and stats"
```

---

## Remaining Tasks (Summary)

The following tasks follow the same pattern. I'll provide brief outlines:

### Task 9: Teams List Page
- Create `app/Http/Controllers/Admin/TeamController.php` with `index()` method
- Create `resources/js/pages/Admin/Teams/Index.vue`
- Add routes and tests

### Task 10: Team Edit Page
- Add `edit()` and `update()` methods to TeamController
- Create `app/Http/Requests/Admin/UpdateTeamRequest.php`
- Create `resources/js/pages/Admin/Teams/Edit.vue`

### Task 11: System Health Overview Page
- Create `app/Http/Controllers/Admin/SystemHealth/OverviewController.php`
- Create `resources/js/pages/Admin/SystemHealth/Index.vue`

### Task 12: Job Queue Page
- Create `app/Http/Controllers/Admin/SystemHealth/JobQueueController.php`
- Create `resources/js/pages/Admin/SystemHealth/JobQueue.vue`
- Add retry/delete actions for failed jobs

### Task 13: Source Health Page
- Create `app/Http/Controllers/Admin/SystemHealth/SourceHealthController.php`
- Create `resources/js/pages/Admin/SystemHealth/SourceHealth.vue`

### Task 14: Error Logs Page
- Create `app/Http/Controllers/Admin/SystemHealth/ErrorLogController.php`
- Create `resources/js/pages/Admin/SystemHealth/ErrorLogs.vue`

### Task 15: Impersonation Feature
- Create `app/Http/Controllers/Admin/ImpersonationController.php`
- Create `resources/js/components/Admin/ImpersonationBanner.vue`
- Add session handling and audit logging

### Task 16: Inactive User/Team Enforcement
- Update login logic to check `is_active`
- Update `MonitorSource` job to check team `is_active`
- Update token-consuming operations

### Task 17: Final Integration & Testing
- Run full test suite
- Manual testing of all admin features
- Code review and cleanup

---

## Verification Checklist

After completing all tasks:

- [ ] All tests pass: `php artisan test`
- [ ] Pint formatting: `vendor/bin/pint --dirty`
- [ ] Frontend builds: `npm run build`
- [ ] Admin can access dashboard at `/admin`
- [ ] Admin sidebar shows "Admin" link
- [ ] Users list shows all users with correct stats
- [ ] User edit allows changing active/admin status
- [ ] Teams list shows all teams with token usage
- [ ] Team edit allows changing active status
- [ ] System health shows job queue, sources, errors
- [ ] Impersonation works with banner and exit
- [ ] Inactive users cannot log in
- [ ] Inactive teams cannot check sources
