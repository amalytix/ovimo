<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ExternalLink, FileText, GripVertical, Link2, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Post = {
    id: number;
    uri: string;
    summary: string;
    external_title: string | null;
    internal_title: string | null;
};

interface Props {
    availablePosts: Post[];
    preselectedPostIds?: number[];
    initialTitle?: string | null;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Content Pieces', href: '/content-pieces' },
    { title: 'Create', href: '/content-pieces/create' },
];

// Source types for the form
type PostSource = {
    type: 'POST';
    post_id: number;
    post: Post;
};

type ManualSource = {
    type: 'MANUAL';
    title: string;
    content: string;
    url: string;
};

type FormSource = PostSource | ManualSource;

const form = useForm({
    internal_name: props.initialTitle || '',
    target_language: 'ENGLISH',
    published_at: '' as string | null,
});

// Sources management (local state, sent on submit)
const sources = ref<FormSource[]>([]);

// Initialize with preselected posts
if (props.preselectedPostIds && props.preselectedPostIds.length > 0) {
    props.preselectedPostIds.forEach(postId => {
        const post = props.availablePosts.find(p => p.id === postId);
        if (post) {
            sources.value.push({
                type: 'POST',
                post_id: post.id,
                post,
            });
        }
    });
}

const showAddPostDialog = ref(false);
const showAddManualDialog = ref(false);
const postSearchQuery = ref('');

const manualForm = useForm({
    title: '',
    content: '',
    url: '',
});

const filteredPosts = computed(() => {
    const existingPostIds = sources.value
        .filter((s): s is PostSource => s.type === 'POST')
        .map(s => s.post_id);

    return props.availablePosts
        .filter(p => !existingPostIds.includes(p.id))
        .filter(p => {
            if (!postSearchQuery.value) return true;
            const query = postSearchQuery.value.toLowerCase();
            const title = (p.internal_title || p.external_title || '').toLowerCase();
            return title.includes(query) || p.uri.toLowerCase().includes(query);
        });
});

const postSources = computed(() =>
    sources.value.filter((s): s is PostSource => s.type === 'POST')
);

const manualSources = computed(() =>
    sources.value.filter((s): s is ManualSource => s.type === 'MANUAL')
);

const addPost = (post: Post) => {
    sources.value.push({
        type: 'POST',
        post_id: post.id,
        post,
    });
    showAddPostDialog.value = false;
    postSearchQuery.value = '';
};

const addManualSource = () => {
    if (!manualForm.title.trim()) return;

    sources.value.push({
        type: 'MANUAL',
        title: manualForm.title,
        content: manualForm.content,
        url: manualForm.url,
    });
    showAddManualDialog.value = false;
    manualForm.reset();
};

const removeSource = (index: number) => {
    sources.value.splice(index, 1);
};

const getPostTitle = (source: PostSource): string => {
    return source.post.internal_title || source.post.external_title || 'Untitled';
};

const serializePublishedAt = (value: string | null) => {
    if (!value) return null;
    const date = new Date(value);
    return date.toISOString();
};

const save = () => {
    // Transform sources for API
    const sourcesData = sources.value.map((source, index) => ({
        type: source.type,
        post_id: source.type === 'POST' ? source.post_id : null,
        title: source.type === 'MANUAL' ? source.title : null,
        content: source.type === 'MANUAL' ? source.content : null,
        url: source.type === 'MANUAL' ? source.url : null,
        sort_order: index,
    }));

    router.post('/content-pieces', {
        internal_name: form.internal_name,
        target_language: form.target_language,
        published_at: serializePublishedAt(form.published_at),
        sources: sourcesData,
    }, {
        preserveScroll: true,
    });
};

const cancel = () => {
    router.visit('/content-pieces');
};
</script>

<template>
    <Head title="Create Content Piece" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl space-y-6 p-6">
            <div>
                <h1 class="text-2xl font-semibold">Create Content Piece</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Create a new content piece and add research sources. After saving, you can generate content for different channels.
                </p>
            </div>

            <!-- Basic Info -->
            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-medium">Basic Information</h2>
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-2 md:col-span-2">
                        <Label for="internal_name">Name</Label>
                        <Input
                            id="internal_name"
                            v-model="form.internal_name"
                            type="text"
                            placeholder="Enter a name for this content piece"
                            required
                        />
                        <p v-if="form.errors.internal_name" class="text-sm text-destructive">
                            {{ form.errors.internal_name }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="target_language">Target Language</Label>
                        <Select v-model="form.target_language">
                            <SelectTrigger>
                                <SelectValue placeholder="Select language" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="ENGLISH">English</SelectItem>
                                <SelectItem value="GERMAN">German</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="published_at">Planned Publish Date (optional)</Label>
                        <Input
                            id="published_at"
                            v-model="form.published_at"
                            type="datetime-local"
                        />
                    </div>
                </div>
            </div>

            <!-- Sources Section -->
            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-medium">Research Sources</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Add posts and manual content to use as background research for AI generation.
                        </p>
                    </div>
                </div>

                <div class="space-y-6">
                    <!-- Research Posts Section -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-50">
                                Research Posts
                            </h3>
                            <Button size="sm" variant="outline" @click="showAddPostDialog = true">
                                <Plus class="mr-1 h-4 w-4" />
                                Add Post
                            </Button>
                        </div>

                        <div v-if="postSources.length === 0" class="rounded-lg border border-dashed p-6 text-center">
                            <FileText class="mx-auto h-8 w-8 text-gray-400" />
                            <p class="mt-2 text-sm text-gray-500">No research posts added yet.</p>
                        </div>

                        <div v-else class="space-y-2">
                            <div
                                v-for="(source, index) in postSources"
                                :key="`post-${source.post_id}`"
                                class="flex items-start gap-3 rounded-lg border bg-card p-3"
                            >
                                <GripVertical class="mt-1 h-4 w-4 text-gray-400" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="truncate font-medium">{{ getPostTitle(source) }}</span>
                                        <Badge variant="secondary" class="shrink-0">Post</Badge>
                                    </div>
                                    <p v-if="source.post.summary" class="mt-1 line-clamp-2 text-xs text-gray-500">
                                        {{ source.post.summary }}
                                    </p>
                                    <a
                                        v-if="source.post.uri"
                                        :href="source.post.uri"
                                        target="_blank"
                                        class="mt-1 flex items-center gap-1 text-xs text-blue-600 hover:underline"
                                    >
                                        <ExternalLink class="h-3 w-3" />
                                        {{ source.post.uri }}
                                    </a>
                                </div>
                                <Button size="icon" variant="ghost" @click="removeSource(sources.indexOf(source))">
                                    <Trash2 class="h-4 w-4 text-red-500" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Manual Sources Section -->
                    <div class="space-y-4 border-t pt-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-50">
                                Manual Sources
                            </h3>
                            <Button size="sm" variant="outline" @click="showAddManualDialog = true">
                                <Plus class="mr-1 h-4 w-4" />
                                Add Source
                            </Button>
                        </div>

                        <div v-if="manualSources.length === 0" class="rounded-lg border border-dashed p-6 text-center">
                            <Link2 class="mx-auto h-8 w-8 text-gray-400" />
                            <p class="mt-2 text-sm text-gray-500">No manual sources added yet.</p>
                        </div>

                        <div v-else class="space-y-2">
                            <div
                                v-for="(source, index) in manualSources"
                                :key="`manual-${index}`"
                                class="flex items-start gap-3 rounded-lg border bg-card p-3"
                            >
                                <GripVertical class="mt-1 h-4 w-4 text-gray-400" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="truncate font-medium">{{ source.title || 'Untitled' }}</span>
                                        <Badge variant="outline" class="shrink-0">Manual</Badge>
                                    </div>
                                    <p v-if="source.content" class="mt-1 line-clamp-2 text-xs text-gray-500">
                                        {{ source.content }}
                                    </p>
                                    <a
                                        v-if="source.url"
                                        :href="source.url"
                                        target="_blank"
                                        class="mt-1 flex items-center gap-1 text-xs text-blue-600 hover:underline"
                                    >
                                        <ExternalLink class="h-3 w-3" />
                                        {{ source.url }}
                                    </a>
                                </div>
                                <Button size="icon" variant="ghost" @click="removeSource(sources.indexOf(source))">
                                    <Trash2 class="h-4 w-4 text-red-500" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-3">
                <Button variant="outline" @click="cancel">Cancel</Button>
                <Button
                    :disabled="form.processing || !form.internal_name.trim()"
                    @click="save"
                >
                    {{ form.processing ? 'Creating...' : 'Create Content Piece' }}
                </Button>
            </div>
        </div>
    </AppLayout>

    <!-- Add Post Dialog -->
    <Dialog v-model:open="showAddPostDialog">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Add Research Post</DialogTitle>
                <DialogDescription>
                    Select a post to add as background research.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <Input
                    v-model="postSearchQuery"
                    placeholder="Search posts..."
                    class="w-full"
                />

                <div class="max-h-64 space-y-2 overflow-y-auto">
                    <div
                        v-for="post in filteredPosts"
                        :key="post.id"
                        class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 hover:bg-muted"
                        @click="addPost(post)"
                    >
                        <FileText class="h-5 w-5 shrink-0 text-gray-400" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">
                                {{ post.internal_title || post.external_title || 'Untitled' }}
                            </p>
                            <p class="truncate text-xs text-gray-500">{{ post.uri }}</p>
                        </div>
                    </div>

                    <p v-if="filteredPosts.length === 0" class="py-4 text-center text-sm text-gray-500">
                        No matching posts found.
                    </p>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Add Manual Source Dialog -->
    <Dialog v-model:open="showAddManualDialog">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Add Manual Source</DialogTitle>
                <DialogDescription>
                    Add custom content to include in AI generation context.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="addManualSource" class="space-y-4">
                <div class="space-y-2">
                    <Label for="manual_title">Title</Label>
                    <Input
                        id="manual_title"
                        v-model="manualForm.title"
                        placeholder="Source title"
                        required
                    />
                </div>

                <div class="space-y-2">
                    <Label for="manual_content">Content</Label>
                    <textarea
                        id="manual_content"
                        v-model="manualForm.content"
                        placeholder="Paste or type content here..."
                        rows="6"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    />
                </div>

                <div class="space-y-2">
                    <Label for="manual_url">URL (optional)</Label>
                    <Input
                        id="manual_url"
                        v-model="manualForm.url"
                        type="url"
                        placeholder="https://..."
                    />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="showAddManualDialog = false">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="!manualForm.title.trim()">
                        Add Source
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
