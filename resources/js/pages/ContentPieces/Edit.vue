<script setup lang="ts">
import DerivativesTab from '@/components/ContentPiece/DerivativesTab.vue';
import GeneralInfoHeader from '@/components/ContentPiece/GeneralInfoHeader.vue';
import ImagesTab from '@/components/ContentPiece/ImagesTab.vue';
import SourcesTab from '@/components/ContentPiece/SourcesTab.vue';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { MediaItem, MediaTag } from '@/types/media';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

type Prompt = {
    id: number;
    name: string;
    channel: string;
    channel_id?: number | null;
};

type Channel = {
    id: number;
    name: string;
    slug: string;
    icon: string | null;
    color: string | null;
};

type ContentDerivative = {
    id: number;
    content_piece_id: number;
    channel_id: number;
    prompt_id: number | null;
    title: string | null;
    text: string | null;
    status: 'NOT_STARTED' | 'DRAFT' | 'FINAL' | 'PUBLISHED' | 'NOT_PLANNED';
    planned_publish_at: string | null;
    generation_status: 'IDLE' | 'QUEUED' | 'PROCESSING' | 'COMPLETED' | 'FAILED';
    generation_error: string | null;
    media: MediaItem[];
};

type BackgroundSource = {
    id: number;
    type: 'POST' | 'MANUAL';
    post_id: number | null;
    post: Post | null;
    title: string | null;
    content: string | null;
    url: string | null;
    sort_order: number;
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
    prompt_id: number | null;
    prompt: Prompt | null;
    posts: Post[];
    published_at: string | null;
};

type AiState = {
    has_openai: boolean;
    has_gemini: boolean;
    settings_url: string;
};

interface Props {
    contentPiece: ContentPiece;
    channels: Channel[];
    derivatives: ContentDerivative[];
    backgroundSources: BackgroundSource[];
    availablePostsForSources: Post[];
    prompts: Prompt[];
    imagePrompts: ImagePrompt[];
    imageGenerations: ImageGeneration[];
    availablePosts: Post[];
    media: MediaItem[];
    mediaTags: MediaTag[];
    ai: AiState;
}

const props = defineProps<Props>();
const ai = props.ai;

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
    post_ids: props.contentPiece.posts.map((p) => p.id),
    published_at: formatDateTimeLocal(props.contentPiece.published_at),
});

const currentUrl = new URL(usePage().url, window.location.origin);
const initialTab = currentUrl.searchParams.get('tab');
const initialChannelId = currentUrl.searchParams.get('channel');
type TabValue = 'sources' | 'derivatives' | 'images';
const activeTab = ref<TabValue>(
    initialTab === 'sources'
        ? 'sources'
        : initialTab === 'derivatives'
          ? 'derivatives'
          : initialTab === 'images'
            ? 'images'
            : 'sources',
);
const updateTabInUrl = (tab: TabValue) => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    window.history.replaceState(
        {},
        '',
        `${url.pathname}?${url.searchParams.toString()}`,
    );
};

const localDerivatives = ref<ContentDerivative[]>([...props.derivatives]);

// Sync localDerivatives when props change (e.g., after creating a new derivative)
watch(() => props.derivatives, (newDerivatives) => {
    localDerivatives.value = [...newDerivatives];
}, { deep: true });

const handleDerivativesUpdated = (derivatives: ContentDerivative[]) => {
    localDerivatives.value = derivatives;
};

const imageGenerations = ref<ImageGeneration[]>([...props.imageGenerations]);

// Sync imageGenerations when props change
watch(() => props.imageGenerations, (newGenerations) => {
    imageGenerations.value = [...newGenerations];
}, { deep: true });

const handleGenerationsUpdated = (generations: ImageGeneration[]) => {
    imageGenerations.value = generations;
};

watch(
    () => activeTab.value,
    (value) => {
        updateTabInUrl(value);
    },
    { immediate: true },
);

const serializePublishedAt = (value: string | null) => {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    return date.toISOString();
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

</script>

<template>
    <Head title="Edit Content Piece" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <GeneralInfoHeader
                :form="form"
                :content-piece-title="contentPiece.internal_name"
                @save="save"
            />

            <Tabs
                v-model="activeTab"
                default-value="sources"
                class="space-y-4"
            >
                <TabsList>
                    <TabsTrigger value="sources">Sources</TabsTrigger>
                    <TabsTrigger value="derivatives">Derivatives</TabsTrigger>
                    <TabsTrigger value="images">Images</TabsTrigger>
                </TabsList>

                <TabsContent
                    value="sources"
                    class="space-y-4 rounded-xl border bg-card p-4 shadow-sm"
                >
                    <SourcesTab
                        :content-piece-id="contentPiece.id"
                        :sources="backgroundSources"
                        :available-posts="availablePostsForSources"
                    />
                </TabsContent>

                <TabsContent
                    value="derivatives"
                    class="space-y-4 rounded-xl border bg-card p-4 shadow-sm"
                >
                    <DerivativesTab
                        :content-piece-id="contentPiece.id"
                        :channels="channels"
                        :derivatives="localDerivatives"
                        :prompts="prompts"
                        :ai="ai"
                        :media="media"
                        :media-tags="mediaTags"
                        :initial-channel-id="initialChannelId ? Number(initialChannelId) : undefined"
                        @derivatives-updated="handleDerivativesUpdated"
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
                        :ai="ai"
                        @generations-updated="handleGenerationsUpdated"
                    />
                </TabsContent>
            </Tabs>

            <div class="flex items-center justify-end">
                <Button
                    type="button"
                    :disabled="form.processing"
                    @click="save()"
                >
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
