<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { MediaTag } from '@/types/media';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: number[];
    tags: MediaTag[];
    placeholder?: string;
    allowCreate?: boolean;
    creating?: boolean;
}>();

const emit = defineEmits<{
    (event: 'update:modelValue', value: number[]): void;
    (event: 'create', value: string): void;
}>();

const search = ref('');
const internalSelection = ref<number[]>([...props.modelValue]);

watch(
    () => props.modelValue,
    (value) => {
        internalSelection.value = [...value];
    }
);

const filteredTags = computed(() => {
    if (!search.value) {
        return props.tags;
    }
    return props.tags.filter((tag) => tag.name.toLowerCase().includes(search.value.toLowerCase()));
});

const trimmedSearch = computed(() => search.value.trim());

const canCreate = computed(() => {
    if (!props.allowCreate || !trimmedSearch.value) {
        return false;
    }

    return !props.tags.some((tag) => tag.name.toLowerCase() === trimmedSearch.value.toLowerCase());
});

const toggle = (id: number) => {
    if (internalSelection.value.includes(id)) {
        internalSelection.value = internalSelection.value.filter((tagId) => tagId !== id);
    } else {
        internalSelection.value = [...internalSelection.value, id];
    }
    emit('update:modelValue', internalSelection.value);
};

const createTag = () => {
    if (!canCreate.value || props.creating) {
        return;
    }

    emit('create', trimmedSearch.value);
    search.value = '';
};
</script>

<template>
    <div class="flex flex-col gap-2">
        <Input
            v-model="search"
            type="text"
            :placeholder="placeholder || 'Search tags'"
            @keydown.enter.prevent="createTag"
        />
        <div v-if="canCreate" class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
            <Button size="sm" :disabled="creating" @click="createTag">
                {{ creating ? 'Adding…' : `Add \"${trimmedSearch}\"` }}
            </Button>
            <span class="text-xs text-gray-500">Press Enter to add</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <Button
                v-for="tag in filteredTags"
                :key="tag.id"
                variant="outline"
                size="sm"
                class="gap-2 border-dashed"
                :class="internalSelection.includes(tag.id) ? 'bg-blue-600 text-white hover:bg-blue-700' : ''"
                @click="toggle(tag.id)"
            >
                <Badge
                    v-if="internalSelection.includes(tag.id)"
                    variant="secondary"
                    class="bg-white/20 text-white"
                >
                    ✓
                </Badge>
                {{ tag.name }}
            </Button>
        </div>
    </div>
</template>
