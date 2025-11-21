<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { MediaTag } from '@/types/media';
import { nextTick, ref, watch } from 'vue';
import MediaTagInput from './MediaTagInput.vue';

interface Filters {
    search: string;
    tag_ids: number[];
    file_type: 'all' | 'images' | 'pdfs';
    date_from: string | null;
    date_to: string | null;
}

const props = defineProps<{
    modelValue: Filters;
    tags: MediaTag[];
}>();

const emit = defineEmits<{
    (event: 'update:modelValue', value: Filters): void;
    (event: 'clear'): void;
    (event: 'change'): void;
}>();

const cloneFilters = (value: Filters): Filters => ({
    ...value,
    tag_ids: [...value.tag_ids],
});

const isSyncingFromParent = ref(false);

const localFilters = ref<Filters>(cloneFilters(props.modelValue));

watch(
    () => props.modelValue,
    (value) => {
        isSyncingFromParent.value = true;
        localFilters.value = cloneFilters(value);
        nextTick(() => {
            isSyncingFromParent.value = false;
        });
    }
);

watch(
    localFilters,
    (value) => {
        if (isSyncingFromParent.value) {
            return;
        }

        emit('update:modelValue', cloneFilters(value));
        emit('change');
    },
    { deep: true }
);

const setFileType = (type: Filters['file_type']) => {
    localFilters.value.file_type = type;
};

const clearFilters = () => {
    localFilters.value = {
        search: '',
        tag_ids: [],
        file_type: 'all',
        date_from: null,
        date_to: null,
    };
    emit('clear');
};
</script>

<template>
    <div class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-center gap-3">
            <Input
                v-model="localFilters.search"
                type="text"
                placeholder="Search filename"
                class="w-full flex-1 min-w-[220px]"
            />
            <div class="flex items-center gap-2">
                <Button
                    size="sm"
                    variant="outline"
                    :class="localFilters.file_type === 'all' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : ''"
                    @click="setFileType('all')"
                >
                    All
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    :class="localFilters.file_type === 'images' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : ''"
                    @click="setFileType('images')"
                >
                    Images
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    :class="localFilters.file_type === 'pdfs' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : ''"
                    @click="setFileType('pdfs')"
                >
                    PDFs
                </Button>
            </div>
            <div class="flex items-center gap-2">
                <Input v-model="localFilters.date_from" type="date" class="w-36" />
                <span class="text-sm text-gray-500">to</span>
                <Input v-model="localFilters.date_to" type="date" class="w-36" />
            </div>
            <Button variant="ghost" size="sm" class="ml-auto" @click="clearFilters">
                Clear
            </Button>
        </div>
        <MediaTagInput v-model="localFilters.tag_ids" :tags="tags" placeholder="Filter tags" />
    </div>
</template>
