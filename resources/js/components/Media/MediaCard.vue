<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import type { MediaItem } from '@/types/media';
import axios from 'axios';
import {
    Download,
    FileText,
    Image as ImageIcon,
    RefreshCw,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    media: MediaItem;
    selected: boolean;
}>();

const emit = defineEmits<{
    (event: 'toggle', value: boolean): void;
    (event: 'preview'): void;
}>();

const isImage = computed(() => props.media.mime_type.startsWith('image/'));

const tempUrl = ref(props.media.temporary_url);
const isRefreshing = ref(false);
const loadError = ref(false);

watch(
    () => props.media.temporary_url,
    (value) => {
        tempUrl.value = value;
        loadError.value = false;
    },
);

const refreshUrl = async () => {
    if (isRefreshing.value) {
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
    return new Date(props.media.created_at).toLocaleDateString();
});
</script>

<template>
    <div
        class="group relative flex h-full cursor-pointer flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-gray-800 dark:bg-gray-900"
        @click="emit('preview')"
    >
        <div class="relative w-full pb-[100%]">
            <div
                class="absolute inset-0 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900"
            >
                <img
                    v-if="isImage && tempUrl"
                    :src="tempUrl"
                    :alt="media.filename"
                    class="h-full w-full object-cover"
                    @error="loadError = true"
                />
                <div
                    v-else
                    class="flex h-full items-center justify-center text-gray-500"
                >
                    <div
                        v-if="isImage"
                        class="flex flex-col items-center gap-2 text-xs text-gray-500"
                    >
                        <RefreshCw class="h-6 w-6" />
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-2 py-1 text-xs hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-800"
                            :disabled="isRefreshing"
                            @click.stop="refreshUrl"
                        >
                            {{
                                isRefreshing ? 'Refreshing...' : 'Refresh link'
                            }}
                        </button>
                        <span v-if="loadError" class="text-[11px] text-red-500"
                            >Unable to load preview.</span
                        >
                    </div>
                    <FileText v-else class="h-10 w-10" />
                </div>
                <div class="absolute top-3 left-3 z-10" @click.stop>
                    <Checkbox
                        :model-value="selected"
                        @update:model-value="emit('toggle', $event === true)"
                    />
                </div>
                <a
                    v-if="media.download_url || media.temporary_url"
                    :href="media.download_url || media.temporary_url"
                    download
                    class="absolute top-3 right-3 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-black/70 text-white opacity-0 transition group-hover:opacity-100 hover:bg-black focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black"
                    @click.stop
                >
                    <Download class="h-4 w-4" />
                </a>
                <div
                    class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 transition group-hover:opacity-80"
                />
                <div
                    class="pointer-events-none absolute inset-x-0 bottom-0 flex items-center justify-between px-4 pb-3 text-xs text-white opacity-0 transition group-hover:opacity-100"
                >
                    <span class="flex items-center gap-2">
                        <component
                            :is="isImage ? ImageIcon : FileText"
                            class="h-4 w-4"
                        />
                        {{ isImage ? 'Image' : 'PDF' }}
                    </span>
                    <span>{{ formattedSize }}</span>
                </div>
            </div>
        </div>
        <div class="flex flex-1 flex-col gap-2 p-4">
            <div class="flex items-start justify-between gap-2">
                <p
                    class="line-clamp-2 text-sm font-semibold text-gray-900 dark:text-gray-50"
                >
                    {{ media.filename }}
                </p>
                <span class="text-xs text-gray-500">{{ formattedSize }}</span>
            </div>
            <div class="flex flex-wrap gap-2">
                <Badge
                    v-for="tag in media.tags"
                    :key="tag.id"
                    variant="secondary"
                    class="bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-100"
                >
                    {{ tag.name }}
                </Badge>
                <span
                    v-if="media.tags.length === 0"
                    class="text-xs text-gray-400"
                    >No tags</span
                >
            </div>
            <div class="mt-auto text-xs text-gray-500 dark:text-gray-400">
                Added {{ createdDisplay }}
            </div>
        </div>
    </div>
</template>
