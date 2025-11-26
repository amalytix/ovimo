<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import { store, update, generate, status, destroy } from '@/actions/App/Http/Controllers/ImageGenerationController';
import { computed, onUnmounted, ref, watch } from 'vue';

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
    aspect_ratio: '16:9' | '1:1' | '4:3' | '9:16';
    status: 'DRAFT' | 'GENERATING' | 'COMPLETED' | 'FAILED';
    media_id: number | null;
    media: ImageMedia | null;
    error_message: string | null;
    created_at: string;
};

const props = defineProps<{
    contentPieceId: number;
    imagePrompts: ImagePrompt[];
    imageGenerations: ImageGeneration[];
    hasEditedText: boolean;
}>();

const emit = defineEmits<{
    (event: 'generations-updated', generations: ImageGeneration[]): void;
}>();

const generations = ref<ImageGeneration[]>([...props.imageGenerations]);
const selectedPromptId = ref<number | null>(props.imagePrompts[0]?.id ?? null);
const selectedAspectRatio = ref<'16:9' | '1:1' | '4:3' | '9:16'>('16:9');
const currentTextPrompt = ref<string>('');
const currentGenerationId = ref<number | null>(null);
const isGeneratingPrompt = ref(false);
const isGeneratingImage = ref(false);
const error = ref<string | null>(null);

let pollingInterval: number | null = null;

const aspectRatios = [
    { value: '16:9', label: '16:9 (Landscape)' },
    { value: '1:1', label: '1:1 (Square)' },
    { value: '4:3', label: '4:3 (Standard)' },
    { value: '9:16', label: '9:16 (Portrait)' },
] as const;

const canGeneratePrompt = computed(() => {
    return props.hasEditedText && selectedPromptId.value !== null && !isGeneratingPrompt.value;
});

const canGenerateImage = computed(() => {
    return currentTextPrompt.value.trim().length > 0 && currentGenerationId.value !== null && !isGeneratingImage.value;
});

const completedGenerations = computed(() => {
    return generations.value.filter((g) => g.status === 'COMPLETED' && g.media);
});

const generateTextPrompt = async () => {
    if (!canGeneratePrompt.value) return;

    error.value = null;
    isGeneratingPrompt.value = true;

    try {
        const response = await fetch(store.url(props.contentPieceId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                prompt_id: selectedPromptId.value,
                aspect_ratio: selectedAspectRatio.value,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to generate prompt');
        }

        const newGeneration = data.image_generation as ImageGeneration;
        generations.value.unshift(newGeneration);
        currentGenerationId.value = newGeneration.id;
        currentTextPrompt.value = newGeneration.generated_text_prompt || '';
        emit('generations-updated', generations.value);
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Failed to generate prompt';
    } finally {
        isGeneratingPrompt.value = false;
    }
};

const updateTextPrompt = async () => {
    if (!currentGenerationId.value || !currentTextPrompt.value.trim()) return;

    try {
        const response = await fetch(update.url([props.contentPieceId, currentGenerationId.value]), {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                generated_text_prompt: currentTextPrompt.value,
                aspect_ratio: selectedAspectRatio.value,
            }),
        });

        if (!response.ok) {
            const data = await response.json();
            throw new Error(data.message || 'Failed to update prompt');
        }
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Failed to update prompt';
    }
};

const generateImage = async () => {
    if (!canGenerateImage.value) return;

    // First save any edits to the prompt
    await updateTextPrompt();

    error.value = null;
    isGeneratingImage.value = true;

    try {
        const response = await fetch(generate.url([props.contentPieceId, currentGenerationId.value!]), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to start image generation');
        }

        // Start polling for status
        startPolling(currentGenerationId.value!);
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Failed to generate image';
        isGeneratingImage.value = false;
    }
};

const startPolling = (generationId: number) => {
    stopPolling();

    pollingInterval = window.setInterval(async () => {
        try {
            const response = await fetch(status.url([props.contentPieceId, generationId]));
            const data = await response.json();
            const updatedGeneration = data.image_generation as ImageGeneration;

            // Update the generation in our list
            const index = generations.value.findIndex((g) => g.id === generationId);
            if (index !== -1) {
                generations.value[index] = updatedGeneration;
            }

            if (updatedGeneration.status === 'COMPLETED') {
                stopPolling();
                isGeneratingImage.value = false;
                currentTextPrompt.value = '';
                currentGenerationId.value = null;
                emit('generations-updated', generations.value);
            } else if (updatedGeneration.status === 'FAILED') {
                stopPolling();
                isGeneratingImage.value = false;
                error.value = updatedGeneration.error_message || 'Image generation failed';
            }
        } catch (e) {
            console.error('Polling error:', e);
        }
    }, 3000);
};

const stopPolling = () => {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
};

const deleteGeneration = async (generationId: number) => {
    try {
        const response = await fetch(destroy.url([props.contentPieceId, generationId], { query: { detach_media: 'true' } }), {
            method: 'DELETE',
            headers: {
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            const data = await response.json();
            throw new Error(data.message || 'Failed to delete generation');
        }

        generations.value = generations.value.filter((g) => g.id !== generationId);
        emit('generations-updated', generations.value);
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Failed to delete generation';
    }
};

const getCsrfToken = (): string => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

// Watch for external updates to generations
watch(
    () => props.imageGenerations,
    (newGenerations) => {
        generations.value = [...newGenerations];
    }
);

onUnmounted(() => {
    stopPolling();
});
</script>

<template>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <!-- Left Column: Generation Form -->
        <div class="space-y-6">
            <div class="space-y-4 rounded-lg border border-dashed p-4">
                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-50">Generate New Image</h3>

                <!-- Warning if no edited text -->
                <div v-if="!hasEditedText" class="rounded-md bg-amber-50 p-3 text-sm text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                    Please add content in the Editing tab before generating images.
                </div>

                <!-- Prompt Template Selection -->
                <div class="space-y-2">
                    <Label for="image_prompt_id">Image Prompt</Label>
                    <Select v-model="selectedPromptId">
                        <SelectTrigger>
                            <SelectValue placeholder="Select image prompt" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="prompt in imagePrompts" :key="prompt.id" :value="prompt.id">
                                {{ prompt.internal_name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="imagePrompts.length === 0" class="text-xs text-muted-foreground">No image prompts available. Create one in Settings &gt; Prompts.</p>
                </div>

                <!-- Aspect Ratio Selection -->
                <div class="space-y-2">
                    <Label>Aspect Ratio</Label>
                    <Select v-model="selectedAspectRatio">
                        <SelectTrigger>
                            <SelectValue placeholder="Select aspect ratio" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="ratio in aspectRatios" :key="ratio.value" :value="ratio.value">
                                {{ ratio.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Generate Text Prompt Button -->
                <Button type="button" class="w-full" :disabled="!canGeneratePrompt" @click="generateTextPrompt">
                    <Spinner v-if="isGeneratingPrompt" class="mr-2" />
                    {{ isGeneratingPrompt ? 'Generating Prompt...' : 'Generate Text Prompt' }}
                </Button>
            </div>

            <!-- Current Text Prompt (editable) -->
            <div v-if="currentTextPrompt || currentGenerationId" class="space-y-4 rounded-lg border border-dashed p-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-50">Generated Prompt</h3>
                    <span v-if="isGeneratingImage" class="rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/40 dark:text-blue-100">
                        Generating...
                    </span>
                </div>

                <div class="space-y-2">
                    <Label for="text_prompt">Edit prompt if needed</Label>
                    <textarea
                        id="text_prompt"
                        v-model="currentTextPrompt"
                        rows="6"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        placeholder="The generated image prompt will appear here..."
                        :disabled="isGeneratingImage"
                    />
                </div>

                <div class="flex gap-2">
                    <Button type="button" variant="secondary" class="flex-1" :disabled="!canGeneratePrompt" @click="generateTextPrompt">
                        <Spinner v-if="isGeneratingPrompt" class="mr-2" />
                        Regenerate
                    </Button>
                    <Button type="button" class="flex-1" :disabled="!canGenerateImage" @click="generateImage">
                        <Spinner v-if="isGeneratingImage" class="mr-2" />
                        {{ isGeneratingImage ? 'Generating...' : 'Generate Image' }}
                    </Button>
                </div>
            </div>

            <!-- Error Display -->
            <InputError v-if="error" :message="error" />
        </div>

        <!-- Right Column: Generated Images -->
        <div class="space-y-4 md:col-span-2">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <Label>Generated Images</Label>
                    <p class="text-xs text-muted-foreground">
                        {{ completedGenerations.length }} image{{ completedGenerations.length !== 1 ? 's' : '' }} generated
                    </p>
                </div>
            </div>

            <div v-if="completedGenerations.length === 0" class="flex min-h-[300px] items-center justify-center rounded-lg border border-dashed">
                <p class="text-sm text-muted-foreground">No images generated yet.</p>
            </div>

            <div v-else class="grid grid-cols-2 gap-4 lg:grid-cols-3">
                <div v-for="generation in completedGenerations" :key="generation.id" class="group relative overflow-hidden rounded-lg border bg-muted/30">
                    <img
                        v-if="generation.media"
                        :src="generation.media.temporary_url"
                        :alt="generation.media.filename"
                        class="aspect-video w-full object-cover"
                    />
                    <div class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                        <div class="flex gap-2">
                            <Button variant="secondary" size="sm" as="a" :href="generation.media?.temporary_url" target="_blank"> View </Button>
                            <Button variant="destructive" size="sm" @click="deleteGeneration(generation.id)"> Remove </Button>
                        </div>
                    </div>
                    <div class="p-2">
                        <p class="truncate text-xs text-muted-foreground">{{ generation.media?.filename }}</p>
                        <p class="text-xs text-muted-foreground">{{ generation.aspect_ratio }}</p>
                    </div>
                </div>
            </div>

            <!-- Generating/Failed generations -->
            <div v-if="generations.filter((g) => g.status === 'GENERATING').length > 0" class="rounded-lg border bg-blue-50 p-4 dark:bg-blue-900/20">
                <div class="flex items-center gap-2">
                    <Spinner />
                    <p class="text-sm text-blue-700 dark:text-blue-300">Generating image...</p>
                </div>
            </div>

            <div v-for="gen in generations.filter((g) => g.status === 'FAILED')" :key="`failed-${gen.id}`" class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-900/20">
                <p class="text-sm font-medium text-red-700 dark:text-red-300">Generation Failed</p>
                <p class="text-xs text-red-600 dark:text-red-400">{{ gen.error_message }}</p>
                <Button variant="ghost" size="sm" class="mt-2" @click="deleteGeneration(gen.id)"> Dismiss </Button>
            </div>
        </div>
    </div>
</template>
