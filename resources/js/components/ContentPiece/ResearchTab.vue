<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import { computed } from 'vue';

type Prompt = {
    id: number;
    name: string;
    channel?: string;
};

type Post = {
    id: number;
    uri: string;
    summary: string;
    external_title?: string | null;
    internal_title?: string | null;
};

type GenerationStatus = {
    status: string | null;
    error?: string | null;
};

const props = defineProps<{
    form: Record<string, any>;
    prompts: Prompt[];
    posts: Post[];
    generationStatus: GenerationStatus;
}>();

const emit = defineEmits<{
    (event: 'copy-to-editor'): void;
    (event: 'generate'): void;
}>();

const promptOptions = computed(() => props.prompts ?? []);

const isGenerating = computed(() => {
    return props.generationStatus.status === 'PROCESSING' || props.generationStatus.status === 'QUEUED';
});

const togglePost = (postId: number, checked: boolean) => {
    if (checked) {
        props.form.post_ids = [...props.form.post_ids, postId];
    } else {
        props.form.post_ids = props.form.post_ids.filter((id: number) => id !== postId);
    }
};
</script>

<template>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <!-- Left Column: 1/3 width -->
        <div class="space-y-6">
            <!-- Prompt Template -->
            <div class="space-y-2">
                <Label for="prompt_id">Prompt template</Label>
                <Select v-model="form.prompt_id">
                    <SelectTrigger>
                        <SelectValue placeholder="Select prompt" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="prompt in promptOptions" :key="prompt.id" :value="prompt.id">
                            {{ prompt.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.prompt_id" />
            </div>

            <!-- Briefing -->
            <div class="space-y-2">
                <Label for="briefing_text">Briefing</Label>
                <textarea
                    id="briefing_text"
                    v-model="form.briefing_text"
                    rows="3"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    placeholder="Tone, audience, notes..."
                />
                <InputError :message="form.errors.briefing_text" />
            </div>

            <!-- Source Posts -->
            <div class="space-y-3">
                <div class="flex items-center gap-2">
                    <Label>Source posts</Label>
                    <Badge variant="outline">{{ form.post_ids?.length || 0 }} selected</Badge>
                </div>
                <div class="space-y-2">
                    <div
                        v-for="post in posts"
                        :key="post.id"
                        class="rounded-lg border bg-card p-3 shadow-sm transition hover:border-border/80"
                    >
                        <div class="flex items-start gap-2">
                            <Checkbox :id="`post-${post.id}`" :model-value="form.post_ids.includes(post.id)" @update:model-value="(v: boolean) => togglePost(post.id, v)" />
                            <div class="space-y-1">
                                <label :for="`post-${post.id}`" class="block text-sm font-semibold">
                                    {{ post.external_title || post.internal_title || post.uri }}
                                </label>
                                <p class="line-clamp-2 text-xs text-muted-foreground">{{ post.summary }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <InputError :message="form.errors.post_ids" />
            </div>

            <!-- Generate Button -->
            <Button type="button" class="w-full" :disabled="!form.prompt_id || isGenerating" @click="emit('generate')">
                <Spinner v-if="isGenerating" class="mr-2" />
                {{ isGenerating ? 'Generating...' : 'Generate Content' }}
            </Button>
        </div>

        <!-- Right Column: 2/3 width -->
        <div class="space-y-2 md:col-span-2">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <Label for="research_text">Research text</Label>
                    <p class="text-xs text-muted-foreground">AI output lands here; copy it into the editor when ready.</p>
                </div>
                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                    <span
                        :class="[
                            'rounded-full px-3 py-1 text-xs font-medium',
                            generationStatus.status === 'PROCESSING' || generationStatus.status === 'QUEUED'
                                ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-100'
                                : generationStatus.status === 'COMPLETED'
                                  ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-100'
                                  : generationStatus.status === 'FAILED'
                                    ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-100'
                                    : 'bg-muted text-foreground',
                        ]"
                    >
                        {{ generationStatus.status || 'Idle' }}
                    </span>
                    <Button variant="outline" size="sm" @click="emit('copy-to-editor')">Copy to editing tab</Button>
                </div>
            </div>
            <textarea
                id="research_text"
                v-model="form.research_text"
                rows="24"
                class="w-full rounded-lg border border-input bg-background p-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                readonly
                placeholder="Start generation to populate research text."
            />
            <InputError :message="form.errors.research_text" />
            <p v-if="generationStatus.error" class="text-sm text-red-500">{{ generationStatus.error }}</p>
        </div>
    </div>
</template>
