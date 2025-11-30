<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Webhook {
    id: number;
    name: string;
    url: string;
    event: string;
    is_active: boolean;
    secret: string | null;
}

interface Props {
    webhook: Webhook;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Webhooks', href: '/webhooks' },
    { title: 'Edit', href: `/webhooks/${props.webhook.id}/edit` },
];

const form = useForm({
    name: props.webhook.name,
    url: props.webhook.url,
    event: props.webhook.event,
    is_active: props.webhook.is_active,
    secret: props.webhook.secret || '',
});

const submit = () => {
    form.put(`/webhooks/${props.webhook.id}`);
};

const testProcessing = ref(false);

const sendTestWebhook = () => {
    testProcessing.value = true;
    router.post(
        `/webhooks/${props.webhook.id}/test`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                testProcessing.value = false;
            },
        },
    );
};
</script>

<template>
    <Head title="Edit Webhook" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Edit Webhook</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Update your webhook configuration.
                </p>
            </div>

            <form @submit.prevent="submit" class="max-w-2xl space-y-6">
                <div class="space-y-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="E.g., Slack Notification"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="space-y-2">
                    <Label for="url">Webhook URL</Label>
                    <Input
                        id="url"
                        v-model="form.url"
                        type="url"
                        placeholder="https://example.com/webhook"
                    />
                    <InputError :message="form.errors.url" />
                </div>

                <div class="space-y-2">
                    <Label for="event">Event</Label>
                    <Select v-model="form.event">
                        <SelectTrigger>
                            <SelectValue placeholder="Select event" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="NEW_POSTS"
                                >New Posts Found</SelectItem
                            >
                            <SelectItem value="HIGH_RELEVANCY_POST"
                                >High Relevancy Post</SelectItem
                            >
                            <SelectItem value="CONTENT_GENERATED"
                                >Content Generated</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.event" />
                </div>

                <div class="space-y-2">
                    <Label for="secret">Secret (Optional)</Label>
                    <Input
                        id="secret"
                        v-model="form.secret"
                        type="text"
                        placeholder="Used for HMAC signature verification"
                    />
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        If provided, the webhook payload will be signed using
                        HMAC-SHA256 and included in the X-Webhook-Signature
                        header.
                    </p>
                    <InputError :message="form.errors.secret" />
                </div>

                <div class="flex items-center gap-2">
                    <Checkbox
                        id="is_active"
                        :default-value="form.is_active"
                        @update:model-value="form.is_active = $event"
                    />
                    <Label for="is_active" class="cursor-pointer">Active</Label>
                </div>

                <div class="flex items-center gap-4">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Updating...' : 'Update Webhook' }}
                    </Button>
                </div>
            </form>

            <div
                class="mt-8 max-w-2xl border-t border-gray-200 pt-8 dark:border-gray-700"
            >
                <h2 class="text-lg font-medium">Test Webhook</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Send a test payload to verify your webhook is configured
                    correctly. The test will be sent via the queue.
                </p>
                <div class="mt-4">
                    <Button
                        variant="outline"
                        :disabled="testProcessing"
                        @click="sendTestWebhook"
                    >
                        {{
                            testProcessing ? 'Sending...' : 'Send Test Webhook'
                        }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
