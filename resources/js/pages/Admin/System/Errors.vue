<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface ErrorLog {
    id: number;
    event_type: string;
    description: string;
    user_name: string | null;
    user_email: string | null;
    team_name: string | null;
    source_id: number | null;
    post_id: number | null;
    ip_address: string | null;
    created_at: string;
    metadata: Record<string, unknown> | null;
}

interface Props {
    errors: {
        data: ErrorLog[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    errorCounts: {
        '1h': number;
        '24h': number;
        '7d': number;
    };
    filters: {
        period: string;
        search: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'System Health', href: '/admin/system' },
    { title: 'Error Logs', href: '/admin/errors' },
];

const period = ref(props.filters.period);
const search = ref(props.filters.search);

const applyFilters = () => {
    router.get('/admin/errors', {
        period: period.value || undefined,
        search: search.value || undefined,
    }, { preserveState: true });
};

let searchTimeout: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

const expandedRows = ref<Set<number>>(new Set());

const toggleRow = (id: number) => {
    if (expandedRows.value.has(id)) {
        expandedRows.value.delete(id);
    } else {
        expandedRows.value.add(id);
    }
};

const formatMetadata = (metadata: Record<string, unknown> | null): string => {
    if (!metadata) return '';
    return JSON.stringify(metadata, null, 2);
};
</script>

<template>
    <Head title="Error Logs - Admin" />

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Error Logs</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">View application errors and exceptions.</p>
            </div>

            <!-- Error Counts -->
            <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-lg border bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Last Hour</div>
                    <div class="mt-1 text-2xl font-semibold" :class="errorCounts['1h'] > 0 ? 'text-red-600 dark:text-red-400' : ''">
                        {{ errorCounts['1h'] }}
                    </div>
                </div>
                <div class="rounded-lg border bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Last 24 Hours</div>
                    <div class="mt-1 text-2xl font-semibold" :class="errorCounts['24h'] > 0 ? 'text-red-600 dark:text-red-400' : ''">
                        {{ errorCounts['24h'] }}
                    </div>
                </div>
                <div class="rounded-lg border bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Last 7 Days</div>
                    <div class="mt-1 text-2xl font-semibold" :class="errorCounts['7d'] > 0 ? 'text-red-600 dark:text-red-400' : ''">
                        {{ errorCounts['7d'] }}
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="mb-6 flex flex-wrap items-center gap-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search errors..."
                    class="rounded-lg border px-4 py-2 dark:border-gray-700 dark:bg-gray-800"
                />
                <select
                    v-model="period"
                    @change="applyFilters"
                    class="rounded-lg border px-4 py-2 dark:border-gray-700 dark:bg-gray-800"
                >
                    <option value="1h">Last Hour</option>
                    <option value="24h">Last 24 Hours</option>
                    <option value="7d">Last 7 Days</option>
                    <option value="30d">Last 30 Days</option>
                </select>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-lg border dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Time
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Type
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Description
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                User
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Team
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        <template v-for="error in errors.data" :key="error.id">
                            <tr
                                class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                                @click="toggleRow(error.id)"
                            >
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ error.created_at }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <span class="inline-flex rounded-full bg-red-100 px-2 text-xs font-semibold leading-5 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        {{ error.event_type }}
                                    </span>
                                </td>
                                <td class="max-w-md truncate px-6 py-4 text-sm text-gray-900 dark:text-white" :title="error.description">
                                    {{ error.description }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <template v-if="error.user_name">
                                        {{ error.user_name }}
                                        <div class="text-xs text-gray-400 dark:text-gray-500">{{ error.user_email }}</div>
                                    </template>
                                    <template v-else>-</template>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ error.team_name || '-' }}
                                </td>
                            </tr>
                            <!-- Expanded row -->
                            <tr v-if="expandedRows.has(error.id)">
                                <td colspan="5" class="bg-gray-50 px-6 py-4 dark:bg-gray-800">
                                    <div class="space-y-2 text-sm">
                                        <div>
                                            <span class="font-medium text-gray-700 dark:text-gray-300">Full Description:</span>
                                            <p class="mt-1 whitespace-pre-wrap text-gray-600 dark:text-gray-400">{{ error.description }}</p>
                                        </div>
                                        <div v-if="error.ip_address" class="flex gap-2">
                                            <span class="font-medium text-gray-700 dark:text-gray-300">IP Address:</span>
                                            <span class="text-gray-600 dark:text-gray-400">{{ error.ip_address }}</span>
                                        </div>
                                        <div v-if="error.source_id" class="flex gap-2">
                                            <span class="font-medium text-gray-700 dark:text-gray-300">Source ID:</span>
                                            <span class="text-gray-600 dark:text-gray-400">{{ error.source_id }}</span>
                                        </div>
                                        <div v-if="error.post_id" class="flex gap-2">
                                            <span class="font-medium text-gray-700 dark:text-gray-300">Post ID:</span>
                                            <span class="text-gray-600 dark:text-gray-400">{{ error.post_id }}</span>
                                        </div>
                                        <div v-if="error.metadata">
                                            <span class="font-medium text-gray-700 dark:text-gray-300">Metadata:</span>
                                            <pre class="mt-1 overflow-x-auto rounded bg-gray-100 p-2 text-xs dark:bg-gray-900">{{ formatMetadata(error.metadata) }}</pre>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="errors.data.length === 0">
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No errors found for the selected time period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="errors.links.length > 3" class="mt-4 flex justify-center gap-1">
                <template v-for="link in errors.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded px-3 py-1 text-sm"
                        :class="link.active ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-800'"
                    >
                        <!-- eslint-disable-next-line vue/no-v-html -->
                        <span v-html="link.label" />
                    </Link>
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <span
                        v-else
                        class="rounded bg-gray-50 px-3 py-1 text-sm text-gray-400 dark:bg-gray-900"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AdminLayout>
</template>
