<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import CalendarMonth from '@/pages/ContentPieces/CalendarMonth.vue';
import CalendarWeek from '@/pages/ContentPieces/CalendarWeek.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Pencil, Trash2 } from 'lucide-vue-next';

interface ContentPiece {
    id: number;
    internal_name: string;
    channel: string;
    target_language: string;
    status: string;
    prompt_name: string | null;
    created_at: string;
    published_at: string | null;
    published_at_human: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    contentPieces: {
        data: ContentPiece[];
        links: PaginationLink[];
        meta?: {
            from?: number;
            to?: number;
            total?: number;
        };
    };
    filters: {
        status?: string;
        channel?: string;
        search?: string;
        view?: 'list' | 'week' | 'month';
        sort_by?: string | null;
        sort_direction?: 'asc' | 'desc' | null;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Content Pieces', href: '/content-pieces' }];

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'all');
const channel = ref(props.filters.channel || 'all');
const view = ref<Props['filters']['view']>(props.filters.view || 'list');
const sortBy = ref(props.filters.sort_by || 'published_at');
const sortDirection = ref<Props['filters']['sort_direction']>(props.filters.sort_direction || 'asc');
const selectedDate = ref<string>(props.filters?.date || new Date().toISOString().slice(0, 10));

const showListView = computed(() => view.value === 'list');

const applyFilters = (overrides: Record<string, unknown> = {}) => {
    router.get(
        '/content-pieces',
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            channel: channel.value === 'all' ? undefined : channel.value,
            view: view.value,
            sort_by: sortBy.value,
            sort_direction: sortDirection.value,
            date: selectedDate.value,
            ...overrides,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
};

watch([status, channel], () => {
    applyFilters();
});

const switchView = (nextView: 'list' | 'month' | 'week') => {
    if (view.value === nextView) {
        return;
    }

    view.value = nextView;
    applyFilters();
};

const deleteContentPiece = (id: number) => {
    if (confirm('Are you sure you want to delete this content piece?')) {
        router.delete(`/content-pieces/${id}`);
    }
};

const formatStatus = (status: string) => {
    const map: Record<string, string> = {
        NOT_STARTED: 'Not Started',
        DRAFT: 'Draft',
        FINAL: 'Final',
    };
    return map[status] || status;
};

const getStatusColor = (status: string) => {
    const colors: Record<string, string> = {
        NOT_STARTED: 'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
        DRAFT: 'bg-blue-50 text-blue-700 dark:bg-blue-900/60 dark:text-blue-200',
        FINAL: 'bg-green-50 text-green-700 dark:bg-green-900/60 dark:text-green-200',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};

const formatChannel = (channel: string) => {
    const map: Record<string, string> = {
        BLOG_POST: 'Blog Post',
        LINKEDIN_POST: 'LinkedIn Post',
        YOUTUBE_SCRIPT: 'YouTube Script',
    };
    return map[channel] || channel;
};

const formatLanguage = (language: string) => {
    const map: Record<string, string> = {
        ENGLISH: 'English',
        GERMAN: 'German',
    };
    return map[language] || language;
};

const formatPublishDate = (publishedAt: string | null) => {
    if (! publishedAt) {
        return 'Unscheduled';
    }

    const date = new Date(publishedAt);

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};

const isPublishDateInPast = (publishedAt: string | null) => {
    if (! publishedAt) {
        return false;
    }

    const date = new Date(publishedAt);
    const now = new Date();

    return date < now;
};

const togglePublishSort = () => {
    sortBy.value = 'published_at';
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    applyFilters();
};
</script>

<template>
    <Head title="Content Pieces" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Content Pieces</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Plan, schedule, and organize your content.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex rounded-md border border-gray-200 bg-white text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <Button :variant="view === 'list' ? 'default' : 'outline'" class="rounded-none" @click="switchView('list')">
                            List
                        </Button>
                        <Button :variant="view === 'week' ? 'default' : 'outline'" class="rounded-none" @click="switchView('week')">
                            Week
                        </Button>
                        <Button :variant="view === 'month' ? 'default' : 'outline'" class="rounded-none" @click="switchView('month')">
                            Month
                        </Button>
                    </div>
                    <Link href="/content-pieces/create">
                        <Button>Create Content</Button>
                    </Link>
                </div>
            </div>

            <!-- Filters -->
            <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="space-y-2">
                    <Label>Search</Label>
                    <Input v-model="search" placeholder="Search by name..." @keyup.enter="applyFilters" />
                </div>
                <div class="space-y-2">
                    <Label>Status</Label>
                    <Select v-model="status">
                        <SelectTrigger>
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            <SelectItem value="NOT_STARTED">Not Started</SelectItem>
                            <SelectItem value="DRAFT">Draft</SelectItem>
                            <SelectItem value="FINAL">Final</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-2">
                    <Label>Channel</Label>
                    <Select v-model="channel">
                        <SelectTrigger>
                            <SelectValue placeholder="All channels" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All channels</SelectItem>
                            <SelectItem value="BLOG_POST">Blog Post</SelectItem>
                            <SelectItem value="LINKEDIN_POST">LinkedIn Post</SelectItem>
                            <SelectItem value="YOUTUBE_SCRIPT">YouTube Script</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div v-if="showListView" class="space-y-4">
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="w-1/2 px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 md:w-1/3">
                                    Name
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Channel
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Language
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    <button
                                        class="flex items-center gap-1 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                        type="button"
                                        @click="togglePublishSort"
                                    >
                                        Publish Date
                                        <span class="text-xs text-gray-400">
                                            {{ sortDirection === 'asc' ? '↑' : '↓' }}
                                        </span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Created
                                </th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                            <tr v-for="piece in contentPieces.data" :key="piece.id">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                    <div class="max-w-xs md:max-w-sm">
                                        <Link
                                            :href="`/content-pieces/${piece.id}/edit`"
                                            class="line-clamp-2 break-words hover:text-blue-600 dark:hover:text-blue-400"
                                        >
                                            {{ piece.internal_name }}
                                        </Link>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <span class="rounded bg-gray-100 px-2 py-1 text-xs dark:bg-gray-700">
                                        {{ formatChannel(piece.channel) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatLanguage(piece.target_language) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <span :class="getStatusColor(piece.status)" class="rounded-full px-2 py-1 text-xs">
                                        {{ formatStatus(piece.status) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900 dark:text-white">
                                    <div v-if="piece.published_at" class="space-y-1">
                                        <div
                                            class="text-sm text-gray-900 dark:text-white"
                                            :class="{ 'line-through opacity-60': isPublishDateInPast(piece.published_at) }"
                                        >
                                            {{ formatPublishDate(piece.published_at) }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ piece.published_at_human }}
                                        </div>
                                    </div>
                                    <span v-else class="text-sm text-gray-500 dark:text-gray-400">Unscheduled</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ piece.created_at }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-3">
                                        <Link
                                            :href="`/content-pieces/${piece.id}/edit`"
                                            class="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                                            title="Edit"
                                        >
                                            <Pencil :size="18" />
                                            <span class="sr-only">Edit</span>
                                        </Link>
                                        <button
                                            @click="deleteContentPiece(piece.id)"
                                            class="text-red-500 hover:text-red-700 dark:text-red-400"
                                            title="Delete"
                                        >
                                            <Trash2 :size="18" />
                                            <span class="sr-only">Delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="contentPieces.data.length === 0">
                                <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No content pieces found. Click "Create Content" to start generating content.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination
                    :links="contentPieces.links"
                    :from="contentPieces.meta?.from"
                    :to="contentPieces.meta?.to"
                    :total="contentPieces.meta?.total"
                />
            </div>

            <div v-else-if="view === 'month'">
                <CalendarMonth v-model:date="selectedDate" />
            </div>
            <div v-else>
                <CalendarWeek v-model:date="selectedDate" />
            </div>
        </div>
    </AppLayout>
</template>
