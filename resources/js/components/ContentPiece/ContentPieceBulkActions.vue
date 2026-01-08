<script setup lang="ts">
import { Button } from '@/components/ui/button';

defineProps<{
    count: number;
}>();

const emit = defineEmits<{
    (event: 'delete'): void;
    (event: 'clear'): void;
}>();
</script>

<template>
    <div
        v-if="count > 0"
        class="fixed inset-x-0 bottom-0 z-50 border-t border-gray-200 bg-white/90 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90"
    >
        <div
            class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-3"
        >
            <div class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ count }} item{{ count === 1 ? '' : 's' }} selected
            </div>
            <div class="flex items-center gap-3">
                <Button variant="destructive" size="sm" @click="emit('delete')"
                    >Delete Selected</Button
                >
                <Button variant="ghost" size="sm" @click="emit('clear')"
                    >Clear</Button
                >
            </div>
        </div>
    </div>
</template>
