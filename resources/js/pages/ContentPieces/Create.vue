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
import { computed, onUnmounted, ref, watch } from 'vue';
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
    external_title: string | null;
    internal_title: string | null;
};

interface Props {
    prompts: Prompt[];
    availablePosts: Post[];
    preselectedPostIds?: number[];
    initialTitle?: string | null;
    media: MediaItem[];
    mediaTags: MediaTag[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Content Pieces', href: '/content-pieces' },
    { title: 'Create', href: '/content-pieces/create' },
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

const form = useForm({
    internal_name: props.initialTitle || '',
    prompt_id: props.prompts.length > 0 ? props.prompts[0].id : null,
    briefing_text: '',
    channel: props.prompts.length > 0 ? props.prompts[0].channel : 'BLOG_POST',
    target_language: 'ENGLISH',
    status: 'NOT_STARTED',
    research_text: '',
    edited_text: '',
    post_ids: props.preselectedPostIds || ([] as number[]),
    media_ids: [] as number[],
    published_at: '' as string | null,
});

const activeTab = ref<'research' | 'editing'>('research');
const showCopyDialog = ref(false);
const generation = ref<{ status: string | null; error: string | null }>({
    status: null,
    error: null,
});

const selectedMedia = ref<MediaItem[]>([]);
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

const saveDraft = () => {
    form
        .transform((data) => ({
            ...data,
            published_at: serializePublishedAt(form.published_at),
        }))
        .post('/content-pieces', {
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        });
};

const saveAndGenerate = () => {
    form
        .transform((data) => ({
            ...data,
            generate_content: true,
            published_at: serializePublishedAt(form.published_at),
        }))
        .post('/content-pieces', {
            onFinish: () => form.transform((data) => data),
        });
};

const cancel = () => {
    router.visit('/content-pieces');
};
</script>

<template>
    <Head title="Create Content Piece" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <GeneralInfoHeader :form="form" />

            <Tabs v-model="activeTab" default-value="research" class="space-y-4">
                <TabsList>
                    <TabsTrigger value="research">Research</TabsTrigger>
                    <TabsTrigger value="editing">Editing</TabsTrigger>
                </TabsList>

                <TabsContent value="research" class="space-y-4 rounded-xl border bg-card p-4 shadow-sm">
                    <ResearchTab
                        :form="form"
                        :prompts="prompts"
                        :posts="availablePosts"
                        :generation-status="generation"
                        @copy-to-editor="openCopyDialog"
                        @generate="saveAndGenerate"
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
                <Button variant="outline" @click="cancel">Cancel</Button>
                <Button variant="secondary" :disabled="form.processing || isPolling" @click="saveDraft">
                    {{ form.processing ? 'Saving...' : 'Save Draft' }}
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
