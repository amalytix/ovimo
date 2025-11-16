<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';

interface Webhook {
    id: number;
    name: string;
    url: string;
    event: string;
    is_active: boolean;
    last_triggered_at: string | null;
    failure_count: number;
}

interface Props {
    webhooks: {
        data: Webhook[];
        links: object;
        meta: object;
    };
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Webhooks', href: '/webhooks' }];

const deleteWebhook = (id: number) => {
    if (confirm('Are you sure you want to delete this webhook?')) {
        router.delete(`/webhooks/${id}`);
    }
};

const formatEvent = (event: string) => {
    const map: Record<string, string> = {
        NEW_POSTS: 'New Posts Found',
        HIGH_RELEVANCY_POST: 'High Relevancy Post',
        CONTENT_GENERATED: 'Content Generated',
    };
    return map[event] || event;
};
</script>

<template>
    <Head title="Webhooks" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Webhooks</h1>
                <Link href="/webhooks/create">
                    <Button>Add Webhook</Button>
                </Link>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                URL
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Event
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Last Triggered
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Failures
                            </th>
                            <th
                                class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        <tr v-for="webhook in webhooks.data" :key="webhook.id">
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                {{ webhook.name }}
                            </td>
                            <td class="max-w-xs truncate px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ webhook.url }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <span class="rounded bg-gray-100 px-2 py-1 text-xs dark:bg-gray-700">
                                    {{ formatEvent(webhook.event) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span
                                    :class="
                                        webhook.is_active
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                    "
                                    class="rounded-full px-2 py-1 text-xs"
                                >
                                    {{ webhook.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ webhook.last_triggered_at || 'Never' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <span
                                    :class="webhook.failure_count > 0 ? 'text-red-600 dark:text-red-400' : ''"
                                    class="font-medium"
                                >
                                    {{ webhook.failure_count }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <Link
                                    :href="`/webhooks/${webhook.id}/edit`"
                                    class="mr-3 text-blue-600 hover:text-blue-900 dark:text-blue-400"
                                >
                                    Edit
                                </Link>
                                <button @click="deleteWebhook(webhook.id)" class="text-red-600 hover:text-red-900 dark:text-red-400">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="webhooks.data.length === 0">
                            <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No webhooks configured. Click "Add Webhook" to set up notifications.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
