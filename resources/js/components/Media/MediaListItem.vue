<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import type { MediaItem } from '@/types/media';
import { Eye, FileText } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    media: MediaItem;
    selected: boolean;
}>();

const emit = defineEmits<{
    (event: 'toggle', value: boolean): void;
    (event: 'preview'): void;
}>();

const isImage = computed(() => props.media.mime_type.startsWith('image/'));

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
        <td class="px-4 py-3">
            <Checkbox :checked="selected" @update:model-value="emit('toggle', $event === true)" />
        </td>
        <td class="px-4 py-3">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-16 items-center justify-center overflow-hidden rounded-md bg-gray-50 dark:bg-gray-800">
                    <img
                        v-if="isImage && media.temporary_url"
                        :src="media.temporary_url"
                        :alt="media.filename"
                        class="h-full w-full object-cover"
                    />
                    <FileText v-else class="h-6 w-6 text-gray-500" />
                </div>
                <div>
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
            <Button variant="outline" size="sm" class="flex items-center gap-2" @click.prevent="emit('preview')">
                <Eye class="h-4 w-4" />
                View
            </Button>
        </td>
    </tr>
</template>
