<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { MediaTag } from '@/types/media';
import type { MediaFilters as Filters } from '@/composables/useMediaFilters';
import MediaTagInput from './MediaTagInput.vue';

const props = defineProps<{
    filters: Filters;
    tags: MediaTag[];
}>();

const emit = defineEmits<{
    (event: 'clear'): void;
    (event: 'change', value: Filters): void;
}>();

const setFileType = (type: Filters['file_type']) => {
    emit('change', { ...props.filters, file_type: type });
};

const clearFilters = () => {
    emit('change', {
        ...props.filters,
        search: '',
        tag_ids: [],
        file_type: 'all',
        date_from: null,
        date_to: null,
        sort_by: 'uploaded_at',
        sort_dir: 'desc',
    });
    emit('clear');
};

const updateFilter = <K extends keyof Filters>(key: K, value: Filters[K]) => {
    emit('change', { ...props.filters, [key]: value });
};

const updateTags = (value: number[]) => {
    emit('change', { ...props.filters, tag_ids: value });
};
</script>

<template>
    <div class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-center gap-3">
            <Input
                :model-value="filters.search"
                type="text"
                placeholder="Search filename"
                class="w-full flex-1 min-w-[220px]"
                @update:model-value="updateFilter('search', $event)"
            />
            <div class="flex items-center gap-2">
                <Button
                    size="sm"
                    variant="outline"
                    :class="filters.file_type === 'all' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : ''"
                    @click="setFileType('all')"
                >
                    All
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    :class="filters.file_type === 'images' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : ''"
                    @click="setFileType('images')"
                >
                    Images
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    :class="filters.file_type === 'pdfs' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : ''"
                    @click="setFileType('pdfs')"
                >
                    PDFs
                </Button>
            </div>
            <div class="flex items-center gap-2">
                <Input :model-value="filters.date_from" type="date" class="w-36" @update:model-value="updateFilter('date_from', $event)" />
                <span class="text-sm text-gray-500">to</span>
                <Input :model-value="filters.date_to" type="date" class="w-36" @update:model-value="updateFilter('date_to', $event)" />
            </div>
            <Button variant="ghost" size="sm" class="ml-auto" @click="clearFilters">
                Clear
            </Button>
        </div>
        <div class="flex flex-wrap items-start gap-3">
            <MediaTagInput class="flex-1" :model-value="filters.tag_ids" :tags="tags" placeholder="Filter tags" @update:model-value="updateTags" />
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-600 dark:text-gray-300">Sort</span>
                <select
                    class="py-1 h-9.5 rounded-md border border-gray-200 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-gray-100"
                    :value="`${filters.sort_by}:${filters.sort_dir}`"
                    @change="
                        updateFilter('sort_by', ($event.target as HTMLSelectElement).value.split(':')[0] as Filters['sort_by']);
                        updateFilter('sort_dir', ($event.target as HTMLSelectElement).value.split(':')[1] as Filters['sort_dir']);
                    "
                >
                    <option value="uploaded_at:desc">Uploaded: Newest</option>
                    <option value="uploaded_at:asc">Uploaded: Oldest</option>
                    <option value="filename:asc">Filename: A–Z</option>
                    <option value="filename:desc">Filename: Z–A</option>
                </select>
            </div>
        </div>
    </div>
</template>
