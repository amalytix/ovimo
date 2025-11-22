<script setup lang="ts">
import CopyContentDialog from '@/components/ContentPiece/CopyContentDialog.vue';
import EditingTab from '@/components/ContentPiece/EditingTab.vue';
import GeneralInfoHeader from '@/components/ContentPiece/GeneralInfoHeader.vue';
import MediaGalleryPicker from '@/components/ContentPiece/MediaGalleryPicker.vue';
import ResearchTab from '@/components/ContentPiece/ResearchTab.vue';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { MediaItem, MediaTag } from '@/types/media';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { onUnmounted, ref, watch } from 'vue';
import { status } from '@/routes/content-pieces';

type Prompt = {
    id: number;
    name: string;
    channel: string;
};

type Post = {
    id: number;
    uri: string;
    summary: string;
    external_title?: string | null;
    internal_title?: string | null;
};

type ContentPiece = {
    id: number;
    internal_name: string;
    briefing_text: string | null;
    channel: string;
    target_language: string;
    status: string;
    research_text: string | null;
    edited_text: string | null;
    prompt_id: number | null;
    prompt: Prompt | null;
    posts: Post[];
    media: MediaItem[];
    published_at: string | null;
};

interface Props {
    contentPiece: ContentPiece;
    prompts: Prompt[];
    availablePosts: Post[];
    media: MediaItem[];
    mediaTags: MediaTag[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Content Pieces', href: '/content-pieces' },
    { title: 'Edit', href: `/content-pieces/${props.contentPiece.id}/edit` },
];

const formatDateTimeLocal = (value: string | Date | null) => {
    if (!value) {
        return '';
    }

    const date = value instanceof Date ? value : new Date(value);

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const allAvailablePosts = [...props.availablePosts];
props.contentPiece.posts.forEach((post) => {
    if (!allAvailablePosts.find((p) => p.id === post.id)) {
        allAvailablePosts.push(post);
    }
});

const form = useForm({
    internal_name: props.contentPiece.internal_name,
    prompt_id: props.contentPiece.prompt_id,
    briefing_text: props.contentPiece.briefing_text || '',
    channel: props.contentPiece.channel,
    target_language: props.contentPiece.target_language,
    status: props.contentPiece.status,
    research_text: props.contentPiece.research_text || '',
    edited_text: props.contentPiece.edited_text || '',
    post_ids: props.contentPiece.posts.map((p) => p.id),
    media_ids: props.contentPiece.media.map((m) => m.id),
    published_at: formatDateTimeLocal(props.contentPiece.published_at),
});

const activeTab = ref<'research' | 'editing'>('research');
const showCopyDialog = ref(false);
const generation = ref<{ status: string | null; error: string | null }>({
    status: null,
    error: null,
});

const selectedMedia = ref<MediaItem[]>([...props.contentPiece.media]);
const attachmentsOpen = ref(false);
const imagePickerOpen = ref(false);
const pickerMode = ref<'attachments' | 'insert'>('attachments');

const editingTabRef = ref<InstanceType<typeof EditingTab> | null>(null);

const page = usePage();
const isPolling = ref(false);
let pollingInterval: number | null = null;
let successTimeout: number | null = null;

const serializePublishedAt = (value: string | null) => {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    return date.toISOString();
};

const startPolling = (contentPieceId: number) => {
    isPolling.value = true;
    generation.value.status = 'QUEUED';
    generation.value.error = null;

    pollingInterval = window.setInterval(async () => {
        try {
            const response = await fetch(status.url(contentPieceId));
            const data = await response.json();

            generation.value.status = data.generation_status;

            if (data.generation_status === 'COMPLETED') {
                stopPolling();
                form.research_text = data.research_text || '';
                if (!form.edited_text) {
                    form.edited_text = data.edited_text || '';
                }
                form.status = 'DRAFT';
                successTimeout = window.setTimeout(() => {
                    generation.value.status = null;
                }, 4000);
            } else if (data.generation_status === 'FAILED') {
                stopPolling();
                generation.value.error = data.error || 'Generation failed. Please try again.';
            }
        } catch (error) {
            console.error('Polling error:', error);
        }
    }, 3000);
};

const stopPolling = () => {
    isPolling.value = false;
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
};

onUnmounted(() => {
    stopPolling();
    if (successTimeout) {
        clearTimeout(successTimeout);
        successTimeout = null;
    }
});

watch(
    () => page.props.polling,
    (polling: any) => {
        if (polling && polling.content_piece_id) {
            startPolling(polling.content_piece_id);
        }
    },
    { immediate: true }
);

const openCopyDialog = () => {
    if (form.edited_text && form.edited_text.trim().length > 0) {
        showCopyDialog.value = true;
    } else {
        form.edited_text = form.research_text;
        activeTab.value = 'editing';
    }
};

const confirmCopy = (mode: 'replace' | 'append') => {
    if (mode === 'replace') {
        form.edited_text = form.research_text;
    } else {
        form.edited_text = [form.edited_text, form.research_text].filter(Boolean).join('\n\n');
    }
    activeTab.value = 'editing';
};

const cancelCopy = () => {
    showCopyDialog.value = false;
};

const openMediaPicker = () => {
    pickerMode.value = 'attachments';
    attachmentsOpen.value = true;
};

const openImagePicker = () => {
    pickerMode.value = 'insert';
    imagePickerOpen.value = true;
};

const applyMediaSelection = (items: MediaItem[]) => {
    if (pickerMode.value === 'insert') {
        const first = items[0];
        if (first) {
            const src = first.temporary_url || first.download_url || '';
            if (src) {
                editingTabRef.value?.insertImage(src);
            }
        }
        imagePickerOpen.value = false;
        return;
    }

    const unique = [...selectedMedia.value];
    items.forEach((item) => {
        if (!unique.find((m) => m.id === item.id)) {
            unique.push(item);
        }
    });
    selectedMedia.value = unique;
    form.media_ids = unique.map((m) => m.id);
    attachmentsOpen.value = false;
};

const removeMedia = (id: number) => {
    selectedMedia.value = selectedMedia.value.filter((m) => m.id !== id);
    form.media_ids = selectedMedia.value.map((m) => m.id);
};

const save = () => {
    form
        .transform((data) => ({
            ...data,
            published_at: serializePublishedAt(form.published_at),
        }))
        .put(`/content-pieces/${props.contentPiece.id}`, {
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        });
};

const saveAndClose = () => {
    form
        .transform((data) => ({
            ...data,
            published_at: serializePublishedAt(form.published_at),
        }))
        .put(`/content-pieces/${props.contentPiece.id}`, {
            onSuccess: () => router.visit('/content-pieces'),
            onFinish: () => form.transform((data) => data),
        });
};

const generateContent = () => {
    router.post(`/content-pieces/${props.contentPiece.id}/generate`, {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Edit Content Piece" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <GeneralInfoHeader :form="form" :content-piece-title="contentPiece.internal_name" />

            <Tabs v-model="activeTab" default-value="research" class="space-y-4">
                <TabsList>
                    <TabsTrigger value="research">Research</TabsTrigger>
                    <TabsTrigger value="editing">Editing</TabsTrigger>
                </TabsList>

                <TabsContent value="research" class="space-y-4 rounded-xl border bg-card p-4 shadow-sm">
                    <ResearchTab
                        :form="form"
                        :prompts="prompts"
                        :posts="allAvailablePosts"
                        :generation-status="generation"
                        @copy-to-editor="openCopyDialog"
                        @generate="generateContent"
                    />
                </TabsContent>

                <TabsContent value="editing" class="space-y-4 rounded-xl border bg-card p-4 shadow-sm">
                    <EditingTab
                        ref="editingTabRef"
                        :form="form"
                        :selected-media="selectedMedia"
                        @open-media-picker="openMediaPicker"
                        @remove-media="removeMedia"
                        @request-image="openImagePicker"
                    />
                </TabsContent>
            </Tabs>

            <div class="flex items-center justify-end gap-3">
                <Button variant="outline" @click="saveAndClose">Save &amp; close</Button>
                <Button variant="secondary" :disabled="form.processing || isPolling" @click="save">
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>
        </div>
    </AppLayout>

    <MediaGalleryPicker
        :open="attachmentsOpen"
        :selected-ids="form.media_ids"
        :media="media"
        :tags="mediaTags"
        @update:open="attachmentsOpen = $event"
        @select="applyMediaSelection"
    />

    <MediaGalleryPicker
        :open="imagePickerOpen"
        :selected-ids="[]"
        :media="media"
        :tags="mediaTags"
        :multi-select="false"
        @update:open="imagePickerOpen = $event"
        @select="applyMediaSelection"
    />

    <CopyContentDialog
        :open="showCopyDialog"
        :has-existing-content="!!form.edited_text"
        @update:open="showCopyDialog = $event"
        @confirm="confirmCopy"
        @cancel="cancelCopy"
    />
</template>
