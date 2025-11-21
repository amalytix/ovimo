<script setup lang="ts">
import MediaPreview from '@/components/Media/MediaPreview.vue';
import MediaTagInput from '@/components/Media/MediaTagInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { MediaItem, MediaTag } from '@/types/media';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from '@/components/ui/sonner';

interface MediaProps extends MediaItem {
    uploaded_by?: {
        id: number;
        name: string;
    } | null;
    metadata?: Record<string, unknown> | null;
}

interface Props {
    media: MediaProps;
    availableTags: MediaTag[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Media', href: '/media' },
    { title: props.media.filename, href: `/media/${props.media.id}` },
];

const form = useForm({
    filename: props.media.filename,
    tag_ids: props.media.tags.map((tag) => tag.id),
});

const previewOpen = ref(false);

const save = () => {
    form.patch(`/media/${props.media.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Media updated'),
        onError: () => toast.error('Unable to update media'),
    });
};

const handleDelete = () => {
    router.delete(`/media/${props.media.id}`, {
        preserveScroll: true,
        onSuccess: () => router.visit('/media'),
    });
};
</script>

<template>
    <Head :title="media.filename" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="sm" as-child>
                    <a href="/media" class="flex items-center gap-2">
                        <ArrowLeft class="h-4 w-4" />
                        Back
                    </a>
                </Button>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-50">{{ media.filename }}</h1>
                <div class="ml-auto flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="previewOpen = true">Preview</Button>
                    <Button variant="destructive" size="sm" class="gap-2" @click="handleDelete">
                        <Trash2 class="h-4 w-4" />
                        Delete
                    </Button>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-[2fr,1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">Filename</label>
                            <Input v-model="form.filename" />
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">Tags</label>
                            <MediaTagInput v-model="form.tag_ids" :tags="availableTags" />
                        </div>

                        <div class="flex items-center gap-3">
                            <Button :disabled="form.processing" @click="save">Save changes</Button>
                            <p v-if="form.progress" class="text-xs text-gray-500">Saving…</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Metadata</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm text-gray-700 dark:text-gray-200">
                        <div class="flex justify-between">
                            <span>Type</span>
                            <Badge variant="secondary">{{ media.mime_type }}</Badge>
                        </div>
                        <div class="flex justify-between">
                            <span>Size</span>
                            <span>{{ (media.file_size / 1024 / 1024).toFixed(2) }} MB</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Uploaded</span>
                            <span>{{ new Date(media.created_at || '').toLocaleString() }}</span>
                        </div>
                        <div v-if="media.uploaded_by" class="flex justify-between">
                            <span>Uploaded by</span>
                            <span>{{ media.uploaded_by.name }}</span>
                        </div>
                        <div v-if="media.metadata" class="space-y-1">
                            <div v-for="(value, key) in media.metadata" :key="key" class="flex justify-between">
                                <span class="font-medium">{{ key }}</span>
                                <span class="text-gray-600">{{ value as string }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>

    <MediaPreview v-model:open="previewOpen" :media="media" />
</template>
