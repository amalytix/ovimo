<script setup lang="ts">
import HealthCard from '@/components/Admin/HealthCard.vue';
import StatCard from '@/components/Admin/StatCard.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

interface Props {
    overview: {
        pending_jobs: number;
        failed_jobs_24h: number;
        total_failed_jobs: number;
        failing_sources: number;
        total_sources: number;
        active_sources: number;
        errors_24h: number;
    };
    jobsByQueue: Array<{
        queue: string;
        count: number;
    }>;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'System Health', href: '/admin/system' },
];

const getJobsStatus = (pending: number): 'healthy' | 'warning' | 'critical' => {
    if (pending > 1000) return 'critical';
    if (pending > 100) return 'warning';
    return 'healthy';
};

const getFailedJobsStatus = (
    failed: number,
): 'healthy' | 'warning' | 'critical' => {
    if (failed > 50) return 'critical';
    if (failed > 10) return 'warning';
    return 'healthy';
};

const getSourcesStatus = (
    failing: number,
): 'healthy' | 'warning' | 'critical' => {
    if (failing > 10) return 'critical';
    if (failing > 0) return 'warning';
    return 'healthy';
};

const getErrorsStatus = (
    errors: number,
): 'healthy' | 'warning' | 'critical' => {
    if (errors > 100) return 'critical';
    if (errors > 10) return 'warning';
    return 'healthy';
};
</script>

<template>
    <Head title="System Health - Admin" />

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">System Health</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Overview of system status and health metrics.
                </p>
            </div>

            <!-- Health Cards -->
            <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-4">
                <HealthCard
                    title="Pending Jobs"
                    :value="overview.pending_jobs"
                    :status="getJobsStatus(overview.pending_jobs)"
                    href="/admin/jobs"
                />
                <HealthCard
                    title="Failed Jobs (24h)"
                    :value="overview.failed_jobs_24h"
                    :status="getFailedJobsStatus(overview.failed_jobs_24h)"
                    href="/admin/jobs"
                />
                <HealthCard
                    title="Failing Sources"
                    :value="overview.failing_sources"
                    :status="getSourcesStatus(overview.failing_sources)"
                    href="/admin/sources"
                />
                <HealthCard
                    title="Errors (24h)"
                    :value="overview.errors_24h"
                    :status="getErrorsStatus(overview.errors_24h)"
                    href="/admin/errors"
                />
            </div>

            <!-- Stats -->
            <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-3">
                <StatCard
                    title="Total Sources"
                    :value="overview.total_sources"
                    href="/admin/sources?status=all"
                />
                <StatCard
                    title="Active Sources"
                    :value="overview.active_sources"
                    href="/admin/sources?status=healthy"
                />
                <StatCard
                    title="Total Failed Jobs"
                    :value="overview.total_failed_jobs"
                    href="/admin/jobs"
                />
            </div>

            <!-- Jobs by Queue -->
            <div
                class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
            >
                <h2 class="mb-4 text-lg font-semibold">Jobs by Queue</h2>
                <div v-if="jobsByQueue.length > 0" class="space-y-3">
                    <div
                        v-for="queue in jobsByQueue"
                        :key="queue.queue"
                        class="flex items-center justify-between"
                    >
                        <Link
                            :href="`/admin/jobs?queue=${queue.queue}`"
                            class="font-medium hover:underline"
                        >
                            {{ queue.queue }}
                        </Link>
                        <span
                            class="rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                        >
                            {{ queue.count }}
                        </span>
                    </div>
                </div>
                <div v-else class="text-sm text-gray-500 dark:text-gray-400">
                    No pending jobs in any queue.
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
