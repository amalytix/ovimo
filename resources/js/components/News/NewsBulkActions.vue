<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { EyeOff, WandSparkles } from 'lucide-vue-next';

defineProps<{
    count: number;
}>();

const emit = defineEmits<{
    (event: 'create-content'): void;
    (event: 'hide'): void;
    (event: 'hide-not-relevant'): void;
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
                <Button size="sm" @click="emit('create-content')">
                    <WandSparkles
                        class="mr-2 h-4 w-4 text-gray-700 dark:text-gray-200"
                    />
                    Create Content Piece
                </Button>
                <Button variant="outline" size="sm" @click="emit('hide')">
                    <EyeOff class="mr-2 h-4 w-4" />
                    Hide
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    @click="emit('hide-not-relevant')"
                    >Hide not relevant</Button
                >
                <Button variant="destructive" size="sm" @click="emit('delete')"
                    >Delete</Button
                >
                <Button variant="ghost" size="sm" @click="emit('clear')"
                    >Clear</Button
                >
            </div>
        </div>
    </div>
</template>
