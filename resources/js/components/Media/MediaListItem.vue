<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import type { MediaItem } from '@/types/media';
import { Download, Eye, FileText, RefreshCw } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps<{
    media: MediaItem;
    selected: boolean;
}>();

const emit = defineEmits<{
    (event: 'toggle', value: boolean): void;
    (event: 'preview'): void;
}>();

const isImage = computed(() => props.media.mime_type.startsWith('image/'));

const tempUrl = ref(props.media.temporary_url ?? null);
const isRefreshing = ref(false);

watch(
    () => props.media.temporary_url,
    (value) => {
        tempUrl.value = value ?? null;
    }
);

const refreshUrl = async () => {
    if (isRefreshing.value) {
        return;
    }

    isRefreshing.value = true;

    try {
        const response = await axios.get(`/media/${props.media.id}/temporary`);
        tempUrl.value = response.data?.temporary_url ?? null;
    } catch (error) {
        console.error(error);
    } finally {
        isRefreshing.value = false;
    }
};

const formattedSize = computed(() => {
    const size = props.media.file_size;
    if (!size && size !== 0) {
        return '';
    }
    if (size >= 1024 * 1024) {
        return `${(size / 1024 / 1024).toFixed(1)} MB`;
    }
    return `${(size / 1024).toFixed(0)} KB`;
});

const createdDisplay = computed(() => {
    if (!props.media.created_at) {
        return '';
    }
    return new Date(props.media.created_at).toLocaleString();
});
</script>

<template>
    <tr class="border-b border-gray-100 dark:border-gray-800">
        <td class="px-4 py-3 align-middle">
            <Checkbox :model-value="selected" @update:model-value="emit('toggle', $event === true)" />
        </td>
        <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="flex h-12 w-16 items-center justify-center overflow-hidden rounded-md bg-gray-50 transition hover:opacity-80 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:bg-gray-800"
                        @click="emit('preview')"
                    >
                    <img
                        v-if="isImage && tempUrl"
                        :src="tempUrl"
                        :alt="media.filename"
                        class="h-full w-full object-cover"
                        @error="refreshUrl"
                    />
                    <FileText v-else class="h-6 w-6 text-gray-500" />
                </button>
                <div class="min-w-0">
                    <p class="max-w-xs truncate text-sm font-semibold text-gray-900 dark:text-gray-50">{{ media.filename }}</p>
                    <p class="text-xs text-gray-500">{{ isImage ? 'Image' : 'PDF' }}</p>
                </div>
            </div>
        </td>
        <td class="px-4 py-3">
            <div class="flex flex-wrap gap-2">
                <Badge v-for="tag in media.tags" :key="tag.id" variant="secondary" class="bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-100">
                    {{ tag.name }}
                </Badge>
                <span v-if="media.tags.length === 0" class="text-xs text-gray-400">No tags</span>
            </div>
        </td>
        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
            {{ formattedSize }}
        </td>
        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
            {{ createdDisplay }}
        </td>
        <td class="px-4 py-3 text-right">
            <div class="flex justify-end gap-2">
                <Button variant="outline" size="sm" class="flex items-center gap-2" @click.prevent="emit('preview')">
                    <Eye class="h-4 w-4" />
                    View
                </Button>
                <Button
                    v-if="isImage && !tempUrl"
                    variant="outline"
                    size="sm"
                    class="flex items-center gap-2"
                    :disabled="isRefreshing"
                    @click="refreshUrl"
                >
                    <RefreshCw class="h-4 w-4" />
                    {{ isRefreshing ? 'Refreshing…' : 'Refresh link' }}
                </Button>
                <Button
                    v-if="media.download_url || tempUrl"
                    variant="outline"
                    size="sm"
                    class="flex items-center gap-2"
                    as-child
                >
                    <a :href="media.download_url || tempUrl" download>
                        <Download class="h-4 w-4" />
                        Download
                    </a>
                </Button>
            </div>
        </td>
    </tr>
</template>
