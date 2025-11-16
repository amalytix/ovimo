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

interface Prompt {
    id: number;
    name: string;
}

interface Post {
    id: number;
    uri: string;
    summary: string;
}

interface Props {
    prompts: Prompt[];
    availablePosts: Post[];
    preselectedPostIds?: number[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Content Pieces', href: '/content-pieces' },
    { title: 'Create', href: '/content-pieces/create' },
];

const form = useForm({
    internal_name: '',
    prompt_id: null as number | null,
    briefing_text: '',
    channel: 'BLOG_POST',
    target_language: 'ENGLISH',
    post_ids: props.preselectedPostIds || ([] as number[]),
});

const togglePost = (postId: number, checked: boolean) => {
    if (checked) {
        form.post_ids = [...form.post_ids, postId];
    } else {
        form.post_ids = form.post_ids.filter((id) => id !== postId);
    }
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
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Create Content Piece</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Set up your content piece. After creation, you can generate the content using AI.
                </p>
            </div>

            <form @submit.prevent="submit" class="max-w-4xl space-y-6">
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
                    <p class="text-sm text-gray-600 dark:text-gray-400">Select posts to use as source material for content generation.</p>

                    <div v-if="availablePosts.length > 0" class="max-h-64 space-y-3 overflow-y-auto rounded-md border p-4">
                        <div v-for="post in availablePosts" :key="post.id" class="flex items-start gap-3">
                            <Checkbox
                                :id="`post-${post.id}`"
                                :model-value="form.post_ids.includes(post.id)"
                                @update:model-value="(checked: boolean) => togglePost(post.id, checked)"
                            />
                            <div class="flex-1">
                                <label :for="`post-${post.id}`" class="block cursor-pointer text-sm font-medium">
                                    {{ post.uri }}
                                </label>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ post.summary }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="rounded-md border p-4 text-sm text-gray-500 dark:text-gray-400">
                        No posts marked as "CREATE_CONTENT" are available. Mark some posts for content creation first.
                    </div>
                    <InputError :message="form.errors.post_ids" />
                </div>

                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                    <h3 class="mb-2 font-medium text-blue-900 dark:text-blue-100">Available Placeholders for Prompts</h3>
                    <ul class="space-y-1 text-sm text-blue-800 dark:text-blue-200">
                        <li><code class="rounded bg-blue-100 px-1 dark:bg-blue-800">&#123;&#123;context&#125;&#125;</code> - Post summaries, briefing text, channel, and language</li>
                        <li><code class="rounded bg-blue-100 px-1 dark:bg-blue-800">&#123;&#123;channel&#125;&#125;</code> - Target channel (e.g., BLOG_POST, LINKEDIN_POST, YOUTUBE_SCRIPT)</li>
                        <li><code class="rounded bg-blue-100 px-1 dark:bg-blue-800">&#123;&#123;language&#125;&#125;</code> - Target language (ENGLISH or GERMAN)</li>
                    </ul>
                </div>

                <div class="flex items-center gap-4">
                    <Button type="button" variant="outline" :disabled="form.processing" @click="cancel"> Cancel </Button>
                    <Button type="button" variant="secondary" :disabled="form.processing" @click="saveAndClose">
                        {{ form.processing ? 'Saving...' : 'Save and Close' }}
                    </Button>
                    <Button type="button" :disabled="form.processing" @click="saveAndGenerate">
                        {{ form.processing ? 'Creating...' : 'Save and Generate Content' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
