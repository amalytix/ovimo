<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

interface Tag {
    id: number;
    name: string;
}

interface Source {
    id: number;
    internal_name: string;
    type: string;
    url: string;
    monitoring_interval: string;
    is_active: boolean;
    should_notify: boolean;
    auto_summarize: boolean;
    tag_ids: number[];
}

interface Props {
    source: Source;
    tags: Tag[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sources', href: '/sources' },
    { title: 'Edit', href: `/sources/${props.source.id}/edit` },
];

const form = useForm({
    internal_name: props.source.internal_name,
    type: props.source.type,
    url: props.source.url,
    monitoring_interval: props.source.monitoring_interval,
    is_active: props.source.is_active,
    should_notify: props.source.should_notify,
    auto_summarize: props.source.auto_summarize,
    tag_ids: props.source.tag_ids as number[],
});

const submit = () => {
    form.put(`/sources/${props.source.id}`);
};

const intervals = [
    { value: 'EVERY_10_MIN', label: 'Every 10 minutes' },
    { value: 'EVERY_30_MIN', label: 'Every 30 minutes' },
    { value: 'HOURLY', label: 'Hourly' },
    { value: 'EVERY_6_HOURS', label: 'Every 6 hours' },
    { value: 'DAILY', label: 'Daily' },
    { value: 'WEEKLY', label: 'Weekly' },
];
</script>

<template>
    <Head title="Edit Source" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl p-6">
            <h1 class="mb-6 text-2xl font-semibold">Edit Source</h1>

            <form @submit.prevent="submit" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                    <input
                        v-model="form.internal_name"
                        type="text"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                        required
                    />
                    <p v-if="form.errors.internal_name" class="mt-1 text-sm text-red-600">{{ form.errors.internal_name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                    <select
                        v-model="form.type"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                    >
                        <option value="RSS">RSS Feed</option>
                        <option value="XML_SITEMAP">XML Sitemap</option>
                    </select>
                    <p v-if="form.errors.type" class="mt-1 text-sm text-red-600">{{ form.errors.type }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">URL</label>
                    <input
                        v-model="form.url"
                        type="url"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                        required
                    />
                    <p v-if="form.errors.url" class="mt-1 text-sm text-red-600">{{ form.errors.url }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Monitoring Interval</label>
                    <select
                        v-model="form.monitoring_interval"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                    >
                        <option v-for="interval in intervals" :key="interval.value" :value="interval.value">
                            {{ interval.label }}
                        </option>
                    </select>
                    <p v-if="form.errors.monitoring_interval" class="mt-1 text-sm text-red-600">{{ form.errors.monitoring_interval }}</p>
                </div>

                <div class="space-y-4">
                    <label class="flex items-center">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                    </label>

                    <label class="flex items-center">
                        <input v-model="form.should_notify" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Send notifications for new posts</span>
                    </label>

                    <label class="flex items-center">
                        <input v-model="form.auto_summarize" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Auto-summarize new posts with AI</span>
                    </label>
                </div>

                <div v-if="tags.length > 0">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tags</label>
                    <div class="mt-2 space-y-2">
                        <label v-for="tag in tags" :key="tag.id" class="flex items-center">
                            <input
                                v-model="form.tag_ids"
                                :value="tag.id"
                                type="checkbox"
                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ tag.name }}</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="/sources" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        Cancel
                    </a>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
