<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface ContentPiece {
    id: number;
    internal_name: string;
    channel: string;
    target_language: string;
    status: string;
    prompt_name: string | null;
    created_at: string;
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
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Content Pieces', href: '/content-pieces' }];

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'all');
const channel = ref(props.filters.channel || 'all');

const applyFilters = () => {
    router.get(
        '/content-pieces',
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            channel: channel.value === 'all' ? undefined : channel.value,
        },
        { preserveState: true, replace: true },
    );
};

watch([status, channel], () => {
    applyFilters();
});

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
        NOT_STARTED: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
        DRAFT: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        FINAL: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
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
</script>

<template>
    <Head title="Content Pieces" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Content Pieces</h1>
                <Link href="/content-pieces/create">
                    <Button>Create Content</Button>
                </Link>
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

            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
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
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                {{ piece.internal_name }}
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
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ piece.created_at }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <Link
                                    :href="`/content-pieces/${piece.id}/edit`"
                                    class="mr-3 text-blue-600 hover:text-blue-900 dark:text-blue-400"
                                >
                                    Edit
                                </Link>
                                <button @click="deleteContentPiece(piece.id)" class="text-red-600 hover:text-red-900 dark:text-red-400">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="contentPieces.data.length === 0">
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
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
    </AppLayout>
</template>
