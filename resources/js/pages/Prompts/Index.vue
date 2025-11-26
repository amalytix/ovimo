<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { setDefault } from '@/actions/App/Http/Controllers/PromptController';
import { Pencil, Star, Trash2 } from 'lucide-vue-next';

interface Prompt {
    id: number;
    internal_name: string;
    type: 'CONTENT' | 'IMAGE';
    prompt_text: string;
    content_pieces_count: number;
    created_at: string;
    is_default: boolean;
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

const setDefaultPrompt = (id: number) => {
    router.post(setDefault(id).url);
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
                                Type
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
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Default
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
                                <Link :href="`/prompts/${prompt.id}/edit`" class="hover:text-blue-600 dark:hover:text-blue-400">
                                    {{ prompt.internal_name }}
                                </Link>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        prompt.type === 'IMAGE'
                                            ? 'bg-purple-50 text-purple-700 dark:bg-purple-900/50 dark:text-purple-200'
                                            : 'bg-blue-50 text-blue-700 dark:bg-blue-900/50 dark:text-blue-200',
                                    ]"
                                >
                                    {{ prompt.type === 'IMAGE' ? 'Image' : 'Content' }}
                                </span>
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
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span
                                    v-if="prompt.is_default"
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-200"
                                >
                                    <Star class="h-4 w-4" fill="currentColor" />
                                    Default
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-3">
                                    <button
                                        v-if="!prompt.is_default"
                                        @click="setDefaultPrompt(prompt.id)"
                                        class="text-amber-500 hover:text-amber-400 dark:text-amber-300"
                                        title="Set as default"
                                    >
                                        <Star :size="18" fill="currentColor" />
                                        <span class="sr-only">Set as default</span>
                                    </button>
                                    <Link
                                        :href="`/prompts/${prompt.id}/edit`"
                                        class="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                                        title="Edit"
                                    >
                                        <Pencil :size="18" />
                                        <span class="sr-only">Edit</span>
                                    </Link>
                                    <button
                                        @click="deletePrompt(prompt.id)"
                                        class="text-red-500 hover:text-red-700 dark:text-red-400"
                                        title="Delete"
                                    >
                                        <Trash2 :size="18" />
                                        <span class="sr-only">Delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="prompts.data.length === 0">
                            <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No prompts found. Click "Add Prompt" to create your first prompt.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
