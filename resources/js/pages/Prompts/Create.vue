<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Prompts', href: '/prompts' },
    { title: 'Create', href: '/prompts/create' },
];

const form = useForm({
    internal_name: '',
    channel: '',
    prompt_text: '',
});

const submit = () => {
    form.post('/prompts');
};
</script>

<template>
    <Head title="Create Prompt" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Create Prompt</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Create a reusable prompt template for AI content generation.
                </p>
            </div>

            <form @submit.prevent="submit" class="max-w-2xl space-y-6">
                <div class="space-y-2">
                    <Label for="internal_name">Name</Label>
                    <Input id="internal_name" v-model="form.internal_name" type="text" placeholder="Enter prompt name" />
                    <InputError :message="form.errors.internal_name" />
                </div>

                <div class="space-y-2">
                    <Label for="channel">Channel</Label>
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
                    <Label for="prompt_text">Content</Label>
                    <textarea
                        id="prompt_text"
                        v-model="form.prompt_text"
                        rows="12"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Enter your prompt template. Allowed placeholders: {{context}}, {{language}} or {{channel}}."
                    ></textarea>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Use placeholders like
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-800">&#123;&#123;post_summary&#125;&#125;</code> or
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-800">&#123;&#123;context&#125;&#125;</code> that will be replaced
                        during content generation.
                    </p>
                    <InputError :message="form.errors.prompt_text" />
                </div>

                <div class="flex items-center gap-4">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Creating...' : 'Create Prompt' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
