<script setup lang="ts">
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type Channel = {
    id: number;
    name: string;
    slug: string;
};

type ContentDerivative = {
    id: number;
    channel_id: number;
    status: 'NOT_STARTED' | 'DRAFT' | 'FINAL' | 'PUBLISHED' | 'NOT_PLANNED';
    generation_status: 'IDLE' | 'QUEUED' | 'PROCESSING' | 'COMPLETED' | 'FAILED';
};

const props = defineProps<{
    channels: Channel[];
    derivatives: ContentDerivative[];
}>();

const emit = defineEmits<{
    (event: 'channel-click', channelId: number): void;
}>();

const getDerivativeForChannel = (channelId: number) => {
    return props.derivatives.find(d => d.channel_id === channelId);
};

const getStatusColor = (status: ContentDerivative['status'] | null) => {
    switch (status) {
        case 'NOT_STARTED':
            return 'bg-gray-400';
        case 'DRAFT':
            return 'bg-blue-500';
        case 'FINAL':
            return 'bg-green-500';
        case 'PUBLISHED':
            return 'bg-purple-500';
        case 'NOT_PLANNED':
            return 'bg-gray-300 opacity-50';
        default:
            return 'bg-gray-200 border border-dashed border-gray-300';
    }
};

const getStatusLabel = (status: ContentDerivative['status'] | null) => {
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

const isGenerating = (derivative: ContentDerivative | undefined) => {
    return derivative && ['QUEUED', 'PROCESSING'].includes(derivative.generation_status);
};
</script>

<template>
    <TooltipProvider>
        <div class="flex items-center gap-1">
            <Tooltip v-for="channel in channels" :key="channel.id">
                <TooltipTrigger as-child>
                    <button
                        class="relative h-6 w-6 rounded-md transition hover:scale-110 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1"
                        :class="getStatusColor(getDerivativeForChannel(channel.id)?.status ?? null)"
                        @click="emit('channel-click', channel.id)"
                    >
                        <!-- Generating indicator -->
                        <span
                            v-if="isGenerating(getDerivativeForChannel(channel.id))"
                            class="absolute inset-0 animate-pulse rounded-md bg-blue-400/50"
                        />
                    </button>
                </TooltipTrigger>
                <TooltipContent>
                    <p class="font-medium">{{ channel.name }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ getStatusLabel(getDerivativeForChannel(channel.id)?.status ?? null) }}
                    </p>
                </TooltipContent>
            </Tooltip>
        </div>
    </TooltipProvider>
</template>
