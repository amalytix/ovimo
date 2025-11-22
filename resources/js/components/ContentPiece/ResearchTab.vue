<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { computed, ref } from 'vue';

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

const postSearch = ref('');

const filteredPosts = computed(() => {
    if (!postSearch.value) {
        return props.posts;
    }
    const q = postSearch.value.toLowerCase();
    return props.posts.filter((post) => {
        const title = post.external_title || post.internal_title || post.uri;
        return title.toLowerCase().includes(q) || (post.summary || '').toLowerCase().includes(q);
    });
});

const promptOptions = computed(() => props.prompts ?? []);

const togglePost = (postId: number, checked: boolean) => {
    if (checked) {
        props.form.post_ids = [...props.form.post_ids, postId];
    } else {
        props.form.post_ids = props.form.post_ids.filter((id: number) => id !== postId);
    }
};
</script>

<template>
    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3 items-start">
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

            <div class="space-y-2 md:col-span-1 md:col-start-2 md:col-end-3">
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

            <div class="flex flex-col justify-start gap-2">
                <Label class="invisible">Generate</Label>
                <Button type="button" class="self-start" :disabled="!form.prompt_id" @click="emit('generate')">
                    Generate Content
                </Button>
            </div>
        </div>

        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Label>Source posts</Label>
                    <Badge variant="outline">{{ form.post_ids?.length || 0 }} selected</Badge>
                </div>
                <Input v-model="postSearch" class="w-64" placeholder="Search posts..." />
            </div>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div
                    v-for="post in filteredPosts"
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

        <div class="space-y-2">
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
                rows="12"
                class="w-full rounded-lg border border-input bg-background p-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                readonly
                placeholder="Start generation to populate research text."
            />
            <InputError :message="form.errors.research_text" />
            <p v-if="generationStatus.error" class="text-sm text-red-500">{{ generationStatus.error }}</p>
        </div>
    </div>
</template>
