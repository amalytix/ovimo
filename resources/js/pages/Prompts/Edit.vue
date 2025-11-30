<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

interface Prompt {
    id: number;
    internal_name: string;
    type: 'CONTENT' | 'IMAGE';
    channel: string;
    prompt_text: string;
}

interface Props {
    prompt: Prompt;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Prompts', href: '/prompts' },
    { title: 'Edit', href: `/prompts/${props.prompt.id}/edit` },
];

const form = useForm({
    internal_name: props.prompt.internal_name,
    type: props.prompt.type,
    channel: props.prompt.channel,
    prompt_text: props.prompt.prompt_text,
});

const submit = () => {
    form.put(`/prompts/${props.prompt.id}`);
};
</script>

<template>
    <Head title="Edit Prompt" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Edit Prompt</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Update your prompt template.
                </p>
            </div>

            <form @submit.prevent="submit" class="max-w-2xl space-y-6">
                <div class="space-y-2">
                    <Label for="internal_name">Name</Label>
                    <Input
                        id="internal_name"
                        v-model="form.internal_name"
                        type="text"
                        placeholder="Enter prompt name"
                    />
                    <InputError :message="form.errors.internal_name" />
                </div>

                <div class="space-y-2">
                    <Label for="type">Type</Label>
                    <Select v-model="form.type">
                        <SelectTrigger>
                            <SelectValue placeholder="Select type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="CONTENT"
                                >Content Generation</SelectItem
                            >
                            <SelectItem value="IMAGE"
                                >Image Generation</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Content prompts are used for generating text. Image
                        prompts are used for generating AI images.
                    </p>
                    <InputError :message="form.errors.type" />
                </div>

                <div v-if="form.type === 'CONTENT'" class="space-y-2">
                    <Label for="channel">Channel</Label>
                    <Select v-model="form.channel">
                        <SelectTrigger>
                            <SelectValue placeholder="Select channel" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="BLOG_POST">Blog Post</SelectItem>
                            <SelectItem value="LINKEDIN_POST"
                                >LinkedIn Post</SelectItem
                            >
                            <SelectItem value="YOUTUBE_SCRIPT"
                                >YouTube Script</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.channel" />
                </div>

                <div class="space-y-2">
                    <Label for="prompt_text">Content</Label>
                    <textarea
                        id="prompt_text"
                        v-model="form.prompt_text"
                        rows="12"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        :placeholder="
                            form.type === 'IMAGE'
                                ? 'Enter your image prompt template. Use {{content}} as a placeholder for the blog content.'
                                : 'Enter your prompt template...'
                        "
                    ></textarea>
                    <p
                        v-if="form.type === 'IMAGE'"
                        class="text-xs text-gray-500 dark:text-gray-400"
                    >
                        Use the placeholder
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-800"
                            >&#123;&#123;content&#125;&#125;</code
                        >
                        which will be replaced with the blog content when
                        generating image prompts.
                    </p>
                    <p v-else class="text-xs text-gray-500 dark:text-gray-400">
                        Use placeholders like
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-800"
                            >&#123;&#123;context&#125;&#125;</code
                        >
                        or
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-800"
                            >&#123;&#123;language&#125;&#125;</code
                        >
                        or
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-800"
                            >&#123;&#123;channel&#125;&#125;</code
                        >
                        that will be replaced during content generation.
                    </p>
                    <InputError :message="form.errors.prompt_text" />
                </div>

                <div class="flex items-center gap-4">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Updating...' : 'Update Prompt' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
