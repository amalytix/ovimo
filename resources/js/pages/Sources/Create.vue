<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Tag {
    id: number;
    name: string;
}

interface Props {
    tags: Tag[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sources', href: '/sources' },
    { title: 'Create', href: '/sources/create' },
];

const form = useForm({
    internal_name: '',
    type: 'RSS',
    url: '',
    monitoring_interval: 'DAILY',
    is_active: true,
    should_notify: false,
    auto_summarize: true,
    tags: [] as string[],
});

const newTagInput = ref('');

const submit = () => {
    form.post('/sources');
};

const sourceTypes = [
    { value: 'RSS', label: 'RSS Feed' },
    { value: 'XML_SITEMAP', label: 'XML Sitemap' },
];

const intervals = [
    { value: 'EVERY_10_MIN', label: 'Every 10 minutes' },
    { value: 'EVERY_30_MIN', label: 'Every 30 minutes' },
    { value: 'HOURLY', label: 'Hourly' },
    { value: 'EVERY_6_HOURS', label: 'Every 6 hours' },
    { value: 'DAILY', label: 'Daily' },
    { value: 'WEEKLY', label: 'Weekly' },
];

const addTag = (tagName: string) => {
    const trimmed = tagName.trim();
    if (trimmed && !form.tags.includes(trimmed)) {
        form.tags.push(trimmed);
    }
};

const removeTag = (tagName: string) => {
    form.tags = form.tags.filter((t) => t !== tagName);
};

const handleTagInput = (event: KeyboardEvent) => {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        addTag(newTagInput.value);
        newTagInput.value = '';
    }
};

const addTagFromInput = () => {
    addTag(newTagInput.value);
    newTagInput.value = '';
};

const toggleExistingTag = (tagName: string, checked: boolean | 'indeterminate') => {
    if (checked === true) {
        addTag(tagName);
    } else {
        removeTag(tagName);
    }
};
</script>

<template>
    <Head title="Create Source" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl p-6">
            <h1 class="mb-6 text-2xl font-semibold">Create Source</h1>

            <form @submit.prevent="submit" class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="internal_name">Name</Label>
                    <Input id="internal_name" v-model="form.internal_name" type="text" required placeholder="Source name" />
                    <InputError :message="form.errors.internal_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="type">Type</Label>
                    <Select v-model="form.type">
                        <SelectTrigger id="type">
                            <SelectValue placeholder="Select type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="sourceType in sourceTypes" :key="sourceType.value" :value="sourceType.value">
                                {{ sourceType.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.type" />
                </div>

                <div class="grid gap-2">
                    <Label for="url">URL</Label>
                    <Input id="url" v-model="form.url" type="url" required placeholder="https://example.com/feed" />
                    <InputError :message="form.errors.url" />
                </div>

                <div class="grid gap-2">
                    <Label for="monitoring_interval">Monitoring Interval</Label>
                    <Select v-model="form.monitoring_interval">
                        <SelectTrigger id="monitoring_interval">
                            <SelectValue placeholder="Select interval" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="interval in intervals" :key="interval.value" :value="interval.value">
                                {{ interval.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.monitoring_interval" />
                </div>

                <div class="grid gap-4">
                    <div class="flex items-center gap-2">
                        <Checkbox id="is_active" :default-value="form.is_active" @update:model-value="form.is_active = $event" />
                        <Label for="is_active" class="font-normal">Active</Label>
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox id="should_notify" :default-value="form.should_notify" @update:model-value="form.should_notify = $event" />
                        <Label for="should_notify" class="font-normal">Send notifications for new posts</Label>
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox id="auto_summarize" :default-value="form.auto_summarize" @update:model-value="form.auto_summarize = $event" />
                        <Label for="auto_summarize" class="font-normal">Auto-summarize new posts with AI</Label>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label>Tags</Label>
                    <div class="flex gap-2">
                        <Input
                            v-model="newTagInput"
                            type="text"
                            placeholder="Type a tag and press Enter"
                            @keydown="handleTagInput"
                        />
                        <Button type="button" variant="outline" @click="addTagFromInput">Add</Button>
                    </div>
                    <div v-if="form.tags.length > 0" class="flex flex-wrap gap-2">
                        <span
                            v-for="tag in form.tags"
                            :key="tag"
                            class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-3 py-1 text-sm text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                        >
                            {{ tag }}
                            <button
                                type="button"
                                @click="removeTag(tag)"
                                class="ml-1 text-blue-600 hover:text-blue-800 dark:text-blue-300 dark:hover:text-blue-100"
                            >
                                &times;
                            </button>
                        </span>
                    </div>
                    <div v-if="props.tags.length > 0" class="mt-2">
                        <Label class="text-xs text-gray-500 dark:text-gray-400">Or select existing tags:</Label>
                        <div class="mt-1 flex flex-wrap gap-3">
                            <div v-for="tag in props.tags" :key="tag.id" class="flex items-center gap-1">
                                <Checkbox
                                    :id="`existing-tag-${tag.id}`"
                                    :default-value="form.tags.includes(tag.name)"
                                    @update:model-value="toggleExistingTag(tag.name, $event)"
                                />
                                <Label :for="`existing-tag-${tag.id}`" class="text-sm font-normal">{{ tag.name }}</Label>
                            </div>
                        </div>
                    </div>
                    <InputError :message="form.errors.tags" />
                </div>

                <div class="flex justify-end gap-3">
                    <Button variant="outline" as="a" href="/sources">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Creating...' : 'Create Source' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
