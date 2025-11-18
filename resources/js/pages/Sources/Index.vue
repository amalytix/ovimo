<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { toast } from 'vue3-toastify';

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
    next_check_at: string | null;
    posts_count: number;
    tags: Tag[];
}

interface Filters {
    tag_ids?: number[];
    sort_by?: string;
    sort_direction?: string;
}

interface Props {
    sources: {
        data: Source[];
        links: object;
        meta: object;
    };
    tags: Tag[];
    filters?: Filters;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sources', href: '/sources' },
];

// Initialize selected tags from URL filters
const selectedTagIds = ref<number[]>(props.filters?.tag_ids || []);

// Initialize sorting from URL filters
const currentSortBy = ref(props.filters?.sort_by || 'internal_name');
const currentSortDirection = ref(props.filters?.sort_direction || 'asc');

const deleteSource = (id: number) => {
    if (confirm('Are you sure you want to delete this source?')) {
        router.delete(`/sources/${id}`);
    }
};

const checkSource = (id: number) => {
    router.post(
        `/sources/${id}/check`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Source check has been queued.');
            },
        },
    );
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

const toggleTagFilter = (tagId: number, checked: boolean | 'indeterminate') => {
    if (checked === true) {
        if (!selectedTagIds.value.includes(tagId)) {
            selectedTagIds.value.push(tagId);
        }
    } else {
        selectedTagIds.value = selectedTagIds.value.filter((id) => id !== tagId);
    }
};

const applyFilters = () => {
    router.get(
        '/sources',
        {
            tag_ids: selectedTagIds.value.length > 0 ? selectedTagIds.value : undefined,
            sort_by: currentSortBy.value,
            sort_direction: currentSortDirection.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const clearFilters = () => {
    selectedTagIds.value = [];
    currentSortBy.value = 'internal_name';
    currentSortDirection.value = 'asc';
    router.get('/sources', {}, { preserveState: true, preserveScroll: true });
};

const sortBy = (column: string) => {
    if (currentSortBy.value === column) {
        // Toggle direction if same column
        currentSortDirection.value = currentSortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        // New column, default to desc for posts_count and last_checked_at, asc for others
        currentSortBy.value = column;
        currentSortDirection.value = column === 'posts_count' || column === 'last_checked_at' ? 'desc' : 'asc';
    }
    applyFilters();
};

const getSortIcon = (column: string) => {
    if (currentSortBy.value !== column) return '';
    return currentSortDirection.value === 'asc' ? '↑' : '↓';
};

// Watch for filter changes and apply them
watch(selectedTagIds, applyFilters, { deep: true });
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

            <!-- Tag Filters -->
            <div v-if="tags.length > 0" class="mb-6 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-3 flex items-center justify-between">
                    <Label class="text-sm font-medium">Filter by Tags</Label>
                    <button
                        v-if="selectedTagIds.length > 0"
                        @click="clearFilters"
                        class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                    >
                        Clear filters
                    </button>
                </div>
                <div class="flex flex-wrap gap-4">
                    <div v-for="tag in tags" :key="tag.id" class="flex items-center gap-2">
                        <Checkbox
                            :id="`filter-tag-${tag.id}`"
                            :default-value="selectedTagIds.includes(tag.id)"
                            @update:model-value="toggleTagFilter(tag.id, $event)"
                        />
                        <Label :for="`filter-tag-${tag.id}`" class="cursor-pointer text-sm font-normal">
                            {{ tag.name }}
                        </Label>
                    </div>
                </div>
                <div v-if="selectedTagIds.length > 0" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Showing sources with any of the selected tags
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                @click="sortBy('internal_name')"
                            >
                                Name {{ getSortIcon('internal_name') }}
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                @click="sortBy('type')"
                            >
                                Type {{ getSortIcon('type') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Tags</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Interval</th>
                            <th
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                @click="sortBy('posts_count')"
                            >
                                Posts {{ getSortIcon('posts_count') }}
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                @click="sortBy('last_checked_at')"
                            >
                                Last Checked {{ getSortIcon('last_checked_at') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Next Check</th>
                            <th
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                @click="sortBy('is_active')"
                            >
                                Status {{ getSortIcon('is_active') }}
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        <tr v-for="source in sources.data" :key="source.id">
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                <Link :href="`/sources/${source.id}/edit`" class="hover:text-blue-600 dark:hover:text-blue-400">
                                    {{ source.internal_name }}
                                </Link>
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
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ source.next_check_at || 'Not scheduled' }}
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
                                    class="mr-3 text-red-600 hover:text-red-900 dark:text-red-400"
                                >
                                    Delete
                                </button>
                                <button
                                    @click="checkSource(source.id)"
                                    class="text-green-600 hover:text-green-900 dark:text-green-400"
                                >
                                    Check now
                                </button>
                            </td>
                        </tr>
                        <tr v-if="sources.data.length === 0">
                            <td colspan="9" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No sources found. Click "Add Source" to create your first source.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination
                :links="sources.links"
                :from="sources.meta?.from"
                :to="sources.meta?.to"
                :total="sources.meta?.total"
            />
        </div>
    </AppLayout>
</template>
