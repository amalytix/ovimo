<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import type { MediaItem } from '@/types/media';
import { computed } from 'vue';
import { Download } from 'lucide-vue-next';

const props = defineProps<{
    open: boolean;
    media: MediaItem | null;
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
}>();

const isImage = computed(() => props.media?.mime_type.startsWith('image/'));
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-4xl">
            <DialogHeader>
                <div class="flex items-start justify-between gap-3 pr-12">
                    <div class="space-y-1">
                        <DialogTitle>{{ media?.filename }}</DialogTitle>
                        <DialogDescription>
                            {{ media?.mime_type }} ·
                            <span v-if="media?.file_size">{{ (media.file_size / 1024 / 1024).toFixed(2) }} MB</span>
                        </DialogDescription>
                    </div>
                    <div class="flex items-start gap-2 pt-0.5">
                        <Button v-if="media?.download_url || media?.temporary_url" size="sm" variant="outline" as-child>
                            <a :href="media?.download_url || media?.temporary_url" download class="inline-flex items-center gap-2">
                                <Download class="h-4 w-4" />
                                Download
                            </a>
                        </Button>
                        <Dialog.Title class="sr-only">Close</Dialog.Title>
                    </div>
                </div>
            </DialogHeader>

            <div v-if="media" class="rounded-lg border border-dashed border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900">
                <img
                    v-if="isImage && media.temporary_url"
                    :src="media.temporary_url"
                    :alt="media.filename"
                    class="mx-auto max-h-[70vh] w-full rounded-lg object-contain"
                />
                <div v-else class="flex h-[70vh] items-center justify-center">
                    <object v-if="media.temporary_url" :data="media.temporary_url" type="application/pdf" class="h-full w-full">
                        <p class="text-center text-sm text-gray-500">Unable to preview this file.</p>
                    </object>
                    <p v-else class="text-sm text-gray-500">Preview unavailable.</p>
                </div>
            </div>
        </DialogContent>
    </Dialog>
    </template>
