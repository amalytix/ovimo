<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import MediaGalleryPicker from '@/components/ContentPiece/MediaGalleryPicker.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import {
    derivativeStatusOptions,
    getStatusDotColor,
    type DerivativeStatus,
} from '@/composables/useDerivativeStatus';
import {
    generate as generateDerivative,
    status as derivativeStatus,
    store as storeDerivative,
    update as updateDerivative,
} from '@/actions/App/Http/Controllers/ContentDerivativeController';
import type { MediaItem, MediaTag } from '@/types/media';
import { router, useForm } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, Clock, Image, Pencil, Plus, Sparkles, Trash2, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import TiptapEditor from './TiptapEditor.vue';
import ActivityFeed from './ActivityFeed/ActivityFeed.vue';

type Channel = {
    id: number;
    name: string;
    slug: string;
    icon: string | null;
    color: string | null;
};

type Prompt = {
    id: number;
    name: string;
    channel_id: number | null;
};

type ContentDerivative = {
    id: number;
    content_piece_id: number;
    channel_id: number;
    prompt_id: number | null;
    title: string | null;
    text: string | null;
    status: DerivativeStatus;
    planned_publish_at: string | null;
    generation_status: 'IDLE' | 'QUEUED' | 'PROCESSING' | 'COMPLETED' | 'FAILED';
    generation_error: string | null;
    media: MediaItem[];
};

type AiState = {
    has_openai: boolean;
    settings_url: string;
};

const props = defineProps<{
    contentPieceId: number;
    channels: Channel[];
    derivatives: ContentDerivative[];
    prompts: Prompt[];
    ai: AiState;
    media: MediaItem[];
    mediaTags: MediaTag[];
    initialChannelId?: number;
}>();

const emit = defineEmits<{
    (event: 'derivatives-updated', derivatives: ContentDerivative[]): void;
}>();

const localDerivatives = ref<ContentDerivative[]>([...props.derivatives]);
const selectedChannelId = ref<number | null>(
    props.initialChannelId && props.channels.some(c => c.id === props.initialChannelId)
        ? props.initialChannelId
        : props.channels[0]?.id ?? null
);
const pollingIntervals = ref<Map<number, number>>(new Map());

const selectedChannel = computed(() =>
    props.channels.find(c => c.id === selectedChannelId.value)
);

const selectedDerivative = computed(() =>
    localDerivatives.value.find(d => d.channel_id === selectedChannelId.value)
);

const channelPrompts = computed(() => {
    if (!selectedChannelId.value) return props.prompts;
    return props.prompts.filter(p => p.channel_id === selectedChannelId.value || p.channel_id === null);
});

const derivativeForm = useForm({
    title: '',
    text: '',
    status: 'NOT_STARTED' as ContentDerivative['status'],
    prompt_id: null as number | null,
    planned_publish_at: '',
    media_ids: [] as number[],
});

const mediaPickerOpen = ref(false);
const contentTypeForEditor = ref<'html' | 'markdown'>('html');
const activityFeedRef = ref<InstanceType<typeof ActivityFeed> | null>(null);


const getGenerationStatusBadge = (derivative: ContentDerivative) => {
    switch (derivative.generation_status) {
        case 'QUEUED':
        case 'PROCESSING':
            return { variant: 'default' as const, text: derivative.generation_status === 'QUEUED' ? 'Queued' : 'Generating...' };
        case 'COMPLETED':
            return { variant: 'success' as const, text: 'Generated' };
        case 'FAILED':
            return { variant: 'destructive' as const, text: 'Failed' };
        default:
            return null;
    }
};

const isGenerating = computed(() => {
    if (!selectedDerivative.value) return false;
    return ['QUEUED', 'PROCESSING'].includes(selectedDerivative.value.generation_status);
});

const canGenerate = computed(() => {
    if (!selectedDerivative.value) return false;
    if (!props.ai.has_openai) return false;
    return !isGenerating.value;
});

const formatDateTimeLocal = (value: string | Date | null) => {
    if (!value) return '';
    const date = value instanceof Date ? value : new Date(value);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

// Sync form when selected derivative changes
watch(selectedDerivative, (derivative) => {
    if (derivative) {
        derivativeForm.title = derivative.title ?? '';
        // Detect if text is markdown (doesn't start with HTML tag)
        // TiptapEditor HTML always starts with tags like <p>, <h1>, etc.
        const text = derivative.text ?? '';
        const looksLikeMarkdown = text && !text.trimStart().startsWith('<');
        contentTypeForEditor.value = looksLikeMarkdown ? 'markdown' : 'html';
        derivativeForm.text = text;
        derivativeForm.status = derivative.status;
        derivativeForm.prompt_id = derivative.prompt_id;
        derivativeForm.planned_publish_at = derivative.planned_publish_at
            ? formatDateTimeLocal(derivative.planned_publish_at)
            : '';
        derivativeForm.media_ids = derivative.media?.map(m => m.id) ?? [];
    } else {
        derivativeForm.reset();
        contentTypeForEditor.value = 'html';
    }
}, { immediate: true });

// Get attached media items for the selected derivative
const attachedMedia = computed(() => {
    if (!selectedDerivative.value) return [];
    return props.media.filter(m => derivativeForm.media_ids.includes(m.id));
});

const openMediaPicker = () => {
    mediaPickerOpen.value = true;
};

const applyMediaSelection = (selectedMedia: MediaItem[]) => {
    derivativeForm.media_ids = selectedMedia.map(m => m.id);
    mediaPickerOpen.value = false;
};

const removeMedia = (mediaId: number) => {
    derivativeForm.media_ids = derivativeForm.media_ids.filter(id => id !== mediaId);
};

// Watch for prop changes
watch(() => props.derivatives, (newDerivatives) => {
    localDerivatives.value = [...newDerivatives];
}, { deep: true });

// Update URL when selected channel changes
watch(selectedChannelId, (channelId) => {
    if (channelId === null) return;
    const url = new URL(window.location.href);
    url.searchParams.set('channel', String(channelId));
    window.history.replaceState({}, '', url.toString());
});

const createDerivative = () => {
    if (!selectedChannelId.value) return;

    router.post(storeDerivative.url(props.contentPieceId), {
        channel_id: selectedChannelId.value,
        status: 'NOT_STARTED',
    }, {
        preserveScroll: true,
    });
};

const saveDerivative = () => {
    if (!selectedDerivative.value) return;

    router.put(
        updateDerivative.url([props.contentPieceId, selectedDerivative.value.id]),
        {
            title: derivativeForm.title,
            text: derivativeForm.text,
            status: derivativeForm.status,
            prompt_id: derivativeForm.prompt_id,
            planned_publish_at: derivativeForm.planned_publish_at || null,
            media_ids: derivativeForm.media_ids,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                // Refresh activity feed after a short delay to allow queued events to be processed
                setTimeout(() => {
                    activityFeedRef.value?.refresh();
                }, 500);
            },
        }
    );
};

const startPolling = (derivativeId: number) => {
    if (pollingIntervals.value.has(derivativeId)) return;

    const interval = window.setInterval(async () => {
        try {
            const response = await fetch(derivativeStatus.url([props.contentPieceId, derivativeId]));
            const data = await response.json();

            // Update local derivative
            const idx = localDerivatives.value.findIndex(d => d.id === derivativeId);
            if (idx !== -1) {
                localDerivatives.value[idx] = {
                    ...localDerivatives.value[idx],
                    generation_status: data.generation_status,
                    title: data.title ?? localDerivatives.value[idx].title,
                    text: data.text ?? localDerivatives.value[idx].text,
                    generation_error: data.error,
                };

                // Update form if this is the selected derivative
                if (selectedDerivative.value?.id === derivativeId) {
                    if (data.title) derivativeForm.title = data.title;
                    if (data.text) {
                        // Mark as markdown so TiptapEditor converts it properly
                        contentTypeForEditor.value = 'markdown';
                        derivativeForm.text = data.text;
                    }
                }

                emit('derivatives-updated', localDerivatives.value);
            }

            if (data.generation_status === 'COMPLETED' || data.generation_status === 'FAILED') {
                stopPolling(derivativeId);
            }
        } catch (error) {
            console.error('Polling error:', error);
        }
    }, 3000);

    pollingIntervals.value.set(derivativeId, interval);
};

const stopPolling = (derivativeId: number) => {
    const interval = pollingIntervals.value.get(derivativeId);
    if (interval) {
        clearInterval(interval);
        pollingIntervals.value.delete(derivativeId);
    }
};

const generate = () => {
    if (!selectedDerivative.value) return;

    router.post(
        generateDerivative.url([props.contentPieceId, selectedDerivative.value.id]),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                // Update local state to show queued
                const idx = localDerivatives.value.findIndex(d => d.id === selectedDerivative.value?.id);
                if (idx !== -1) {
                    localDerivatives.value[idx].generation_status = 'QUEUED';
                }
                startPolling(selectedDerivative.value!.id);
            },
        }
    );
};

// Start polling for any derivatives that are currently generating
onMounted(() => {
    localDerivatives.value.forEach(derivative => {
        if (['QUEUED', 'PROCESSING'].includes(derivative.generation_status)) {
            startPolling(derivative.id);
        }
    });
});

// Clean up polling on unmount
onUnmounted(() => {
    pollingIntervals.value.forEach((interval) => {
        clearInterval(interval);
    });
    pollingIntervals.value.clear();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Channel Pills -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-50">
                        Channel Derivatives
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Create and manage content for each channel.
                    </p>
                </div>
                <div v-if="!ai.has_openai" class="flex items-center gap-2 text-xs text-amber-700 dark:text-amber-300">
                    <AlertCircle class="h-4 w-4" />
                    <span>OpenAI key missing.</span>
                    <a :href="ai.settings_url" class="underline">Configure in AI settings</a>
                </div>
            </div>

            <!-- Channel Tabs -->
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="channel in channels"
                    :key="channel.id"
                    class="group relative flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition"
                    :class="[
                        selectedChannelId === channel.id
                            ? 'border-primary bg-primary/5 text-primary'
                            : 'border-border bg-card text-muted-foreground hover:border-primary/50 hover:bg-muted',
                    ]"
                    @click="selectedChannelId = channel.id"
                >
                    <span>{{ channel.name }}</span>
                    <!-- Status indicator dot -->
                    <span
                        v-if="localDerivatives.find(d => d.channel_id === channel.id)"
                        class="h-2 w-2 rounded-full"
                        :class="getStatusDotColor(localDerivatives.find(d => d.channel_id === channel.id)!.status)"
                    />
                    <span
                        v-else
                        class="flex h-4 w-4 items-center justify-center rounded-full border border-dashed border-gray-300 text-[10px] text-gray-400"
                    >
                        <Plus class="h-3 w-3" />
                    </span>
                </button>
            </div>
        </div>

        <!-- Derivative Editor -->
        <div v-if="selectedChannel" class="space-y-6 rounded-lg border bg-card p-4">
            <!-- No derivative yet -->
            <div v-if="!selectedDerivative" class="flex flex-col items-center justify-center py-12 text-center">
                <div class="mb-4 rounded-full bg-muted p-4">
                    <Pencil class="h-8 w-8 text-muted-foreground" />
                </div>
                <h4 class="mb-2 text-lg font-medium">No {{ selectedChannel.name }} derivative yet</h4>
                <p class="mb-4 text-sm text-muted-foreground">
                    Create a derivative for this channel to start generating content.
                </p>
                <Button @click="createDerivative">
                    <Plus class="mr-2 h-4 w-4" />
                    Create {{ selectedChannel.name }} Derivative
                </Button>
            </div>

            <!-- Derivative exists -->
            <template v-else>
                <!-- Header with status -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <h4 class="text-lg font-semibold">{{ selectedChannel.name }}</h4>
                        <Badge
                            v-if="getGenerationStatusBadge(selectedDerivative)"
                            :variant="getGenerationStatusBadge(selectedDerivative)?.variant"
                            class="gap-1"
                        >
                            <Spinner v-if="isGenerating" class="h-3 w-3" />
                            <CheckCircle2 v-else-if="selectedDerivative.generation_status === 'COMPLETED'" class="h-3 w-3" />
                            <X v-else-if="selectedDerivative.generation_status === 'FAILED'" class="h-3 w-3" />
                            {{ getGenerationStatusBadge(selectedDerivative)?.text }}
                        </Badge>
                    </div>
                    <Button
                        size="sm"
                        :disabled="derivativeForm.processing"
                        @click="saveDerivative"
                    >
                        {{ derivativeForm.processing ? 'Saving...' : 'Save' }}
                    </Button>
                </div>

                <!-- Error message -->
                <div
                    v-if="selectedDerivative.generation_error"
                    class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-300"
                >
                    <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{{ selectedDerivative.generation_error }}</span>
                </div>

                <!-- Form fields -->
                <div class="grid gap-6 md:grid-cols-3 lg:grid-cols-4">
                    <!-- Left column: Settings -->
                    <div class="space-y-4">
                        <!-- Status -->
                        <div class="space-y-2">
                            <Label for="status">Status</Label>
                            <Select v-model="derivativeForm.status">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in derivativeStatusOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span class="h-2 w-2 rounded-full" :class="option.dotColor" />
                                            {{ option.label }}
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="derivativeForm.errors.status" />
                        </div>

                        <!-- Prompt -->
                        <div class="space-y-2">
                            <Label for="prompt_id">Prompt Template</Label>
                            <div class="flex items-center gap-2">
                                <Select v-model="derivativeForm.prompt_id" class="flex-1">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select prompt" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="prompt in channelPrompts"
                                            :key="prompt.id"
                                            :value="prompt.id"
                                        >
                                            {{ prompt.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button
                                    variant="outline"
                                    :disabled="!canGenerate"
                                    @click="generate"
                                >
                                    <Spinner v-if="isGenerating" class="h-4 w-4" />
                                    <Sparkles v-else class="h-4 w-4" />
                                    Generate
                                </Button>
                            </div>
                            <InputError :message="derivativeForm.errors.prompt_id" />
                        </div>

                        <!-- Planned publish date -->
                        <div class="space-y-2">
                            <Label for="planned_publish_at">
                                <div class="flex items-center gap-1">
                                    <Clock class="h-3.5 w-3.5" />
                                    Planned Publish Date
                                </div>
                            </Label>
                            <DatePicker
                                id="planned_publish_at"
                                v-model="derivativeForm.planned_publish_at"
                                placeholder="Select publish date"
                            />
                            <InputError :message="derivativeForm.errors.planned_publish_at" />
                        </div>
                    </div>

                    <!-- Middle column: Content -->
                    <div class="space-y-4 md:col-span-2 lg:col-span-2">
                        <!-- Title -->
                        <div class="space-y-2">
                            <Label for="title">Title</Label>
                            <Input
                                id="title"
                                v-model="derivativeForm.title"
                                :disabled="isGenerating"
                                placeholder="Enter title..."
                            />
                            <InputError :message="derivativeForm.errors.title" />
                        </div>

                        <!-- Content Editor -->
                        <div class="space-y-2">
                            <Label>Content</Label>
                            <TiptapEditor
                                v-model="derivativeForm.text"
                                :content-type="contentTypeForEditor"
                                :disabled="isGenerating"
                                placeholder="Start writing or generate content..."
                                @content-type-change="contentTypeForEditor = $event"
                            />
                            <InputError :message="derivativeForm.errors.text" />
                        </div>

                        <!-- Media Attachments -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <Label>
                                    <div class="flex items-center gap-1.5">
                                        <Image class="h-4 w-4" />
                                        Media Attachments
                                    </div>
                                </Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="openMediaPicker"
                                >
                                    <Plus class="mr-1 h-4 w-4" />
                                    Add Media
                                </Button>
                            </div>

                            <div
                                v-if="attachedMedia.length === 0"
                                class="flex items-center justify-center rounded-lg border border-dashed py-8 text-center"
                            >
                                <p class="text-sm text-muted-foreground">
                                    No media attached to this derivative.
                                </p>
                            </div>

                            <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                <div
                                    v-for="item in attachedMedia"
                                    :key="item.id"
                                    class="group relative overflow-hidden rounded-lg border bg-muted/30"
                                >
                                    <img
                                        v-if="item.mime_type.startsWith('image/')"
                                        :src="item.temporary_url"
                                        :alt="item.filename"
                                        class="aspect-video w-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex aspect-video items-center justify-center bg-muted"
                                    >
                                        <span class="text-xs font-medium text-muted-foreground">
                                            {{ item.mime_type.split('/')[1]?.toUpperCase() || 'FILE' }}
                                        </span>
                                    </div>
                                    <div
                                        class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity group-hover:opacity-100"
                                    >
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            @click="removeMedia(item.id)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                    <div class="p-2">
                                        <p class="truncate text-xs text-muted-foreground">
                                            {{ item.filename }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right column: Activity Feed -->
                    <div class="hidden lg:block">
                        <div class="sticky top-4 max-h-[calc(100vh-12rem)] rounded-lg border bg-card">
                            <ActivityFeed
                                ref="activityFeedRef"
                                :content-piece-id="contentPieceId"
                                :derivative-id="selectedDerivative.id"
                            />
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- No channels configured -->
        <div v-else class="rounded-lg border border-dashed p-8 text-center">
            <AlertCircle class="mx-auto h-8 w-8 text-muted-foreground" />
            <h4 class="mt-2 font-medium">No Channels Configured</h4>
            <p class="mt-1 text-sm text-muted-foreground">
                Configure channels in Team Settings to create derivatives.
            </p>
        </div>
    </div>

    <MediaGalleryPicker
        :open="mediaPickerOpen"
        :selected-ids="derivativeForm.media_ids"
        :media="media"
        :tags="mediaTags"
        :multi-select="true"
        @update:open="mediaPickerOpen = $event"
        @select="applyMediaSelection"
    />
</template>
