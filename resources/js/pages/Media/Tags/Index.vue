<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { toast } from '@/components/ui/sonner';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Save, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface Tag {
    id: number;
    name: string;
    media_count: number;
}

interface Props {
    tags: Tag[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Media', href: '/media' },
    { title: 'Tags', href: '/media-tags' },
];

const createForm = useForm({
    name: '',
});

const editing = ref<Record<number, string>>({});

const submitCreate = () => {
    createForm.post('/media-tags', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            toast.success('Tag created');
        },
    });
};

const startEditing = (tag: Tag) => {
    editing.value[tag.id] = tag.name;
};

const saveEdit = (tag: Tag) => {
    const name = editing.value[tag.id];
    router.patch(
        `/media-tags/${tag.id}`,
        { name },
        {
            preserveScroll: true,
            onSuccess: () => {
                delete editing.value[tag.id];
                toast.success('Tag updated');
            },
            onError: () => toast.error('Unable to update tag'),
        },
    );
};

const deleteTag = (tag: Tag) => {
    if (!confirm(`Delete tag "${tag.name}"?`)) {
        return;
    }
    router.delete(`/media-tags/${tag.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Tag deleted'),
        onError: () => toast.error('Unable to delete tag'),
    });
};
</script>

<template>
    <Head title="Media Tags" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="text-2xl font-semibold text-gray-900 dark:text-gray-50"
                    >
                        Media Tags
                    </h1>
                    <p class="text-sm text-gray-500">
                        Manage reusable tags for your media library.
                    </p>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Create Tag</CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        class="flex items-center gap-3"
                        @submit.prevent="submitCreate"
                    >
                        <Input
                            v-model="createForm.name"
                            placeholder="Tag name"
                            class="w-64"
                        />
                        <Button type="submit" :disabled="createForm.processing"
                            >Create</Button
                        >
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Existing Tags</CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800"
                    >
                        <table
                            class="min-w-full divide-y divide-gray-200 dark:divide-gray-800"
                        >
                            <thead class="bg-gray-50 dark:bg-gray-900/60">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase"
                                    >
                                        Name
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase"
                                    >
                                        Media
                                    </th>
                                    <th class="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-200 dark:divide-gray-800"
                            >
                                <tr v-for="tag in tags" :key="tag.id">
                                    <td class="px-4 py-3">
                                        <Input
                                            v-if="editing[tag.id] !== undefined"
                                            v-model="editing[tag.id]"
                                            class="w-64"
                                        />
                                        <span
                                            v-else
                                            class="text-sm font-semibold text-gray-900 dark:text-gray-50"
                                            >{{ tag.name }}</span
                                        >
                                    </td>
                                    <td
                                        class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300"
                                    >
                                        {{ tag.media_count }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            <Button
                                                v-if="
                                                    editing[tag.id] !==
                                                    undefined
                                                "
                                                size="sm"
                                                class="gap-2"
                                                @click="saveEdit(tag)"
                                            >
                                                <Save class="h-4 w-4" />
                                                Save
                                            </Button>
                                            <Button
                                                v-else
                                                size="sm"
                                                variant="outline"
                                                class="gap-2"
                                                @click="startEditing(tag)"
                                            >
                                                <Pencil class="h-4 w-4" />
                                                Edit
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                class="gap-2"
                                                @click="deleteTag(tag)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                                Delete
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
