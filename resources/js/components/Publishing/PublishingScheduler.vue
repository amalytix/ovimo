<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    modelValue: string;
}>();

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void;
}>();
</script>

<template>
    <div class="space-y-2">
        <Label for="schedule_at">Schedule (local time)</Label>
        <Input
            id="schedule_at"
            type="datetime-local"
            :model-value="modelValue"
            @update:model-value="(value) => emit('update:modelValue', value)"
        />
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Leave empty to publish immediately.
        </p>
    </div>
</template>
