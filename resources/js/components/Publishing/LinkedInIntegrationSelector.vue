<script setup lang="ts">
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { SocialIntegration } from '@/types/social';

const props = defineProps<{
    integrations: SocialIntegration[];
    modelValue: number | null;
}>();

const emit = defineEmits<{
    (event: 'update:modelValue', value: number | null): void;
}>();

const onChange = (value: string) => {
    emit('update:modelValue', value ? Number(value) : null);
};
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">LinkedIn profile</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ integrations.length }} connected</p>
        </div>
        <Select :model-value="modelValue?.toString()" @update:model-value="onChange">
            <SelectTrigger>
                <SelectValue placeholder="Choose LinkedIn profile" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem v-for="integration in integrations" :key="integration.id" :value="integration.id.toString()">
                    {{ integration.platform_username || integration.platform_user_id }}
                </SelectItem>
            </SelectContent>
        </Select>
        <p v-if="integrations.length === 0" class="text-xs text-gray-500 dark:text-gray-400">
            No LinkedIn profiles connected yet.
        </p>
    </div>
</template>
