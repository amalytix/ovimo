<script setup lang="ts">
import CopyContentDialog from '@/components/ContentPiece/CopyContentDialog.vue';
import EditingTab from '@/components/ContentPiece/EditingTab.vue';
import GeneralInfoHeader from '@/components/ContentPiece/GeneralInfoHeader.vue';
import ImagesTab from '@/components/ContentPiece/ImagesTab.vue';
import MediaGalleryPicker from '@/components/ContentPiece/MediaGalleryPicker.vue';
import ResearchTab from '@/components/ContentPiece/ResearchTab.vue';
import LinkedInIntegrationSelector from '@/components/Publishing/LinkedInIntegrationSelector.vue';
import PublishingScheduler from '@/components/Publishing/PublishingScheduler.vue';
import PublishingStatus from '@/components/Publishing/PublishingStatus.vue';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { publish, status } from '@/routes/content-pieces';
import { view as mediaView } from '@/routes/media';
import type { BreadcrumbItem } from '@/types';
import type { MediaItem, MediaTag } from '@/types/media';
import type { SocialIntegration } from '@/types/social';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, watch } from 'vue';

type Prompt = {
    id: number;
    name: string;
    channel: string;
};

type ImagePrompt = {
    id: number;
    internal_name: string;
};

type ImageMedia = {
    id: number;
    filename: string;
    mime_type: string;
    temporary_url: string;
};

type ImageGeneration = {
    id: number;
    content_piece_id: number;
    prompt_id: number | null;
    prompt: { id: number; internal_name: string } | null;
    generated_text_prompt: string | null;
    aspect_ratio: '16:9' | '1:1' | '4:3' | '9:16' | '4:5';
    status: 'DRAFT' | 'GENERATING' | 'COMPLETED' | 'FAILED';
    media_id: number | null;
    media: ImageMedia | null;
    error_message: string | null;
    created_at: string;
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
    publish_state?: string | null;
    publish_to_platforms?: Record<string, any> | null;
    published_platforms?: Record<string, any> | null;
    publish_at?: string | null;
};

interface Props {
    contentPiece: ContentPiece;
    prompts: Prompt[];
    imagePrompts: ImagePrompt[];
    imageGenerations: ImageGeneration[];
    availablePosts: Post[];
    media: MediaItem[];
    mediaTags: MediaTag[];
    integrations: {
        linkedin: SocialIntegration[];
    };
}

const props = defineProps<Props>();
const integrations = props.integrations;

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

const currentUrl = new URL(usePage().url, window.location.origin);
const initialTab = currentUrl.searchParams.get('tab');
const activeTab = ref<'research' | 'editing' | 'images' | 'publishing'>(
    initialTab === 'edit' || initialTab === 'editing'
        ? 'editing'
        : initialTab === 'images'
          ? 'images'
          : initialTab === 'publishing'
            ? 'publishing'
            : 'research',
);
const updateTabInUrl = (
    tab: 'research' | 'editing' | 'images' | 'publishing',
) => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    window.history.replaceState(
        {},
        '',
        `${url.pathname}?${url.searchParams.toString()}`,
    );
};

const imageGenerations = ref<ImageGeneration[]>([...props.imageGenerations]);

const handleGenerationsUpdated = (generations: ImageGeneration[]) => {
    imageGenerations.value = generations;
};

onMounted(() => {
    updateTabInUrl(activeTab.value);
});

watch(
    () => activeTab.value,
    (value) => {
        updateTabInUrl(value);
    },
);
const showCopyDialog = ref(false);
const generation = ref<{ status: string | null; error: string | null }>({
    status: null,
    error: null,
});

const selectedMedia = ref<MediaItem[]>([...props.contentPiece.media]);
const attachmentsOpen = ref(false);
const imagePickerOpen = ref(false);
const pickerMode = ref<'attachments' | 'insert'>('attachments');
const editingContentType = ref<'html' | 'markdown'>('html');

const editingTabRef = ref<InstanceType<typeof EditingTab> | null>(null);

const page = usePage();
const isPolling = ref(false);
let pollingInterval: number | null = null;
let successTimeout: number | null = null;

const publishingForm = useForm({
    integration_id: props.integrations.linkedin[0]?.id ?? null,
    schedule_at: props.contentPiece.publish_at
        ? formatDateTimeLocal(props.contentPiece.publish_at)
        : '',
});

const publishNow = () => {
    publishingForm.schedule_at = '';
    publishingForm.post(publish.url(props.contentPiece.id), {
        preserveScroll: true,
    });
};

const schedulePublish = () => {
    publishingForm.post(publish.url(props.contentPiece.id), {
        preserveScroll: true,
    });
};

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
                generation.value.error =
                    data.error || 'Generation failed. Please try again.';
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
    { immediate: true },
);

const openCopyDialog = () => {
    if (form.edited_text && form.edited_text.trim().length > 0) {
        showCopyDialog.value = true;
    } else {
        form.edited_text = form.research_text;
        editingContentType.value = 'markdown';
        activeTab.value = 'editing';
    }
};

const confirmCopy = (mode: 'replace' | 'append') => {
    if (mode === 'replace') {
        form.edited_text = form.research_text;
    } else {
        form.edited_text = [form.edited_text, form.research_text]
            .filter(Boolean)
            .join('\n\n');
    }
    editingContentType.value = 'markdown';
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
            const src = mediaView.url(first.id);
            editingTabRef.value?.insertImage(src);
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

const attachGeneratedImage = (imageMedia: {
    id: number;
    filename: string;
    mime_type: string;
    temporary_url: string;
}) => {
    // Check if already attached
    if (selectedMedia.value.find((m) => m.id === imageMedia.id)) {
        return;
    }

    // Convert to MediaItem format and add to selected media
    const mediaItem: MediaItem = {
        id: imageMedia.id,
        filename: imageMedia.filename,
        mime_type: imageMedia.mime_type,
        file_size: 0,
        created_at: null,
        temporary_url: imageMedia.temporary_url,
        tags: [],
    };

    selectedMedia.value.push(mediaItem);
    form.media_ids = selectedMedia.value.map((m) => m.id);
};

const detachGeneratedImage = (mediaId: number) => {
    selectedMedia.value = selectedMedia.value.filter((m) => m.id !== mediaId);
    form.media_ids = selectedMedia.value.map((m) => m.id);
};

const save = () => {
    form.transform((data) => ({
        ...data,
        published_at: serializePublishedAt(form.published_at),
    })).put(`/content-pieces/${props.contentPiece.id}?tab=${activeTab.value}`, {
        preserveScroll: true,
        onFinish: () => form.transform((data) => data),
    });
};

const saveAndClose = () => {
    form.transform((data) => ({
        ...data,
        published_at: serializePublishedAt(form.published_at),
    })).put(`/content-pieces/${props.contentPiece.id}?tab=${activeTab.value}`, {
        onSuccess: () => router.visit('/content-pieces'),
        onFinish: () => form.transform((data) => data),
    });
};

const generateContent = () => {
    router.post(
        `/content-pieces/${props.contentPiece.id}/generate?tab=${activeTab.value}`,
        {},
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Edit Content Piece" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <GeneralInfoHeader
                :form="form"
                :content-piece-title="contentPiece.internal_name"
                :is-saving="form.processing || isPolling"
                @save="save"
                @save-and-close="saveAndClose"
            />

            <Tabs
                v-model="activeTab"
                default-value="research"
                class="space-y-4"
            >
                <TabsList>
                    <TabsTrigger value="research">Research</TabsTrigger>
                    <TabsTrigger value="editing">Editing</TabsTrigger>
                    <TabsTrigger value="images">Images</TabsTrigger>
                    <TabsTrigger value="publishing">Publishing</TabsTrigger>
                </TabsList>

                <TabsContent
                    value="research"
                    class="space-y-4 rounded-xl border bg-card p-4 shadow-sm"
                >
                    <ResearchTab
                        :form="form"
                        :prompts="prompts"
                        :posts="allAvailablePosts"
                        :generation-status="generation"
                        @copy-to-editor="openCopyDialog"
                        @generate="generateContent"
                    />
                </TabsContent>

                <TabsContent
                    value="editing"
                    class="space-y-4 rounded-xl border bg-card p-4 shadow-sm"
                >
                    <EditingTab
                        ref="editingTabRef"
                        :form="form"
                        :selected-media="selectedMedia"
                        :content-type="editingContentType"
                        @open-media-picker="openMediaPicker"
                        @remove-media="removeMedia"
                        @request-image="openImagePicker"
                        @content-type-change="
                            (value: 'html' | 'markdown') =>
                                (editingContentType = value)
                        "
                    />
                </TabsContent>

                <TabsContent
                    value="images"
                    class="space-y-4 rounded-xl border bg-card p-4 shadow-sm"
                >
                    <ImagesTab
                        :content-piece-id="contentPiece.id"
                        :image-prompts="imagePrompts"
                        :image-generations="imageGenerations"
                        :attached-media-ids="form.media_ids"
                        :has-edited-text="
                            !!form.edited_text &&
                            form.edited_text.trim().length > 0
                        "
                        @generations-updated="handleGenerationsUpdated"
                        @attach-media="attachGeneratedImage"
                        @detach-media="detachGeneratedImage"
                    />
                </TabsContent>

                <TabsContent
                    value="publishing"
                    class="space-y-6 rounded-xl border bg-card p-4 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <p
                                class="text-sm font-medium text-gray-900 dark:text-gray-50"
                            >
                                Publishing status
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Manage LinkedIn publishing for this content
                                piece.
                            </p>
                        </div>
                        <PublishingStatus
                            :status="
                                contentPiece.publish_state || 'not_published'
                            "
                        />
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div
                            class="space-y-4 rounded-lg border border-dashed p-4"
                        >
                            <h3
                                class="text-sm font-medium text-gray-900 dark:text-gray-50"
                            >
                                Destination
                            </h3>
                            <LinkedInIntegrationSelector
                                :integrations="integrations.linkedin"
                                v-model="publishingForm.integration_id"
                            />
                            <p
                                v-if="!integrations.linkedin.length"
                                class="text-xs text-amber-600 dark:text-amber-400"
                            >
                                Connect a LinkedIn profile in Team Settings to
                                enable publishing.
                            </p>
                        </div>

                        <div
                            class="space-y-4 rounded-lg border border-dashed p-4"
                        >
                            <h3
                                class="text-sm font-medium text-gray-900 dark:text-gray-50"
                            >
                                Schedule
                            </h3>
                            <PublishingScheduler
                                v-model="publishingForm.schedule_at"
                            />
                            <div class="flex gap-3">
                                <Button
                                    class="w-full"
                                    variant="secondary"
                                    :disabled="
                                        publishingForm.processing ||
                                        !publishingForm.integration_id
                                    "
                                    @click="publishNow"
                                >
                                    {{
                                        publishingForm.processing
                                            ? 'Starting…'
                                            : 'Publish now'
                                    }}
                                </Button>
                                <Button
                                    class="w-full"
                                    :disabled="
                                        publishingForm.processing ||
                                        !publishingForm.integration_id
                                    "
                                    @click="schedulePublish"
                                >
                                    {{
                                        publishingForm.processing
                                            ? 'Scheduling…'
                                            : 'Schedule'
                                    }}
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="contentPiece.published_platforms?.linkedin"
                        class="rounded-lg border bg-muted/30 p-4"
                    >
                        <p
                            class="text-sm font-medium text-gray-900 dark:text-gray-50"
                        >
                            Last published to LinkedIn
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            URN:
                            {{
                                contentPiece.published_platforms.linkedin.urn ||
                                'n/a'
                            }}
                        </p>
                    </div>
                </TabsContent>
            </Tabs>

            <div class="flex items-center justify-end gap-3">
                <Button variant="outline" @click="saveAndClose"
                    >Save &amp; close</Button
                >
                <Button
                    variant="secondary"
                    :disabled="form.processing || isPolling"
                    @click="save"
                >
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
        :multi-select="true"
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
