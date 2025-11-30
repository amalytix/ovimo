<script setup lang="ts">
interface Props {
    title: string;
    value: string | number;
    href?: string;
}

defineProps<Props>();

const formatNumber = (num: number | string) => {
    if (typeof num === 'string') return num;
    return new Intl.NumberFormat().format(num);
};
</script>

<template>
    <component
        :is="href ? 'a' : 'div'"
        :href="href"
        class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
        :class="{
            'transition-colors hover:bg-gray-50 dark:hover:bg-gray-700': href,
        }"
    >
        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
            {{ title }}
        </div>
        <div class="mt-2 text-3xl font-bold">{{ formatNumber(value) }}</div>
    </component>
</template>
