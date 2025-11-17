<script setup lang="ts">
import { analyzeWebpage, testExtraction } from '@/actions/App/Http/Controllers/SourceController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';

interface Tag {
    id: number;
    name: string;
}

interface Source {
    id: number;
    internal_name: string;
    type: string;
    url: string;
    css_selector_title: string | null;
    css_selector_link: string | null;
    keywords: string | null;
    monitoring_interval: string;
    is_active: boolean;
    should_notify: boolean;
    auto_summarize: boolean;
    bypass_keyword_filter: boolean;
    tags: string[];
}

interface Props {
    source: Source;
    tags: Tag[];
}

interface ExtractedPost {
    title: string;
    link: string;
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
    css_selector_title: props.source.css_selector_title || '',
    css_selector_link: props.source.css_selector_link || '',
    keywords: props.source.keywords || '',
    monitoring_interval: props.source.monitoring_interval,
    is_active: props.source.is_active,
    should_notify: props.source.should_notify,
    auto_summarize: props.source.auto_summarize,
    bypass_keyword_filter: props.source.bypass_keyword_filter,
    tags: props.source.tags,
});

const newTagInput = ref('');
const isAnalyzing = ref(false);
const isTesting = ref(false);
const extractedPosts = ref<ExtractedPost[]>([]);
const analyzeError = ref('');
const testError = ref('');

const submit = () => {
    form.put(`/sources/${props.source.id}`);
};

const analyzePageStructure = async () => {
    if (!form.url) {
        analyzeError.value = 'Please enter a URL first';
        return;
    }

    isAnalyzing.value = true;
    analyzeError.value = '';

    try {
        const response = await axios.post(analyzeWebpage.url(), { url: form.url });
        form.css_selector_title = response.data.css_selector_title;
        form.css_selector_link = response.data.css_selector_link;
    } catch (error: unknown) {
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            analyzeError.value = error.response.data.message;
        } else {
            analyzeError.value = 'Failed to analyze page structure';
        }
    } finally {
        isAnalyzing.value = false;
    }
};

const testExtractionNow = async () => {
    if (!form.url || !form.css_selector_title || !form.css_selector_link) {
        testError.value = 'URL and CSS selectors are required';
        return;
    }

    isTesting.value = true;
    testError.value = '';
    extractedPosts.value = [];

    try {
        const response = await axios.post(testExtraction.url(), {
            url: form.url,
            css_selector_title: form.css_selector_title,
            css_selector_link: form.css_selector_link,
            keywords: form.keywords,
        });
        extractedPosts.value = response.data.posts;
    } catch (error: unknown) {
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            testError.value = error.response.data.message;
        } else {
            testError.value = 'Failed to extract posts';
        }
    } finally {
        isTesting.value = false;
    }
};

const sourceTypes = [
    { value: 'RSS', label: 'RSS Feed' },
    { value: 'XML_SITEMAP', label: 'XML Sitemap' },
    { value: 'WEBSITE', label: 'Website (Other)' },
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
    <Head title="Edit Source" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl p-6">
            <h1 class="mb-6 text-2xl font-semibold">Edit Source</h1>

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

                <!-- Website-specific fields -->
                <template v-if="form.type === 'WEBSITE'">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                        <h3 class="mb-4 font-medium">Website Extraction Settings</h3>

                        <div class="mb-4">
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="isAnalyzing || !form.url"
                                @click="analyzePageStructure"
                            >
                                {{ isAnalyzing ? 'Analyzing...' : 'Analyze Page' }}
                            </Button>
                            <p class="mt-1 text-xs text-gray-500">Uses AI to detect post structure and suggest CSS selectors</p>
                            <p v-if="analyzeError" class="mt-1 text-sm text-red-600">{{ analyzeError }}</p>
                        </div>

                        <div class="grid gap-4">
                            <div class="grid gap-2">
                                <Label for="css_selector_title">CSS Selector for Title</Label>
                                <Input
                                    id="css_selector_title"
                                    v-model="form.css_selector_title"
                                    type="text"
                                    placeholder=".post-title a"
                                />
                                <InputError :message="form.errors.css_selector_title" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="css_selector_link">CSS Selector for Link</Label>
                                <Input
                                    id="css_selector_link"
                                    v-model="form.css_selector_link"
                                    type="text"
                                    placeholder=".post-title a"
                                />
                                <InputError :message="form.errors.css_selector_link" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="keywords">Keywords (optional)</Label>
                                <Input
                                    id="keywords"
                                    v-model="form.keywords"
                                    type="text"
                                    placeholder="Amazon, Vendor Central, Seller Central"
                                />
                                <p class="text-xs text-gray-500">Comma-separated keywords to filter posts. Only posts containing these keywords will be monitored.</p>
                                <InputError :message="form.errors.keywords" />
                            </div>

                            <div>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    :disabled="isTesting || !form.css_selector_title || !form.css_selector_link"
                                    @click="testExtractionNow"
                                >
                                    {{ isTesting ? 'Testing...' : 'Test Extraction' }}
                                </Button>
                                <p v-if="testError" class="mt-1 text-sm text-red-600">{{ testError }}</p>
                            </div>

                            <div v-if="extractedPosts.length > 0" class="mt-2">
                                <Label class="mb-2 block">Preview (first {{ extractedPosts.length }} posts):</Label>
                                <div class="space-y-2">
                                    <div
                                        v-for="(post, index) in extractedPosts"
                                        :key="index"
                                        class="rounded border border-gray-200 bg-white p-3 dark:border-gray-600 dark:bg-gray-700"
                                    >
                                        <div class="font-medium">{{ post.title }}</div>
                                        <a
                                            :href="post.link"
                                            target="_blank"
                                            class="text-sm text-blue-600 hover:underline dark:text-blue-400"
                                        >
                                            {{ post.link }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

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

                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="bypass_keyword_filter"
                            :default-value="form.bypass_keyword_filter"
                            @update:model-value="form.bypass_keyword_filter = $event"
                        />
                        <Label for="bypass_keyword_filter" class="font-normal">Bypass team keyword filters</Label>
                    </div>
                    <p class="ml-6 -mt-2 text-xs text-gray-500 dark:text-gray-400">
                        When enabled, all posts from this source will be analyzed regardless of team positive/negative keyword settings.
                    </p>
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
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
