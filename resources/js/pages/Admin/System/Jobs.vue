<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { RefreshCw, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface PendingJob {
    id: number;
    queue: string;
    job_name: string;
    attempts: number;
    created_at: string;
    available_at: string;
    is_reserved: boolean;
}

interface FailedJob {
    id: number;
    uuid: string;
    queue: string;
    job_name: string;
    exception_message: string;
    failed_at: string;
}

interface Props {
    pendingJobs: {
        data: PendingJob[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    failedJobs: {
        data: FailedJob[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    queues: string[];
    filters: {
        queue: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'System Health', href: '/admin/system' },
    { title: 'Job Queue', href: '/admin/jobs' },
];

const selectedQueue = ref(props.filters.queue);

const filterByQueue = () => {
    router.get('/admin/jobs', {
        queue: selectedQueue.value || undefined,
    }, { preserveState: true });
};

const retryJob = (id: number) => {
    if (confirm('Are you sure you want to retry this job?')) {
        router.post(`/admin/jobs/${id}/retry`);
    }
};

const deleteJob = (id: number) => {
    if (confirm('Are you sure you want to delete this failed job?')) {
        router.delete(`/admin/jobs/${id}`);
    }
};

const flushAllFailed = () => {
    if (confirm('Are you sure you want to delete ALL failed jobs? This cannot be undone.')) {
        router.delete('/admin/jobs');
    }
};
</script>

<template>
    <Head title="Job Queue - Admin" />

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Job Queue</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Monitor pending and failed jobs.</p>
            </div>

            <!-- Filters -->
            <div class="mb-6 flex flex-wrap items-center gap-4">
                <select
                    v-model="selectedQueue"
                    @change="filterByQueue"
                    class="rounded-lg border px-4 py-2 dark:border-gray-700 dark:bg-gray-800"
                >
                    <option value="">All Queues</option>
                    <option v-for="queue in queues" :key="queue" :value="queue">{{ queue }}</option>
                </select>
            </div>

            <!-- Pending Jobs -->
            <div class="mb-8">
                <h2 class="mb-4 text-lg font-semibold">Pending Jobs ({{ pendingJobs.data.length }})</h2>
                <div class="overflow-hidden rounded-lg border dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Job
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Queue
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Attempts
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Created
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                            <tr v-for="job in pendingJobs.data" :key="job.id">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                    {{ job.job_name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ job.queue }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                    {{ job.attempts }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ job.created_at }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <span
                                        class="inline-flex rounded-full px-2 text-xs font-semibold leading-5"
                                        :class="job.is_reserved ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'"
                                    >
                                        {{ job.is_reserved ? 'Processing' : 'Queued' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="pendingJobs.data.length === 0">
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No pending jobs.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pending Pagination -->
                <div v-if="pendingJobs.links.length > 3" class="mt-4 flex justify-center gap-1">
                    <template v-for="link in pendingJobs.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="rounded px-3 py-1 text-sm"
                            :class="link.active ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-800'"
                        >
                            <!-- eslint-disable-next-line vue/no-v-html -->
                            <span v-html="link.label" />
                        </Link>
                    </template>
                </div>
            </div>

            <!-- Failed Jobs -->
            <div>
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Failed Jobs ({{ failedJobs.data.length }})</h2>
                    <Button v-if="failedJobs.data.length > 0" variant="destructive" size="sm" @click="flushAllFailed">
                        <Trash2 class="mr-2 h-4 w-4" />
                        Flush All
                    </Button>
                </div>
                <div class="overflow-hidden rounded-lg border dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Job
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Queue
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Error
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Failed At
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                            <tr v-for="job in failedJobs.data" :key="job.id">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                    {{ job.job_name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ job.queue }}
                                </td>
                                <td class="max-w-xs truncate px-6 py-4 text-sm text-red-600 dark:text-red-400" :title="job.exception_message">
                                    {{ job.exception_message }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ job.failed_at }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            @click="retryJob(job.id)"
                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400"
                                            title="Retry"
                                        >
                                            <RefreshCw class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="deleteJob(job.id)"
                                            class="text-red-600 hover:text-red-900 dark:text-red-400"
                                            title="Delete"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="failedJobs.data.length === 0">
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No failed jobs.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Failed Pagination -->
                <div v-if="failedJobs.links.length > 3" class="mt-4 flex justify-center gap-1">
                    <template v-for="link in failedJobs.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="rounded px-3 py-1 text-sm"
                            :class="link.active ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-800'"
                        >
                            <!-- eslint-disable-next-line vue/no-v-html -->
                            <span v-html="link.label" />
                        </Link>
                    </template>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
