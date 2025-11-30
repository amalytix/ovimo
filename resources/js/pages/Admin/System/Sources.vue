<script setup lang="ts">
import StatCard from '@/components/Admin/StatCard.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Source {
    id: number;
    internal_name: string;
    type: string;
    url: string;
    team_name: string;
    team_id: number;
    is_active: boolean;
    consecutive_failures: number;
    last_run_status: string | null;
    last_run_error: string | null;
    failed_at: string | null;
    last_checked_at: string | null;
}

interface Props {
    sources: {
        data: Source[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    stats: {
        total: number;
        active: number;
        failing: number;
        inactive: number;
    };
    filters: {
        status: string;
        search: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'System Health', href: '/admin/system' },
    { title: 'Source Health', href: '/admin/sources' },
];

const status = ref(props.filters.status);
const search = ref(props.filters.search);

const applyFilters = () => {
    router.get(
        '/admin/sources',
        {
            status: status.value || undefined,
            search: search.value || undefined,
        },
        { preserveState: true },
    );
};

let searchTimeout: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

const getStatusBadgeClass = (source: Source) => {
    if (!source.is_active) {
        return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
    }
    if (source.consecutive_failures > 0) {
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
    }
    return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
};

const getStatusText = (source: Source) => {
    if (!source.is_active) return 'Inactive';
    if (source.consecutive_failures > 0)
        return `Failing (${source.consecutive_failures}x)`;
    return 'Healthy';
};
</script>

<template>
    <Head title="Source Health - Admin" />

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Source Health</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Monitor source status and failures.
                </p>
            </div>

            <!-- Stats -->
            <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
                <StatCard title="Total Sources" :value="stats.total" />
                <StatCard title="Active" :value="stats.active" />
                <StatCard title="Failing" :value="stats.failing" />
                <StatCard title="Inactive" :value="stats.inactive" />
            </div>

            <!-- Filters -->
            <div class="mb-6 flex flex-wrap items-center gap-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name..."
                    class="rounded-lg border px-4 py-2 dark:border-gray-700 dark:bg-gray-800"
                />
                <select
                    v-model="status"
                    @change="applyFilters"
                    class="rounded-lg border px-4 py-2 dark:border-gray-700 dark:bg-gray-800"
                >
                    <option value="failing">Failing</option>
                    <option value="healthy">Healthy</option>
                    <option value="inactive">Inactive</option>
                    <option value="all">All</option>
                </select>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-lg border dark:border-gray-700">
                <table
                    class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                >
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >
                                Source
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >
                                Team
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >
                                Type
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >
                                Status
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >
                                Last Error
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >
                                Last Checked
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900"
                    >
                        <tr v-for="source in sources.data" :key="source.id">
                            <td class="px-6 py-4">
                                <div
                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{ source.internal_name }}
                                </div>
                                <a
                                    :href="source.url"
                                    target="_blank"
                                    class="flex items-center gap-1 text-xs text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    {{ source.url.substring(0, 50)
                                    }}{{ source.url.length > 50 ? '...' : '' }}
                                    <ExternalLink class="h-3 w-3" />
                                </a>
                            </td>
                            <td
                                class="px-6 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                            >
                                <Link
                                    :href="`/admin/teams/${source.team_id}/edit`"
                                    class="hover:underline"
                                >
                                    {{ source.team_name }}
                                </Link>
                            </td>
                            <td
                                class="px-6 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                            >
                                {{ source.type }}
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap">
                                <span
                                    class="inline-flex rounded-full px-2 text-xs leading-5 font-semibold"
                                    :class="getStatusBadgeClass(source)"
                                >
                                    {{ getStatusText(source) }}
                                </span>
                            </td>
                            <td
                                class="max-w-xs truncate px-6 py-4 text-sm text-red-600 dark:text-red-400"
                                :title="source.last_run_error || ''"
                            >
                                {{ source.last_run_error || '-' }}
                            </td>
                            <td
                                class="px-6 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                            >
                                {{ source.last_checked_at || 'Never' }}
                            </td>
                        </tr>
                        <tr v-if="sources.data.length === 0">
                            <td
                                colspan="6"
                                class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                            >
                                No sources found matching your filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="sources.links.length > 3"
                class="mt-4 flex justify-center gap-1"
            >
                <template v-for="link in sources.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded px-3 py-1 text-sm"
                        :class="
                            link.active
                                ? 'bg-blue-500 text-white'
                                : 'bg-gray-100 dark:bg-gray-800'
                        "
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
