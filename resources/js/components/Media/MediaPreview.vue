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
import { computed, ref, watch } from 'vue';
import { Download, RefreshCw } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps<{
    open: boolean;
    media: MediaItem | null;
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
}>();

const isImage = computed(() => props.media?.mime_type.startsWith('image/'));
const tempUrl = ref<string | null>(props.media?.temporary_url ?? null);
const isRefreshing = ref(false);
const loadError = ref(false);

watch(
    () => props.media,
    (value) => {
        tempUrl.value = value?.temporary_url ?? null;
        loadError.value = false;
    }
);

const refreshUrl = async () => {
    if (!props.media || isRefreshing.value) {
        return;
    }

    isRefreshing.value = true;
    loadError.value = false;

    try {
        const response = await axios.get(`/media/${props.media.id}/temporary`);
        tempUrl.value = response.data?.temporary_url ?? null;
    } catch (error) {
        console.error(error);
        loadError.value = true;
    } finally {
        isRefreshing.value = false;
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-4xl pr-12">
            <DialogHeader>
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1">
                        <DialogTitle>{{ media?.filename }}</DialogTitle>
                        <DialogDescription>
                            {{ media?.mime_type }} ·
                            <span v-if="media?.file_size">{{ (media.file_size / 1024 / 1024).toFixed(2) }} MB</span>
                        </DialogDescription>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <Button v-if="media?.download_url || tempUrl" size="sm" variant="outline" as-child>
                            <a :href="media?.download_url || tempUrl" download class="inline-flex items-center gap-2">
                                <Download class="h-4 w-4" />
                                Download
                            </a>
                        </Button>
                    </div>
                </div>
            </DialogHeader>

            <div v-if="media" class="rounded-lg border border-dashed border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900">
                <img
                    v-if="isImage && tempUrl"
                    :src="tempUrl"
                    :alt="media.filename"
                    class="mx-auto max-h-[70vh] w-full rounded-lg object-contain"
                    @error="loadError = true"
                />
                <div v-else class="flex h-[70vh] items-center justify-center">
                    <object v-if="media.temporary_url" :data="media.temporary_url" type="application/pdf" class="h-full w-full">
                        <p class="text-center text-sm text-gray-500">Unable to preview this file.</p>
                    </object>
                    <div v-else class="flex flex-col items-center gap-2 text-sm text-gray-500">
                        <p>Preview unavailable.</p>
                        <Button size="sm" variant="outline" class="gap-2" :disabled="isRefreshing" @click="refreshUrl">
                            <RefreshCw class="h-4 w-4" />
                            {{ isRefreshing ? 'Refreshing...' : 'Refresh link' }}
                        </Button>
                        <p v-if="loadError" class="text-xs text-red-500">Unable to load preview.</p>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
    </template>
