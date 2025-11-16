<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

interface Team {
    id: number;
    name: string;
    notifications_enabled: boolean;
    webhook_url: string | null;
    post_auto_hide_days: number | null;
    monthly_token_limit: number | null;
    relevancy_prompt: string | null;
}

interface Props {
    team: Team;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Team Settings', href: '/team-settings' }];

const form = useForm({
    name: props.team.name,
    notifications_enabled: props.team.notifications_enabled,
    webhook_url: props.team.webhook_url || '',
    post_auto_hide_days: props.team.post_auto_hide_days,
    monthly_token_limit: props.team.monthly_token_limit,
    relevancy_prompt: props.team.relevancy_prompt || '',
});

const submit = () => {
    form.put('/team-settings');
};
</script>

<template>
    <Head title="Team Settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Team Settings</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Configure your team's preferences and defaults.</p>
            </div>

            <form @submit.prevent="submit" class="max-w-2xl space-y-8">
                <!-- General Settings -->
                <div class="space-y-6">
                    <h2 class="text-lg font-medium">General</h2>

                    <div class="space-y-2">
                        <Label for="name">Team Name</Label>
                        <Input id="name" v-model="form.name" type="text" />
                        <InputError :message="form.errors.name" />
                    </div>
                </div>

                <!-- Notification Settings -->
                <div class="space-y-6">
                    <h2 class="text-lg font-medium">Notifications</h2>

                    <div class="flex items-center gap-2">
                        <Checkbox id="notifications_enabled" :default-value="form.notifications_enabled" @update:model-value="form.notifications_enabled = $event" />
                        <Label for="notifications_enabled" class="cursor-pointer">Enable Notifications</Label>
                    </div>

                    <div class="space-y-2">
                        <Label for="webhook_url">Default Webhook URL (Legacy)</Label>
                        <Input id="webhook_url" v-model="form.webhook_url" type="url" placeholder="https://example.com/webhook" />
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Use the Webhooks page for more advanced webhook configuration.
                        </p>
                        <InputError :message="form.errors.webhook_url" />
                    </div>
                </div>

                <!-- Post Management -->
                <div class="space-y-6">
                    <h2 class="text-lg font-medium">Post Management</h2>

                    <div class="space-y-2">
                        <Label for="post_auto_hide_days">Auto-hide Posts After (days)</Label>
                        <Input id="post_auto_hide_days" v-model.number="form.post_auto_hide_days" type="number" min="0" max="365" />
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Automatically hide posts after this many days. Leave empty to disable.
                        </p>
                        <InputError :message="form.errors.post_auto_hide_days" />
                    </div>
                </div>

                <!-- Token Limits -->
                <div class="space-y-6">
                    <h2 class="text-lg font-medium">Token Usage Limits</h2>

                    <div class="space-y-2">
                        <Label for="monthly_token_limit">Monthly Token Limit</Label>
                        <Input id="monthly_token_limit" v-model.number="form.monthly_token_limit" type="number" min="0" />
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Maximum tokens allowed per month. Leave empty for unlimited.
                        </p>
                        <InputError :message="form.errors.monthly_token_limit" />
                    </div>
                </div>

                <!-- AI Configuration -->
                <div class="space-y-6">
                    <h2 class="text-lg font-medium">AI Configuration</h2>

                    <div class="space-y-2">
                        <Label for="relevancy_prompt">Custom Relevancy Prompt</Label>
                        <textarea
                            id="relevancy_prompt"
                            v-model="form.relevancy_prompt"
                            rows="6"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            placeholder="You are a news analyst. Rate the relevancy of content for a business news monitoring system..."
                        ></textarea>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Customize how the AI evaluates post relevancy. This prompt is used when summarizing and scoring new posts.
                        </p>
                        <InputError :message="form.errors.relevancy_prompt" />
                    </div>
                </div>

                <div class="flex items-center gap-4 pt-4">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save Settings' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
