<script setup lang="ts">
import type { ToasterProps } from 'vue-sonner';
import { Toaster as Sonner } from 'vue-sonner';

const props = withDefaults(defineProps<ToasterProps>(), {
    position: 'top-right',
    theme: 'system',
    richColors: false,
    closeButton: true,
    duration: 3000,
    toastOptions: () => ({
        class: 'bg-card text-card-foreground border border-border shadow-lg rounded-md',
        descriptionClass: 'text-muted-foreground',
        closeButton: true,
    }),
});
</script>

<template>
    <Sonner v-bind="props" />
</template>
