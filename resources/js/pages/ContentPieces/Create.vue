<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, onUnmounted, watch } from 'vue';
import { status } from '@/routes/content-pieces';

interface Prompt {
    id: number;
    name: string;
}

interface Post {
    id: number;
    uri: string;
    summary: string;
    external_title: string | null;
    internal_title: string | null;
}

interface Props {
    prompts: Prompt[];
    availablePosts: Post[];
    preselectedPostIds?: number[];
    initialTitle?: string | null;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Content Pieces', href: '/content-pieces' },
    { title: 'Create', href: '/content-pieces/create' },
];

const form = useForm({
    internal_name: props.initialTitle || '',
    prompt_id: null as number | null,
    briefing_text: '',
    channel: 'BLOG_POST',
    target_language: 'ENGLISH',
    status: 'NOT_STARTED',
    post_ids: props.preselectedPostIds || ([] as number[]),
    full_text: '',
});

// Polling state
const page = usePage();
const isPolling = ref(false);
const generationStatus = ref<string | null>(null);
const generatedContent = ref<string | null>(null);
const generationError = ref<string | null>(null);
const showSuccessMessage = ref(false);
let pollingInterval: number | null = null;
let successTimeout: number | null = null;

// Watch for polling metadata from session flash
watch(
    () => page.props.polling,
    (polling: any) => {
        if (polling && polling.content_piece_id) {
            startPolling(polling.content_piece_id);
        }
    },
    { immediate: true }
);

const startPolling = (contentPieceId: number) => {
    isPolling.value = true;
    generationStatus.value = 'QUEUED';
    generatedContent.value = null;
    generationError.value = null;
    showSuccessMessage.value = false;

    pollingInterval = window.setInterval(async () => {
        try {
            const response = await fetch(status.url(contentPieceId));
            const data = await response.json();

            generationStatus.value = data.generation_status;

            // Stop polling on completion or failure
            if (data.generation_status === 'COMPLETED') {
                stopPolling();
                generatedContent.value = data.full_text;
                form.full_text = data.full_text || '';
                showSuccessMessage.value = true;

                // Auto-hide success message after 5 seconds
                successTimeout = window.setTimeout(() => {
                    showSuccessMessage.value = false;
                }, 5000);
            } else if (data.generation_status === 'FAILED') {
                stopPolling();
                generationError.value = data.error || 'Generation failed. Please try again.';
            }
        } catch (error) {
            console.error('Polling error:', error);
        }
    }, 3000); // Poll every 3 seconds
};

const stopPolling = () => {
    isPolling.value = false;
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
};

// Cleanup on unmount
onUnmounted(() => {
    stopPolling();
    if (successTimeout) {
        clearTimeout(successTimeout);
        successTimeout = null;
    }
});

const togglePost = (postId: number, checked: boolean) => {
    if (checked) {
        form.post_ids = [...form.post_ids, postId];
    } else {
        form.post_ids = form.post_ids.filter((id) => id !== postId);
    }
};

const formatStatus = (status: string) => {
    const map: Record<string, string> = {
        NOT_STARTED: 'Not Started',
        DRAFT: 'Draft',
        FINAL: 'Final',
    };
    return map[status] || status;
};

const getStatusColor = (status: string) => {
    const colors: Record<string, string> = {
        NOT_STARTED: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
        DRAFT: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        FINAL: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};

const saveAndClose = () => {
    form.post('/content-pieces', {
        preserveScroll: true,
        onSuccess: () => {
            router.visit('/content-pieces');
        },
    });
};

const saveAndGenerate = () => {
    form
        .transform((data) => ({
            ...data,
            generate_content: true,
        }))
        .post('/content-pieces');
};

const cancel = () => {
    router.visit('/content-pieces');
};
</script>

<template>
    <Head title="Create Content Piece" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Create Content Piece</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Set up your content piece and generate AI content.</p>
                </div>
                <div class="flex items-center gap-4">
                    <!-- Polling and success status -->
                    <Transition
                        mode="out-in"
                        enter-active-class="transition-opacity duration-300"
                        enter-from-class="opacity-0"
                        enter-to-class="opacity-100"
                        leave-active-class="transition-opacity duration-300"
                        leave-from-class="opacity-100"
                        leave-to-class="opacity-0"
                    >
                        <div v-if="isPolling" key="polling" class="flex items-center gap-2 text-blue-600 dark:text-blue-400">
                            <div class="h-4 w-4 animate-spin rounded-full border-2 border-blue-600 border-t-transparent dark:border-blue-400"></div>
                            <span class="text-sm font-medium">{{ generationStatus === 'QUEUED' ? 'Queued...' : 'Generating...' }}</span>
                        </div>
                        <div v-else-if="showSuccessMessage" key="success" class="flex items-center gap-2 text-green-600 dark:text-green-400">
                            <span class="text-sm font-medium">✓ Generated</span>
                        </div>
                    </Transition>
                    <span :class="getStatusColor(form.status)" class="rounded-full px-3 py-1 text-sm font-medium">
                        {{ formatStatus(form.status) }}
                    </span>
                    <Select v-model="form.status">
                        <SelectTrigger class="w-40">
                            <SelectValue placeholder="Change status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="NOT_STARTED">Not Started</SelectItem>
                            <SelectItem value="DRAFT">Draft</SelectItem>
                            <SelectItem value="FINAL">Final</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <!-- Error message -->
            <div v-if="generationError" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                <p class="mb-1 font-medium text-red-900 dark:text-red-100">Generation failed</p>
                <p class="text-sm text-red-700 dark:text-red-300">{{ generationError }}</p>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Left Column: Settings -->
                <div class="space-y-6">
                    <form @submit.prevent="submit" class="space-y-6">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="internal_name">Internal Name</Label>
                        <Input id="internal_name" v-model="form.internal_name" type="text" placeholder="E.g., Blog post about AI trends" />
                        <InputError :message="form.errors.internal_name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="prompt_id">Prompt Template</Label>
                        <Select v-model="form.prompt_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select a prompt" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="prompt in prompts" :key="prompt.id" :value="prompt.id">
                                    {{ prompt.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.prompt_id" />
                    </div>

                    <div class="space-y-2">
                        <Label for="channel">Target Channel</Label>
                        <Select v-model="form.channel">
                            <SelectTrigger>
                                <SelectValue placeholder="Select channel" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="BLOG_POST">Blog Post</SelectItem>
                                <SelectItem value="LINKEDIN_POST">LinkedIn Post</SelectItem>
                                <SelectItem value="YOUTUBE_SCRIPT">YouTube Script</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.channel" />
                    </div>

                    <div class="space-y-2">
                        <Label for="target_language">Target Language</Label>
                        <Select v-model="form.target_language">
                            <SelectTrigger>
                                <SelectValue placeholder="Select language" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="ENGLISH">English</SelectItem>
                                <SelectItem value="GERMAN">German</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.target_language" />
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="briefing_text">Briefing / Additional Context</Label>
                    <textarea
                        id="briefing_text"
                        v-model="form.briefing_text"
                        rows="4"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Add any additional context, tone requirements, or specific instructions..."
                    ></textarea>
                    <InputError :message="form.errors.briefing_text" />
                </div>

                        <div class="space-y-4">
                            <Label>Source Posts</Label>
                            <div v-if="availablePosts.length > 0" class="max-h-48 space-y-3 overflow-y-auto rounded-md border p-4">
                                <div v-for="post in availablePosts" :key="post.id" class="flex items-start gap-3">
                                    <Checkbox
                                        :id="`post-${post.id}`"
                                        :model-value="form.post_ids.includes(post.id)"
                                        @update:model-value="(checked: boolean) => togglePost(post.id, checked)"
                                    />
                                    <div class="flex-1">
                                        <label :for="`post-${post.id}`" class="block cursor-pointer text-xs font-medium">
                                            {{ post.external_title || post.internal_title || post.uri }}
                                        </label>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ post.summary }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="rounded-md border p-4 text-sm text-gray-500 dark:text-gray-400">No posts available.</div>
                        </div>

                        <div class="flex items-center gap-4">
                            <Button type="button" variant="outline" :disabled="form.processing || isPolling" @click="cancel"> Cancel </Button>
                            <Button type="button" variant="secondary" :disabled="form.processing || isPolling" @click="saveAndClose">
                                {{ form.processing ? 'Saving...' : 'Save and Close' }}
                            </Button>
                            <Button type="button" :disabled="form.processing || isPolling || !form.prompt_id" @click="saveAndGenerate">
                                {{ form.processing ? 'Creating...' : 'Save and Generate Content' }}
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Right Column: Generated Content -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label>Generated Content</Label>
                        <span v-if="form.full_text" class="text-xs text-gray-500"> {{ form.full_text.length }} characters </span>
                    </div>
                    <textarea
                        v-model="form.full_text"
                        rows="24"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Generated content will appear here. Click 'Save and Generate Content' to create content using AI."
                    ></textarea>
                    <InputError :message="form.errors.full_text" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
