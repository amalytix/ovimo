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
import { router, useForm } from '@inertiajs/vue3';
import { ExternalLink, FileText, GripVertical, Link2, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { store as storeSource, destroy as destroySource, reorder as reorderSources } from '@/actions/App/Http/Controllers/BackgroundSourceController';

type Post = {
    id: number;
    uri: string;
    summary: string;
    external_title?: string | null;
    internal_title?: string | null;
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

const props = defineProps<{
    contentPieceId: number;
    sources: BackgroundSource[];
    availablePosts: Post[];
}>();

const showAddPostDialog = ref(false);
const showAddManualDialog = ref(false);
const postSearchQuery = ref('');

const filteredPosts = computed(() => {
    const existingPostIds = props.sources
        .filter(s => s.type === 'POST')
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
    props.sources.filter(s => s.type === 'POST')
);

const manualSources = computed(() =>
    props.sources.filter(s => s.type === 'MANUAL')
);

const manualForm = useForm({
    type: 'MANUAL' as const,
    title: '',
    content: '',
    url: '',
});

const addPost = (postId: number) => {
    router.post(storeSource.url(props.contentPieceId), {
        type: 'POST',
        post_id: postId,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showAddPostDialog.value = false;
        },
    });
};

const addManualSource = () => {
    manualForm.post(storeSource.url(props.contentPieceId), {
        preserveScroll: true,
        onSuccess: () => {
            showAddManualDialog.value = false;
            manualForm.reset();
        },
    });
};

const removeSource = (sourceId: number) => {
    router.delete(destroySource.url([props.contentPieceId, sourceId]), {
        preserveScroll: true,
    });
};

const getPostTitle = (source: BackgroundSource): string => {
    if (source.post) {
        return source.post.internal_title || source.post.external_title || 'Untitled';
    }
    return 'Unknown Post';
};
</script>

<template>
    <div class="space-y-6">
        <!-- Research Posts Section -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-50">
                        Research Posts
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Articles and posts used as background research for AI generation.
                    </p>
                </div>
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
                    v-for="source in postSources"
                    :key="source.id"
                    class="flex items-start gap-3 rounded-lg border bg-card p-3"
                >
                    <GripVertical class="mt-1 h-4 w-4 cursor-grab text-gray-400" />
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="truncate font-medium">{{ getPostTitle(source) }}</span>
                            <Badge variant="secondary" class="shrink-0">Post</Badge>
                        </div>
                        <p v-if="source.post?.summary" class="mt-1 line-clamp-2 text-xs text-gray-500">
                            {{ source.post.summary }}
                        </p>
                        <a
                            v-if="source.post?.uri"
                            :href="source.post.uri"
                            target="_blank"
                            class="mt-1 flex items-center gap-1 text-xs text-blue-600 hover:underline"
                        >
                            <ExternalLink class="h-3 w-3" />
                            {{ source.post.uri }}
                        </a>
                    </div>
                    <Button size="icon" variant="ghost" @click="removeSource(source.id)">
                        <Trash2 class="h-4 w-4 text-red-500" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- Manual Sources Section -->
        <div class="space-y-4 border-t pt-6">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-50">
                        Manual Sources
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Custom text content to include in AI generation context.
                    </p>
                </div>
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
                    v-for="source in manualSources"
                    :key="source.id"
                    class="flex items-start gap-3 rounded-lg border bg-card p-3"
                >
                    <GripVertical class="mt-1 h-4 w-4 cursor-grab text-gray-400" />
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
                    <Button size="icon" variant="ghost" @click="removeSource(source.id)">
                        <Trash2 class="h-4 w-4 text-red-500" />
                    </Button>
                </div>
            </div>
        </div>
    </div>

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
                        @click="addPost(post.id)"
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
                    <Label for="title">Title</Label>
                    <Input
                        id="title"
                        v-model="manualForm.title"
                        placeholder="Source title"
                        required
                    />
                </div>

                <div class="space-y-2">
                    <Label for="content">Content</Label>
                    <textarea
                        id="content"
                        v-model="manualForm.content"
                        placeholder="Paste or type content here..."
                        rows="6"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    />
                </div>

                <div class="space-y-2">
                    <Label for="url">URL (optional)</Label>
                    <Input
                        id="url"
                        v-model="manualForm.url"
                        type="url"
                        placeholder="https://..."
                    />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="showAddManualDialog = false">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="manualForm.processing">
                        Add Source
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
