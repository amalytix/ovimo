<script setup lang="ts">
import { cn } from '@/lib/utils';
import * as icons from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    name: string;
    class?: string;
    size?: number | string;
    color?: string;
    strokeWidth?: number | string;
}

const props = withDefaults(defineProps<Props>(), {
    class: '',
    size: 16,
    strokeWidth: 2,
});

const className = computed(() => cn('h-4 w-4', props.class));

const icon = computed(() => {
    const iconName = props.name
        .split(/[-_\\s]+/)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join('');

    const candidates = [
        iconName,
        `${iconName}Icon`,
        `Lucide${iconName}`,
        `Lucide${iconName}Icon`,
    ];

    const resolvedIcon = candidates
        .map((key) => (icons as Record<string, any>)[key])
        .find(Boolean);

    if (! resolvedIcon && import.meta.env.DEV) {
        console.warn(`[Icon] Missing icon "${props.name}". Tried: ${candidates.join(', ')}`);
    }

    return resolvedIcon ?? (icons as Record<string, any>).HelpCircle;
});
</script>

<template>
    <component
        :is="icon"
        :class="className"
        :size="size"
        :stroke-width="strokeWidth"
        :color="color"
    />
</template>
