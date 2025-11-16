<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';

interface Tag {
    id: number;
    name: string;
}

interface Source {
    id: number;
    internal_name: string;
    type: string;
    url: string;
    monitoring_interval: string;
    is_active: boolean;
    should_notify: boolean;
    auto_summarize: boolean;
    last_checked_at: string | null;
    posts_count: number;
    tags: Tag[];
}

interface Props {
    sources: {
        data: Source[];
        links: object;
        meta: object;
    };
    tags: Tag[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sources', href: '/sources' },
];

const deleteSource = (id: number) => {
    if (confirm('Are you sure you want to delete this source?')) {
        router.delete(`/sources/${id}`);
    }
};

const formatInterval = (interval: string) => {
    const map: Record<string, string> = {
        EVERY_10_MIN: 'Every 10 min',
        EVERY_30_MIN: 'Every 30 min',
        HOURLY: 'Hourly',
        EVERY_6_HOURS: 'Every 6 hours',
        DAILY: 'Daily',
        WEEKLY: 'Weekly',
    };
    return map[interval] || interval;
};
</script>

<template>
    <Head title="Sources" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Sources</h1>
                <Link
                    href="/sources/create"
                    class="rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                >
                    Add Source
                </Link>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Tags</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Interval</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Posts</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Last Checked</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        <tr v-for="source in sources.data" :key="source.id">
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                {{ source.internal_name }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <span class="rounded bg-gray-100 px-2 py-1 text-xs dark:bg-gray-700">
                                    {{ source.type }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <span
                                    v-for="tag in source.tags"
                                    :key="tag.id"
                                    class="mr-1 rounded-full bg-blue-100 px-2 py-1 text-xs text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                                >
                                    {{ tag.name }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ formatInterval(source.monitoring_interval) }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ source.posts_count }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ source.last_checked_at || 'Never' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span
                                    :class="source.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'"
                                    class="rounded-full px-2 py-1 text-xs"
                                >
                                    {{ source.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <Link
                                    :href="`/sources/${source.id}/edit`"
                                    class="mr-3 text-blue-600 hover:text-blue-900 dark:text-blue-400"
                                >
                                    Edit
                                </Link>
                                <button
                                    @click="deleteSource(source.id)"
                                    class="text-red-600 hover:text-red-900 dark:text-red-400"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="sources.data.length === 0">
                            <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No sources found. Click "Add Source" to create your first source.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
