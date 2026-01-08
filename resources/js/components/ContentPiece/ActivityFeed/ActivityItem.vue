<script setup lang="ts">
import { AlertCircle, CheckCircle2, RefreshCw, Zap } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    eventType: string;
    eventTypeLabel: string;
    level: string;
    description: string;
    createdAt: string;
    user: { id: number; name: string } | null;
}>();

const icon = computed(() => {
    if (props.eventType === 'derivative.generated') return CheckCircle2;
    if (props.eventType === 'derivative.generation_failed') return AlertCircle;
    if (props.eventType === 'derivative.status_changed') return RefreshCw;
    return Zap;
});

const iconColor = computed(() => {
    if (props.level === 'error') return 'text-red-500';
    if (props.eventType === 'derivative.generated') return 'text-green-500';
    if (props.eventType === 'derivative.status_changed') return 'text-blue-500';
    return 'text-gray-400';
});
</script>

<template>
    <div class="flex items-start gap-2 rounded-lg p-2 text-sm text-muted-foreground hover:bg-muted/50">
        <component :is="icon" class="mt-0.5 h-4 w-4 shrink-0" :class="iconColor" />
        <div class="min-w-0 flex-1">
            <p class="break-words">{{ description }}</p>
            <p class="mt-0.5 text-xs text-gray-400">{{ createdAt }}</p>
        </div>
    </div>
</template>
