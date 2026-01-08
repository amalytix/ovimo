<script setup lang="ts">
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { computed } from 'vue';

const props = defineProps<{
    user: { id: number; name: string } | null;
    description: string;
    createdAt: string;
}>();

const initials = computed(() => {
    if (!props.user?.name) return '?';
    return props.user.name
        .split(' ')
        .map(n => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
});

const bgColor = computed(() => {
    if (!props.user) return 'bg-gray-200';
    // Simple hash to generate consistent color per user
    const colors = ['bg-blue-200', 'bg-green-200', 'bg-purple-200', 'bg-orange-200', 'bg-pink-200', 'bg-teal-200'];
    const hash = props.user.id % colors.length;
    return colors[hash];
});
</script>

<template>
    <div class="flex items-start gap-2.5 rounded-lg border bg-card p-2.5">
        <Avatar class="h-7 w-7 shrink-0">
            <AvatarFallback :class="bgColor" class="text-xs font-medium">
                {{ initials }}
            </AvatarFallback>
        </Avatar>
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-900 dark:text-gray-50">
                    {{ user?.name || 'Unknown' }}
                </span>
                <span class="text-xs text-gray-400">{{ createdAt }}</span>
            </div>
            <p class="mt-0.5 whitespace-pre-wrap break-words text-sm text-gray-700 dark:text-gray-300">
                {{ description }}
            </p>
        </div>
    </div>
</template>
