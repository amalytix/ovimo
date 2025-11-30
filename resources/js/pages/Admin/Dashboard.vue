<script setup lang="ts">
import HealthCard from '@/components/Admin/HealthCard.vue';
import StatCard from '@/components/Admin/StatCard.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
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

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Admin Dashboard</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Platform overview and system health.
                </p>
            </div>

            <!-- Platform Overview -->
            <div class="mb-8">
                <h2 class="mb-4 text-lg font-semibold">Platform Overview</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <StatCard
                        title="Total Users"
                        :value="platformOverview.total_users"
                        href="/admin/users"
                    />
                    <StatCard
                        title="Total Teams"
                        :value="platformOverview.total_teams"
                        href="/admin/teams"
                    />
                    <StatCard
                        title="New Signups (7d)"
                        :value="platformOverview.new_signups_7d"
                    />
                    <StatCard
                        title="Logins (7d)"
                        :value="platformOverview.logins_7d"
                    />
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
                    <StatCard
                        title="Tokens Today"
                        :value="formatNumber(usageStats.tokens_today)"
                    />
                    <StatCard
                        title="Tokens (7d)"
                        :value="formatNumber(usageStats.tokens_7d)"
                    />
                    <div
                        class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div
                            class="text-sm font-medium text-gray-500 dark:text-gray-400"
                        >
                            Source Checks
                        </div>
                        <div class="mt-2 text-xl font-bold">
                            Today:
                            {{ formatNumber(usageStats.source_checks_today) }} |
                            7d: {{ formatNumber(usageStats.source_checks_7d) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tables Row -->
            <div class="mb-8 grid grid-cols-1 gap-8 lg:grid-cols-2">
                <!-- Top Teams -->
                <div
                    class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                >
                    <h2 class="mb-4 text-lg font-semibold">
                        Top 5 Teams by Token Usage (7d)
                    </h2>
                    <div v-if="topTeams.length > 0" class="space-y-3">
                        <div
                            v-for="(team, index) in topTeams"
                            :key="team.id"
                            class="flex items-center justify-between"
                        >
                            <Link
                                :href="`/admin/teams/${team.id}/edit`"
                                class="hover:underline"
                            >
                                {{ index + 1 }}. {{ team.name }}
                            </Link>
                            <span class="text-gray-500 dark:text-gray-400">
                                {{ formatNumber(team.tokens_used) }} ({{
                                    team.percentage
                                }}%)
                            </span>
                        </div>
                    </div>
                    <div
                        v-else
                        class="text-sm text-gray-500 dark:text-gray-400"
                    >
                        No usage data yet.
                    </div>
                </div>

                <!-- Teams Approaching Limit -->
                <div
                    class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                >
                    <h2 class="mb-4 text-lg font-semibold">
                        Teams Approaching Limit (&gt;80%)
                    </h2>
                    <div
                        v-if="teamsApproachingLimit.length > 0"
                        class="space-y-3"
                    >
                        <div
                            v-for="team in teamsApproachingLimit"
                            :key="team.id"
                            class="flex items-center justify-between"
                        >
                            <Link
                                :href="`/admin/teams/${team.id}/edit`"
                                class="hover:underline"
                            >
                                {{ team.name }}
                            </Link>
                            <span
                                class="font-semibold"
                                :class="
                                    team.percentage >= 95
                                        ? 'text-red-500'
                                        : 'text-yellow-500'
                                "
                            >
                                {{ team.percentage }}%
                            </span>
                        </div>
                    </div>
                    <div
                        v-else
                        class="text-sm text-gray-500 dark:text-gray-400"
                    >
                        No teams approaching limit.
                    </div>
                </div>
            </div>

            <!-- Chart and Recent Registrations -->
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <!-- Token Usage Chart -->
                <div
                    class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                >
                    <h2 class="mb-4 text-lg font-semibold">
                        Token Usage (Last 28 Days)
                    </h2>
                    <div
                        v-if="dailyTokenUsage.length > 0"
                        class="overflow-x-auto"
                    >
                        <div
                            class="flex min-w-full items-end gap-1"
                            style="height: 150px"
                        >
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
                        <div
                            class="mt-2 flex justify-between text-xs text-gray-500"
                        >
                            <span>{{ dailyTokenUsage[0]?.date }}</span>
                            <span>{{
                                dailyTokenUsage[dailyTokenUsage.length - 1]
                                    ?.date
                            }}</span>
                        </div>
                    </div>
                    <div
                        v-else
                        class="text-sm text-gray-500 dark:text-gray-400"
                    >
                        No usage data yet.
                    </div>
                </div>

                <!-- Recent Registrations -->
                <div
                    class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                >
                    <h2 class="mb-4 text-lg font-semibold">
                        Recent Registrations
                    </h2>
                    <div
                        v-if="recentRegistrations.length > 0"
                        class="space-y-3"
                    >
                        <div
                            v-for="user in recentRegistrations"
                            :key="user.id"
                            class="flex items-center justify-between"
                        >
                            <Link
                                :href="`/admin/users/${user.id}/edit`"
                                class="hover:underline"
                            >
                                {{ user.email }}
                            </Link>
                            <span
                                class="text-sm text-gray-500 dark:text-gray-400"
                                >{{ user.created_at }}</span
                            >
                        </div>
                    </div>
                    <div
                        v-else
                        class="text-sm text-gray-500 dark:text-gray-400"
                    >
                        No recent registrations.
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
