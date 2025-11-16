<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Prompt {
    id: number;
    name: string;
    content?: string;
}

interface Post {
    id: number;
    uri: string;
    summary: string;
}

interface ContentPiece {
    id: number;
    internal_name: string;
    briefing_text: string | null;
    channel: string;
    target_language: string;
    status: string;
    full_text: string | null;
    prompt_id: number | null;
    prompt: Prompt | null;
    posts: Post[];
}

interface Props {
    contentPiece: ContentPiece;
    prompts: Prompt[];
    availablePosts: Post[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Content Pieces', href: '/content-pieces' },
    { title: 'Edit', href: `/content-pieces/${props.contentPiece.id}/edit` },
];

const form = useForm({
    internal_name: props.contentPiece.internal_name,
    prompt_id: props.contentPiece.prompt_id,
    briefing_text: props.contentPiece.briefing_text || '',
    channel: props.contentPiece.channel,
    target_language: props.contentPiece.target_language,
    full_text: props.contentPiece.full_text || '',
    post_ids: props.contentPiece.posts.map((p) => p.id),
});

const isGenerating = ref(false);

const togglePost = (postId: number, checked: boolean) => {
    if (checked) {
        form.post_ids = [...form.post_ids, postId];
    } else {
        form.post_ids = form.post_ids.filter((id) => id !== postId);
    }
};

const submit = () => {
    form.put(`/content-pieces/${props.contentPiece.id}`);
};

const submitAndClose = () => {
    form.put(`/content-pieces/${props.contentPiece.id}`, {
        onSuccess: () => {
            router.visit('/content-pieces');
        },
    });
};

const generateContent = () => {
    isGenerating.value = true;
    router.post(
        `/content-pieces/${props.contentPiece.id}/generate`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                // Update form with new content from refreshed props
                form.full_text = props.contentPiece.full_text || '';
            },
            onFinish: () => {
                isGenerating.value = false;
            },
        },
    );
};

const updateStatus = (status: string) => {
    router.patch(`/content-pieces/${props.contentPiece.id}/status`, { status }, { preserveScroll: true });
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

// Combine available posts with already selected posts
const allAvailablePosts = [...props.availablePosts];
props.contentPiece.posts.forEach((post) => {
    if (!allAvailablePosts.find((p) => p.id === post.id)) {
        allAvailablePosts.push(post);
    }
});
</script>

<template>
    <Head title="Edit Content Piece" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Edit Content Piece</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Update your content piece and generate AI content.</p>
                </div>
                <div class="flex items-center gap-4">
                    <span :class="getStatusColor(contentPiece.status)" class="rounded-full px-3 py-1 text-sm font-medium">
                        {{ formatStatus(contentPiece.status) }}
                    </span>
                    <Select :model-value="contentPiece.status" @update:model-value="updateStatus">
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

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Left Column: Settings -->
                <div class="space-y-6">
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="internal_name">Internal Name</Label>
                                <Input id="internal_name" v-model="form.internal_name" type="text" />
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
                            ></textarea>
                            <InputError :message="form.errors.briefing_text" />
                        </div>

                        <div class="space-y-4">
                            <Label>Source Posts</Label>
                            <div v-if="allAvailablePosts.length > 0" class="max-h-48 space-y-3 overflow-y-auto rounded-md border p-4">
                                <div v-for="post in allAvailablePosts" :key="post.id" class="flex items-start gap-3">
                                    <Checkbox
                                        :id="`post-${post.id}`"
                                        :model-value="form.post_ids.includes(post.id)"
                                        @update:model-value="(checked: boolean) => togglePost(post.id, checked)"
                                    />
                                    <div class="flex-1">
                                        <label :for="`post-${post.id}`" class="block cursor-pointer text-xs font-medium">
                                            {{ post.uri }}
                                        </label>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ post.summary }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="rounded-md border p-4 text-sm text-gray-500 dark:text-gray-400">No posts available.</div>
                        </div>

                        <div class="flex items-center gap-4">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Saving...' : 'Save Changes' }}
                            </Button>
                            <Button type="button" variant="outline" :disabled="form.processing" @click="submitAndClose">
                                {{ form.processing ? 'Saving...' : 'Save and Close' }}
                            </Button>
                            <Button type="button" variant="secondary" :disabled="isGenerating || !form.prompt_id" @click="generateContent">
                                {{ isGenerating ? 'Generating...' : 'Generate Content' }}
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Right Column: Generated Content -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <Label>Generated Content</Label>
                        <span v-if="form.full_text" class="text-xs text-gray-500"> {{ form.full_text.length }} characters </span>
                    </div>
                    <textarea
                        v-model="form.full_text"
                        rows="24"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Generated content will appear here. Click 'Generate Content' to create content using AI."
                    ></textarea>
                    <InputError :message="form.errors.full_text" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
