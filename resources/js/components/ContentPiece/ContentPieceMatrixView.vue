<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Link } from '@inertiajs/vue3';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

type Channel = {
    id: number;
    name: string;
    slug: string;
    icon: string | null;
    color: string | null;
};

type Derivative = {
    id: number;
    channel_id: number;
    status: 'NOT_STARTED' | 'DRAFT' | 'FINAL' | 'PUBLISHED' | 'NOT_PLANNED';
    generation_status: 'IDLE' | 'QUEUED' | 'PROCESSING' | 'COMPLETED' | 'FAILED';
};

type ContentPiece = {
    id: number;
    internal_name: string;
    channel: string;
    target_language: string;
    status: string;
    created_at: string;
    published_at: string | null;
    derivatives?: Derivative[];
};

const props = defineProps<{
    contentPieces: ContentPiece[];
    channels: Channel[];
    selectedIds: number[];
    allSelected: boolean;
}>();

const emit = defineEmits<{
    (event: 'toggle-selection', id: number, checked: boolean): void;
    (event: 'toggle-all', checked: boolean): void;
    (event: 'delete', id: number): void;
}>();

const getDerivativeForChannel = (piece: ContentPiece, channelId: number) => {
    return piece.derivatives?.find(d => d.channel_id === channelId);
};

const getStatusColor = (status: Derivative['status'] | null) => {
    switch (status) {
        case 'NOT_STARTED':
            return 'bg-gray-400';
        case 'DRAFT':
            return 'bg-orange-500';
        case 'FINAL':
            return 'bg-green-500';
        case 'PUBLISHED':
            return 'bg-purple-500';
        case 'NOT_PLANNED':
            return 'bg-gray-300';
        default:
            return 'bg-gray-200';
    }
};

const getStatusLabel = (status: Derivative['status'] | null) => {
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
    return derivative && ['QUEUED', 'PROCESSING'].includes(derivative.generation_status);
};
</script>

<template>
    <TooltipProvider>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 align-middle">
                            <Checkbox
                                :model-value="allSelected"
                                @update:model-value="emit('toggle-all', $event === true)"
                            />
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                        >
                            Content Piece
                        </th>
                        <th
                            v-for="channel in channels"
                            :key="channel.id"
                            class="px-3 py-3 text-center text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                        >
                            {{ channel.name }}
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                    <tr v-for="piece in contentPieces" :key="piece.id">
                        <td class="px-4 py-4">
                            <Checkbox
                                :model-value="selectedIds.includes(piece.id)"
                                @update:model-value="(checked: boolean) => emit('toggle-selection', piece.id, checked)"
                            />
                        </td>
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
                        <td
                            v-for="channel in channels"
                            :key="channel.id"
                            class="px-3 py-4 text-center"
                        >
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Link
                                        :href="`/content-pieces/${piece.id}/edit?tab=derivatives`"
                                        class="relative inline-flex h-8 w-8 items-center justify-center rounded-md transition hover:scale-110"
                                        :class="getStatusColor(getDerivativeForChannel(piece, channel.id)?.status ?? null)"
                                    >
                                        <span
                                            v-if="isGenerating(getDerivativeForChannel(piece, channel.id))"
                                            class="absolute inset-0 animate-pulse rounded-md bg-blue-400/50"
                                        />
                                    </Link>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p class="font-medium">{{ channel.name }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ getStatusLabel(getDerivativeForChannel(piece, channel.id)?.status ?? null) }}
                                    </p>
                                </TooltipContent>
                            </Tooltip>
                        </td>
                        <td class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
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
                                    @click="emit('delete', piece.id)"
                                    class="text-red-500 hover:text-red-700 dark:text-red-400"
                                    title="Delete"
                                >
                                    <Trash2 :size="18" />
                                    <span class="sr-only">Delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="contentPieces.length === 0">
                        <td
                            :colspan="channels.length + 3"
                            class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                        >
                            No content pieces found. Click "Create Content" to start generating content.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </TooltipProvider>
</template>
