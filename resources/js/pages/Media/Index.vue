<script setup lang="ts">
import MediaBulkActions from '@/components/Media/MediaBulkActions.vue';
import MediaCard from '@/components/Media/MediaCard.vue';
import MediaFilters from '@/components/Media/MediaFilters.vue';
import MediaListItem from '@/components/Media/MediaListItem.vue';
import MediaPreview from '@/components/Media/MediaPreview.vue';
import MediaTagInput from '@/components/Media/MediaTagInput.vue';
import MediaUploader from '@/components/Media/MediaUploader.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Pagination } from '@/components/ui/pagination';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { MediaItem, MediaTag } from '@/types/media';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { GalleryHorizontal, GalleryVertical, Tags, UploadCloud } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { toast } from '@/components/ui/sonner';
import { useMediaFilters, type MediaFilters as MediaFiltersState } from '@/composables/useMediaFilters';

interface MediaPagination {
    data: MediaItem[];
    links: any[];
    meta?: {
        from?: number;
        to?: number;
        total?: number;
    };
}

interface Props {
    media: MediaPagination;
    filters: Partial<MediaFiltersState>;
    tags: MediaTag[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Media', href: '/media' }];

const availableTags = ref<MediaTag[]>([...props.tags]);
const { filters, normalizedFilters, resetFilters } = useMediaFilters(props.filters);

const selectedIds = ref<number[]>([]);
const viewMode = ref<'gallery' | 'list'>(localStorage.getItem('media.viewMode') === 'list' ? 'list' : 'gallery');
const showUploader = ref(false);
const previewOpen = ref(false);
const previewMedia = ref<MediaItem | null>(null);
const tagDialogOpen = ref(false);
const bulkTagIds = ref<number[]>([]);
const isCreatingTag = ref(false);
const bulkTagMode = ref<'add' | 'remove'>('add');
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const allSelected = computed(() => {
    return props.media.data.length > 0 && selectedIds.value.length === props.media.data.length;
});

const paginationInfo = computed(() => {
    const from = props.media.meta?.from ?? (props.media.data.length ? 1 : 0);
    const to = props.media.meta?.to ?? props.media.data.length;
    const total = props.media.meta?.total ?? props.media.data.length;

    return { from, to, total };
});

watch(viewMode, (mode) => {
    localStorage.setItem('media.viewMode', mode);
});

watch(
    () => props.media.data,
    (items) => {
        const availableIds = items.map((item) => item.id);
        selectedIds.value = selectedIds.value.filter((id) => availableIds.includes(id));
    }
);

watch(
    () => props.tags,
    (value) => {
        availableTags.value = [...value];
    }
);

onBeforeUnmount(() => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
});

const applyFilters = () => {
    router.get('/media', normalizedFilters.value, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const scheduleFilters = () => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
    debounceTimer = setTimeout(applyFilters, 350);
};

const applyFilterChanges = (nextFilters: MediaFiltersState) => {
    Object.assign(filters, nextFilters);
    scheduleFilters();
};

const clearFilters = () => {
    resetFilters();
    applyFilters();
};

const toggleSelection = (id: number, checked: boolean) => {
    if (checked) {
        if (!selectedIds.value.includes(id)) {
            selectedIds.value.push(id);
        }
    } else {
        selectedIds.value = selectedIds.value.filter((selectedId) => selectedId !== id);
    }
};

const toggleAll = (checked: boolean) => {
    if (checked) {
        selectedIds.value = props.media.data.map((item) => item.id);
    } else {
        selectedIds.value = [];
    }
};

const togglePageSelection = () => {
    if (allSelected.value) {
        selectedIds.value = [];
    } else {
        toggleAll(true);
    }
};

const sortBy = (column: MediaFiltersState['sort_by']) => {
    if (filters.sort_by === column) {
        filters.sort_dir = filters.sort_dir === 'asc' ? 'desc' : 'asc';
    } else {
        filters.sort_by = column;
        filters.sort_dir = column === 'filename' ? 'asc' : 'desc';
    }

    applyFilters();
};

const sortIcon = (column: MediaFiltersState['sort_by']) => {
    if (filters.sort_by !== column) {
        return '';
    }

    return filters.sort_dir === 'asc' ? '↑' : '↓';
};

const openPreview = (media: MediaItem) => {
    previewMedia.value = media;
    previewOpen.value = true;
};

const refreshMedia = () => {
    router.get('/media', normalizedFilters(), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const handleBulkDelete = async () => {
    if (selectedIds.value.length === 0) {
        return;
    }
    try {
        await axios.post('/media/bulk-delete', { media_ids: selectedIds.value });
        toast.success('Media deleted');
        selectedIds.value = [];
        refreshMedia();
    } catch (error) {
        console.error(error);
        toast.error('Unable to delete media right now.');
    }
};

const openBulkTagDialog = (mode: 'add' | 'remove') => {
    bulkTagMode.value = mode;
    bulkTagIds.value = [];
    tagDialogOpen.value = true;
};

const confirmBulkTag = async () => {
    if (selectedIds.value.length === 0 || bulkTagIds.value.length === 0) {
        toast.info('Select media and at least one tag.');
        return;
    }
    try {
        await axios.post('/media/bulk-tag', {
            media_ids: selectedIds.value,
            tag_ids: bulkTagIds.value,
            action: bulkTagMode.value === 'add' ? 'add_tags' : 'remove_tags',
        });
        toast.success('Tags updated');
        tagDialogOpen.value = false;
        refreshMedia();
    } catch (error) {
        console.error(error);
        toast.error('Unable to update tags.');
    }
};

const handleCreateTag = async (name: string) => {
    if (isCreatingTag.value) {
        return;
    }

    isCreatingTag.value = true;

    try {
        const response = await axios.post('/media-tags', { name });
        const newTag = response.data.tag as MediaTag;

        availableTags.value = [...availableTags.value, newTag].sort((a, b) => a.name.localeCompare(b.name));
        bulkTagIds.value = Array.from(new Set([...bulkTagIds.value, newTag.id]));

        toast.success('Tag created');
    } catch (error) {
        console.error(error);

        if (axios.isAxiosError(error) && error.response?.status === 422) {
            const message =
                (error.response.data?.errors?.name && error.response.data.errors.name[0]) ||
                'Tag name must be unique.';

            toast.error(message);
        } else {
            toast.error('Unable to create tag right now.');
        }
    } finally {
        isCreatingTag.value = false;
    }
};

const onUploaded = () => {
    showUploader.value = false;
    refreshMedia();
};
</script>

<template>
    <Head title="Media Gallery" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <div class="flex flex-wrap items-center gap-4">
                <div>
                    <h1 class="text-3xl font-semibold text-gray-900 dark:text-gray-50">Media Gallery</h1>
                    <p class="text-sm text-gray-500">Upload images and PDFs, organize with tags, and manage them in bulk.</p>
                </div>
                <div class="ml-auto flex items-center gap-3">
                    <Button variant="outline" as-child>
                        <a href="/media-tags" class="flex items-center gap-2">
                            <Tags class="h-4 w-4" />
                            Manage Tags
                        </a>
                    </Button>
                    <Button variant="ghost" size="sm" @click="togglePageSelection">
                        {{ allSelected ? 'Clear selection' : 'Select page' }}
                    </Button>
                    <div class="flex rounded-full border border-gray-200 bg-white p-1 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <Button
                            size="icon"
                            variant="ghost"
                            :class="viewMode === 'gallery' ? 'bg-gray-100 dark:bg-gray-800' : ''"
                            @click="viewMode = 'gallery'"
                        >
                            <GalleryHorizontal class="h-5 w-5" />
                        </Button>
                        <Button
                            size="icon"
                            variant="ghost"
                            :class="viewMode === 'list' ? 'bg-gray-100 dark:bg-gray-800' : ''"
                            @click="viewMode = 'list'"
                        >
                            <GalleryVertical class="h-5 w-5" />
                        </Button>
                    </div>
                    <Button class="gap-2" @click="showUploader = true">
                        <UploadCloud class="h-4 w-4" />
                        Upload
                    </Button>
                </div>
            </div>

            <MediaFilters :filters="filters" :tags="availableTags" @clear="clearFilters" @change="applyFilterChanges" />

            <div v-if="media.data.length === 0" class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 p-10 text-center dark:border-gray-800">
                <p class="text-lg font-semibold text-gray-800 dark:text-gray-100">No media yet</p>
                <p class="text-sm text-gray-500">Upload images or PDFs to see them here.</p>
                <Button class="mt-4" @click="showUploader = true">Upload Media</Button>
            </div>

            <div v-else>
                <div
                    v-if="viewMode === 'gallery'"
                    class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 2xl:grid-cols-8"
                >
                    <MediaCard
                        v-for="item in media.data"
                        :key="item.id"
                        :media="item"
                        :selected="selectedIds.includes(item.id)"
                        @toggle="toggleSelection(item.id, $event)"
                        @preview="openPreview(item)"
                    />
                </div>

                <div v-else class="overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-gray-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-900/60">
                            <tr>
                                <th class="px-4 py-3 align-middle">
                                    <Checkbox :model-value="allSelected" @update:model-value="toggleAll($event === true)" />
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-700 dark:hover:text-gray-200 cursor-pointer"
                                    @click="sortBy('filename')"
                                >
                                    File {{ sortIcon('filename') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tags</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Size</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-700 dark:hover:text-gray-200 cursor-pointer"
                                    @click="sortBy('uploaded_at')"
                                >
                                    Uploaded {{ sortIcon('uploaded_at') }}
                                </th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            <MediaListItem
                                v-for="item in media.data"
                                :key="item.id"
                                :media="item"
                                :selected="selectedIds.includes(item.id)"
                                @toggle="toggleSelection(item.id, $event)"
                                @preview="openPreview(item)"
                            />
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex items-center justify-between text-sm text-gray-500">
                    <div>Showing {{ paginationInfo.from }} to {{ paginationInfo.to }} of {{ paginationInfo.total }} results</div>
                    <Pagination :links="media.links" />
                </div>
            </div>
        </div>
    </AppLayout>

    <MediaUploader :open="showUploader" @update:open="showUploader = $event" @uploaded="onUploaded" />

    <MediaPreview v-model:open="previewOpen" :media="previewMedia" />

    <MediaBulkActions
        :count="selectedIds.length"
        @delete="handleBulkDelete"
        @add-tags="openBulkTagDialog('add')"
        @remove-tags="openBulkTagDialog('remove')"
        @clear="selectedIds = []"
    />

    <Dialog v-model:open="tagDialogOpen">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ bulkTagMode === 'add' ? 'Add tags' : 'Remove tags' }}</DialogTitle>
                <DialogDescription>Select which tags to {{ bulkTagMode === 'add' ? 'add' : 'remove' }} for the selected media.</DialogDescription>
            </DialogHeader>
            <MediaTagInput
                v-model="bulkTagIds"
                :tags="availableTags"
                allow-create
                :creating="isCreatingTag"
                @create="handleCreateTag"
            />
            <DialogFooter>
                <Button variant="ghost" @click="tagDialogOpen = false">Cancel</Button>
                <Button type="button" @click="confirmBulkTag">Apply</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
