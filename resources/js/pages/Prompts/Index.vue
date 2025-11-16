<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';

interface Prompt {
    id: number;
    internal_name: string;
    prompt_text: string;
    content_pieces_count: number;
    created_at: string;
}

interface Props {
    prompts: {
        data: Prompt[];
        links: object;
        meta: object;
    };
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Prompts', href: '/prompts' }];

const deletePrompt = (id: number) => {
    if (confirm('Are you sure you want to delete this prompt?')) {
        router.delete(`/prompts/${id}`);
    }
};

const truncateContent = (content: string, maxLength: number = 100) => {
    if (content.length <= maxLength) return content;
    return content.substring(0, maxLength) + '...';
};
</script>

<template>
    <Head title="Prompts" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Prompts</h1>
                <Link href="/prompts/create">
                    <Button>Add Prompt</Button>
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
                                Content Preview
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Content Pieces
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Created
                            </th>
                            <th
                                class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        <tr v-for="prompt in prompts.data" :key="prompt.id">
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                {{ prompt.internal_name }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ truncateContent(prompt.prompt_text) }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ prompt.content_pieces_count }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ prompt.created_at }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <Link :href="`/prompts/${prompt.id}/edit`" class="mr-3 text-blue-600 hover:text-blue-900 dark:text-blue-400">
                                    Edit
                                </Link>
                                <button @click="deletePrompt(prompt.id)" class="text-red-600 hover:text-red-900 dark:text-red-400">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="prompts.data.length === 0">
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No prompts found. Click "Add Prompt" to create your first prompt.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
