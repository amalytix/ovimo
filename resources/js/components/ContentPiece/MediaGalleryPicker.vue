<script setup lang="ts">
import MediaCard from '@/components/Media/MediaCard.vue';
import MediaFilters from '@/components/Media/MediaFilters.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useMediaFilters } from '@/composables/useMediaFilters';
import type { MediaItem, MediaTag } from '@/types/media';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    selectedIds: number[];
    media: MediaItem[];
    tags: MediaTag[];
    multiSelect?: boolean;
    maxSelection?: number;
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'select', value: MediaItem[]): void;
}>();

const multiSelect = computed(() => props.multiSelect !== false);
const maxSelection = computed(() => props.maxSelection ?? 100);

const { filters, resetFilters } = useMediaFilters();

const page = ref(1);
const perPage = 12;

const selection = ref<number[]>([...props.selectedIds]);

watch(
    () => props.selectedIds,
    (value) => {
        selection.value = [...value];
    },
);

const filteredMedia = computed(() => {
    let items = [...props.media];
    if (filters.search) {
        const query = filters.search.toLowerCase();
        items = items.filter((item) =>
            item.filename.toLowerCase().includes(query),
        );
    }
    if (filters.file_type === 'images') {
        items = items.filter((item) => item.mime_type.startsWith('image/'));
    } else if (filters.file_type === 'pdfs') {
        items = items.filter((item) => item.mime_type === 'application/pdf');
    }
    if (filters.tag_ids.length) {
        items = items.filter((item) =>
            item.tags.some((tag) => filters.tag_ids.includes(tag.id)),
        );
    }
    return items;
});

const paginatedMedia = computed(() => {
    const start = (page.value - 1) * perPage;
    return filteredMedia.value.slice(start, start + perPage);
});

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredMedia.value.length / perPage)),
);

const toggleSelection = (mediaId: number, value: boolean) => {
    if (value) {
        if (multiSelect.value) {
            if (
                !selection.value.includes(mediaId) &&
                selection.value.length >= maxSelection.value
            ) {
                return;
            }
            selection.value = Array.from(
                new Set([...selection.value, mediaId]),
            );
        } else {
            selection.value = [mediaId];
        }
    } else {
        selection.value = selection.value.filter((id) => id !== mediaId);
    }
};

const handleConfirm = () => {
    const chosen = props.media.filter((item) =>
        selection.value.includes(item.id),
    );
    emit('select', chosen);
    emit('update:open', false);
};

const handleClose = () => emit('update:open', false);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="w-[90vw] max-w-[90vw] sm:max-w-[90vw]">
            <DialogHeader>
                <DialogTitle>Select Media</DialogTitle>
            </DialogHeader>

            <div class="flex max-h-[80vh] flex-col space-y-4 overflow-hidden">
                <MediaFilters
                    :filters="filters"
                    :tags="tags"
                    @change="(value) => Object.assign(filters, value)"
                    @clear="resetFilters"
                />

                <div class="flex-1 overflow-y-auto pr-1">
                    <div
                        class="grid grid-cols-1 gap-3 md:grid-cols-4 lg:grid-cols-8"
                    >
                        <MediaCard
                            v-for="item in paginatedMedia"
                            :key="item.id"
                            :media="item"
                            :selected="selection.includes(item.id)"
                            @toggle="toggleSelection(item.id, $event)"
                            @preview="
                                toggleSelection(
                                    item.id,
                                    !selection.includes(item.id),
                                )
                            "
                        />
                    </div>

                    <div
                        v-if="filteredMedia.length === 0"
                        class="mt-4 rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground"
                    >
                        No media matches your filters.
                    </div>
                </div>

                <div
                    class="flex items-center justify-between text-sm text-muted-foreground"
                >
                    <span>{{ filteredMedia.length }} files</span>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="page <= 1"
                            @click="page = Math.max(1, page - 1)"
                            >Prev</Button
                        >
                        <span>Page {{ page }} / {{ totalPages }}</span>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="page >= totalPages"
                            @click="page = Math.min(totalPages, page + 1)"
                            >Next</Button
                        >
                    </div>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="secondary" @click="handleClose">Cancel</Button>
                <Button
                    :disabled="selection.length === 0"
                    @click="handleConfirm"
                >
                    {{ multiSelect ? 'Select Media' : 'Use Image' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
