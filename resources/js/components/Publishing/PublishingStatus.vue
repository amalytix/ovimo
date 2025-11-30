<script setup lang="ts">
const props = defineProps<{
    status:
        | 'not_published'
        | 'scheduled'
        | 'publishing'
        | 'published'
        | 'failed'
        | null
        | string;
}>();

const labelMap: Record<string, string> = {
    not_published: 'Not published',
    scheduled: 'Scheduled',
    publishing: 'Publishing…',
    published: 'Published',
    failed: 'Failed',
};

const colorMap: Record<string, string> = {
    not_published:
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
    scheduled:
        'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-100',
    publishing: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100',
    published:
        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100',
};

const label = labelMap[props.status || ''] || 'Unknown';
const colorClass =
    colorMap[props.status || ''] ||
    'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200';
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium"
        :class="colorClass"
    >
        {{ label }}
    </span>
</template>
