<script setup lang="ts">
import ContentPieceBulkActions from '@/components/ContentPiece/ContentPieceBulkActions.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { toast } from '@/components/ui/sonner';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import CalendarMonth from '@/pages/ContentPieces/CalendarMonth.vue';
import CalendarWeek from '@/pages/ContentPieces/CalendarWeek.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Badge } from '@/components/ui/badge';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type Derivative = {
    id: number;
    channel_id: number;
    status: 'NOT_STARTED' | 'DRAFT' | 'FINAL' | 'PUBLISHED' | 'NOT_PLANNED';
    generation_status: 'IDLE' | 'QUEUED' | 'PROCESSING' | 'COMPLETED' | 'FAILED';
};

type Channel = {
    id: number;
    name: string;
    language: string;
    icon: string | null;
    color: string | null;
};

interface ContentPiece {
    id: number;
    internal_name: string;
    created_at: string;
    derivatives: Derivative[];
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
    channels: Channel[];
    filters: {
        search?: string;
        view?: 'list' | 'week' | 'month';
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Content Pieces', href: '/content-pieces' },
];

const search = ref(props.filters.search || '');
const view = ref<Props['filters']['view']>(props.filters.view || 'list');
const selectedDate = ref<string>(
    new Date().toISOString().slice(0, 10),
);
const selectedIds = ref<number[]>([]);

const showListView = computed(() => view.value === 'list');

const allSelected = computed(() => {
    return (
        props.contentPieces.data.length > 0 &&
        selectedIds.value.length === props.contentPieces.data.length
    );
});

const applyFilters = (overrides: Record<string, unknown> = {}) => {
    router.get(
        '/content-pieces',
        {
            search: search.value || undefined,
            view: view.value,
            date: selectedDate.value,
            ...overrides,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
};

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

const getDerivativeForChannel = (piece: ContentPiece, channelId: number) => {
    return piece.derivatives?.find((d) => d.channel_id === channelId);
};

const getDerivativeStatusClasses = (status: Derivative['status'] | null) => {
    switch (status) {
        case 'NOT_STARTED':
            return 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700';
        case 'DRAFT':
            return 'bg-orange-100 text-orange-700 border-orange-200 dark:bg-orange-900/40 dark:text-orange-300 dark:border-orange-800';
        case 'FINAL':
            return 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:text-green-300 dark:border-green-800';
        case 'PUBLISHED':
            return 'bg-purple-100 text-purple-700 border-purple-200 dark:bg-purple-900/40 dark:text-purple-300 dark:border-purple-800';
        case 'NOT_PLANNED':
            return 'bg-gray-50 text-gray-400 border-gray-200 dark:bg-gray-900 dark:text-gray-500 dark:border-gray-800';
        default:
            return 'bg-gray-50 text-gray-400 border-dashed border-gray-300 dark:bg-gray-900 dark:text-gray-600 dark:border-gray-700';
    }
};

const getDerivativeStatusLabel = (status: Derivative['status'] | null) => {
    switch (status) {
        case 'NOT_STARTED':
            return 'Not Started';
        case 'DRAFT':
            return 'Draft';
        case 'FINAL':
            return 'Final';
        case 'PUBLISHED':
            return 'Published';
        case 'NOT_PLANNED':
            return 'Not Planned';
        default:
            return 'No derivative';
    }
};

const isGenerating = (derivative: Derivative | undefined) => {
    return (
        derivative &&
        ['QUEUED', 'PROCESSING'].includes(derivative.generation_status)
    );
};

const toggleSelection = (id: number, checked: boolean) => {
    if (checked) {
        if (!selectedIds.value.includes(id)) {
            selectedIds.value.push(id);
        }
    } else {
        selectedIds.value = selectedIds.value.filter(
            (selectedId) => selectedId !== id,
        );
    }
};

const toggleAll = (checked: boolean) => {
    if (checked) {
        selectedIds.value = props.contentPieces.data.map((item) => item.id);
    } else {
        selectedIds.value = [];
    }
};

const refreshContentPieces = () => {
    router.get(
        '/content-pieces',
        {
            search: search.value || undefined,
            view: view.value,
            date: selectedDate.value,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
};

const handleBulkDelete = async () => {
    if (selectedIds.value.length === 0) {
        return;
    }
    if (
        !confirm(
            `Are you sure you want to delete ${selectedIds.value.length} content piece(s)? This action cannot be undone.`,
        )
    ) {
        return;
    }
    try {
        await axios.post('/content-pieces/bulk-delete', {
            content_piece_ids: selectedIds.value,
        });
        toast.success('Content pieces deleted');
        selectedIds.value = [];
        refreshContentPieces();
    } catch (error) {
        console.error(error);
        toast.error('Unable to delete content pieces right now.');
    }
};

watch(
    () => props.contentPieces.data,
    (items) => {
        const availableIds = items.map((item) => item.id);
        selectedIds.value = selectedIds.value.filter((id) =>
            availableIds.includes(id),
        );
    },
);
</script>

<template>
    <Head title="Content Pieces" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <TooltipProvider>
            <div class="p-6">
                <div
                    class="mb-6 flex flex-wrap items-center justify-between gap-4"
                >
                    <div>
                        <h1 class="text-2xl font-semibold">Content Pieces</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Plan, schedule, and organize your content.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <div
                            class="flex rounded-md border border-gray-200 bg-white text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900"
                        >
                            <Button
                                :variant="view === 'list' ? 'default' : 'outline'"
                                class="rounded-none"
                                @click="switchView('list')"
                            >
                                List
                            </Button>
                            <Button
                                :variant="view === 'week' ? 'default' : 'outline'"
                                class="rounded-none"
                                @click="switchView('week')"
                            >
                                Week
                            </Button>
                            <Button
                                :variant="view === 'month' ? 'default' : 'outline'"
                                class="rounded-none"
                                @click="switchView('month')"
                            >
                                Month
                            </Button>
                        </div>
                        <Link href="/content-pieces/create">
                            <Button>Create Content</Button>
                        </Link>
                    </div>
                </div>

                <!-- Filters -->
                <div class="mb-6">
                    <div class="max-w-sm space-y-2">
                        <Label>Search</Label>
                        <Input
                            v-model="search"
                            placeholder="Search by name..."
                            @keyup.enter="applyFilters"
                        />
                    </div>
                </div>

                <div v-if="showListView" class="space-y-4">
                    <div
                        class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700"
                    >
                        <table
                            class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                        >
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 align-middle">
                                        <Checkbox
                                            :model-value="allSelected"
                                            @update:model-value="
                                                toggleAll($event === true)
                                            "
                                        />
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                    >
                                        Name
                                    </th>
                                    <th
                                        v-for="channel in channels"
                                        :key="channel.id"
                                        class="px-3 py-3 text-center text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                    >
                                        {{ channel.name }}
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                    >
                                        Created
                                    </th>
                                    <th
                                        class="px-6 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                    >
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900"
                            >
                                <tr
                                    v-for="piece in contentPieces.data"
                                    :key="piece.id"
                                >
                                    <td class="px-4 py-4">
                                        <Checkbox
                                            :model-value="
                                                selectedIds.includes(piece.id)
                                            "
                                            @update:model-value="
                                                (checked: boolean) =>
                                                    toggleSelection(
                                                        piece.id,
                                                        checked,
                                                    )
                                            "
                                        />
                                    </td>
                                    <td
                                        class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        <div class="max-w-xs md:max-w-sm">
                                            <Link
                                                :href="`/content-pieces/${piece.id}/edit`"
                                                class="line-clamp-2 break-words hover:text-blue-600 dark:hover:text-blue-400"
                                            >
                                                {{ piece.internal_name }}
                                            </Link>
                                        </div>
                                    </td>
                                    <td
                                        v-for="channel in channels"
                                        :key="channel.id"
                                        class="px-2 py-4 text-center"
                                    >
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Link
                                                    :href="`/content-pieces/${piece.id}/edit?tab=derivatives&channel=${channel.id}`"
                                                    class="inline-block"
                                                >
                                                    <Badge
                                                        :class="[
                                                            getDerivativeStatusClasses(
                                                                getDerivativeForChannel(
                                                                    piece,
                                                                    channel.id,
                                                                )?.status ?? null,
                                                            ),
                                                            isGenerating(
                                                                getDerivativeForChannel(
                                                                    piece,
                                                                    channel.id,
                                                                ),
                                                            )
                                                                ? 'animate-pulse'
                                                                : '',
                                                            'text-sm px-2 py-0.5 hover:opacity-80 transition-opacity cursor-pointer',
                                                        ]"
                                                    >
                                                        {{
                                                            getDerivativeStatusLabel(
                                                                getDerivativeForChannel(
                                                                    piece,
                                                                    channel.id,
                                                                )?.status ?? null,
                                                            )
                                                        }}
                                                    </Badge>
                                                </Link>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p class="font-medium">
                                                    {{ channel.name }}
                                                </p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </td>
                                    <td
                                        class="px-6 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                                    >
                                        {{ piece.created_at }}
                                    </td>
                                    <td
                                        class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                    >
                                        <div
                                            class="flex items-center justify-end gap-3"
                                        >
                                            <Link
                                                :href="`/content-pieces/${piece.id}/edit`"
                                                class="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                                                title="Edit"
                                            >
                                                <Pencil :size="18" />
                                                <span class="sr-only">Edit</span>
                                            </Link>
                                            <button
                                                class="text-red-500 hover:text-red-700 dark:text-red-400"
                                                title="Delete"
                                                @click="
                                                    deleteContentPiece(piece.id)
                                                "
                                            >
                                                <Trash2 :size="18" />
                                                <span class="sr-only">Delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="contentPieces.data.length === 0">
                                    <td
                                        :colspan="channels.length + 4"
                                        class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        No content pieces found. Click "Create
                                        Content" to start generating content.
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
        </TooltipProvider>
    </AppLayout>

    <ContentPieceBulkActions
        :count="selectedIds.length"
        @delete="handleBulkDelete"
        @clear="selectedIds = []"
    />
</template>
