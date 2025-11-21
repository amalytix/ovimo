<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Eye, EyeOff, WandSparkles } from 'lucide-vue-next';
import { computed, ref, watch, onUnmounted } from 'vue';

interface Source {
    id: number;
    internal_name: string;
}

interface Tag {
    id: number;
    name: string;
}

interface Post {
    id: number;
    uri: string;
    external_title: string | null;
    internal_title: string | null;
    summary: string | null;
    relevancy_score: number | null;
    is_hidden: boolean;
    status: string;
    found_at: string;
    source: {
        id: number;
        internal_name: string;
    };
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    posts: {
        data: Post[];
        links: PaginationLink[];
        meta?: {
            from?: number;
            to?: number;
            total?: number;
        };
    };
    sources: Source[];
    tags: Tag[];
    filters: {
        source_id: number | null;
        tag_ids: number[];
        search: string | null;
        min_relevancy: number | null;
        show_hidden: boolean | null;
        status: string | null;
        sort_by: string;
        sort_direction: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Posts', href: '/posts' }];

const selectedPosts = ref<number[]>([]);
const localFilters = ref({ ...props.filters });
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const allSelected = computed(() => {
    return props.posts.data.length > 0 && selectedPosts.value.length === props.posts.data.length;
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedPosts.value = props.posts.data.map((p) => p.id);
    } else {
        selectedPosts.value = [];
    }
};

const togglePostSelection = (postId: number, checked: boolean) => {
    if (checked) {
        selectedPosts.value = [...selectedPosts.value, postId];
    } else {
        selectedPosts.value = selectedPosts.value.filter((id) => id !== postId);
    }
};

const applyFilters = () => {
    router.get('/posts', localFilters.value, { preserveState: true, preserveScroll: true });
};

const applyFiltersDebounced = () => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
    debounceTimer = setTimeout(() => {
        applyFilters();
    }, 300);
};

const clearFilters = () => {
    localFilters.value = {
        source_id: null,
        tag_ids: [],
        search: null,
        min_relevancy: null,
        show_hidden: null,
        status: null,
        sort_by: 'found_at',
        sort_direction: 'desc',
    };
    applyFilters();
};

const sortBy = (column: string) => {
    if (localFilters.value.sort_by === column) {
        // Toggle direction if same column
        localFilters.value.sort_direction = localFilters.value.sort_direction === 'asc' ? 'desc' : 'asc';
    } else {
        // New column, default to desc for found_at and relevancy, asc for others
        localFilters.value.sort_by = column;
        localFilters.value.sort_direction = column === 'found_at' || column === 'relevancy' ? 'desc' : 'asc';
    }
    applyFilters();
};

const getSortIcon = (column: string) => {
    if (localFilters.value.sort_by !== column) return '';
    return localFilters.value.sort_direction === 'asc' ? '↑' : '↓';
};

// Watch for filter changes and auto-apply with debounce
watch(
    localFilters,
    () => {
        applyFiltersDebounced();
    },
    { deep: true }
);

onUnmounted(() => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
});

const toggleHidden = (postId: number) => {
    router.patch(`/posts/${postId}/toggle-hidden`, {}, { preserveScroll: true });
};

const updateStatus = (postId: number, status: string) => {
    router.patch(`/posts/${postId}/status`, { status }, { preserveScroll: true });
};

const bulkHide = () => {
    if (selectedPosts.value.length === 0) return;
    router.post('/posts/bulk-hide', { post_ids: selectedPosts.value }, { preserveScroll: true, onSuccess: () => (selectedPosts.value = []) });
};

const bulkDelete = () => {
    if (selectedPosts.value.length === 0) return;
    if (!confirm(`Are you sure you want to delete ${selectedPosts.value.length} post(s)? This action cannot be undone.`)) return;
    router.post('/posts/bulk-delete', { post_ids: selectedPosts.value }, { preserveScroll: true, onSuccess: () => (selectedPosts.value = []) });
};

const hideNotRelevant = () => {
    if (!confirm('Are you sure you want to hide all posts with status "Not Relevant"? This action cannot be undone.')) return;
    router.post('/posts/hide-not-relevant', {}, { preserveScroll: true });
};

const createContentPiece = () => {
    if (selectedPosts.value.length === 0) return;
    const params = new URLSearchParams();
    selectedPosts.value.forEach((id) => params.append('post_ids[]', id.toString()));
    router.visit(`/content-pieces/create?${params.toString()}`);
};

const createContentPieceFromPost = (postId: number) => {
    const params = new URLSearchParams();
    params.append('post_ids[]', postId.toString());
    router.visit(`/content-pieces/create?${params.toString()}`, { preserveScroll: true });
};

watch(
    () => props.posts.data,
    () => {
        selectedPosts.value = [];
    },
);
</script>

<template>
    <Head title="Posts" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Posts</h1>
            </div>

            <!-- Filters -->
            <div class="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                <div class="min-w-32 flex-1">
                    <Input id="search" v-model="localFilters.search" type="text" placeholder="Search..." class="h-9" @keyup.enter="applyFilters" />
                </div>

                <div class="w-60">
                    <Select v-model="localFilters.source_id">
                        <SelectTrigger id="source" class="h-9">
                            <SelectValue placeholder="All sources" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="null">All sources</SelectItem>
                            <SelectItem v-for="source in sources" :key="source.id" :value="source.id">
                                {{ source.internal_name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="w-24">
                    <Input id="min_relevancy" v-model.number="localFilters.min_relevancy" type="number" min="0" max="100" placeholder="Min %" class="h-9" @keyup.enter="applyFilters" />
                </div>

                <div class="w-60">
                    <Select v-model="localFilters.status">
                        <SelectTrigger id="status" class="h-9">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="null">All</SelectItem>
                            <SelectItem value="NOT_RELEVANT">Not Relevant</SelectItem>
                            <SelectItem value="CREATE_CONTENT">Create Content</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="flex items-center gap-2">
                    <Checkbox id="show_hidden" :model-value="localFilters.show_hidden" @update:model-value="localFilters.show_hidden = $event" />
                    <Label for="show_hidden" class="whitespace-nowrap text-sm font-normal">Show hidden</Label>
                </div>

                <Button size="sm" variant="outline" @click="clearFilters" class="h-9">Clear</Button>
            </div>

            <!-- Bulk Actions -->
            <div class="mb-4 flex items-center gap-4 rounded-lg bg-gray-50 p-3 dark:bg-gray-800/50">
                <span class="text-sm font-medium">{{ selectedPosts.length }} selected</span>
                <Button size="sm" :disabled="selectedPosts.length === 0" @click="createContentPiece">
                    <WandSparkles class="mr-2 h-4 w-4 text-gray-700 dark:text-gray-200" />
                    Create Content Piece
                </Button>
                <Button size="sm" variant="outline" :disabled="selectedPosts.length === 0" @click="bulkHide">
                    <EyeOff class="mr-2 h-4 w-4" />
                    Hide
                </Button>
                <Button size="sm" variant="outline" @click="hideNotRelevant">Hide not relevant</Button>
                <Button size="sm" variant="destructive" :disabled="selectedPosts.length === 0" @click="bulkDelete">Delete</Button>
            </div>

            <!-- Posts Table -->
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3">
                                <Checkbox :model-value="allSelected" @update:model-value="(checked: boolean) => toggleSelectAll(checked)" />
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                @click="sortBy('source')"
                            >
                                Source {{ getSortIcon('source') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Title</th>
                            <th class="px-6 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Summary</th>
                            <th
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                @click="sortBy('relevancy')"
                            >
                                Relevancy {{ getSortIcon('relevancy') }}
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                @click="sortBy('status')"
                            >
                                Status {{ getSortIcon('status') }}
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                @click="sortBy('found_at')"
                            >
                                Found {{ getSortIcon('found_at') }}
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        <tr v-for="post in posts.data" :key="post.id" :class="{ 'opacity-50': post.is_hidden }">
                            <td class="px-4 py-4">
                                <Checkbox :model-value="selectedPosts.includes(post.id)" @update:model-value="(checked: boolean) => togglePostSelection(post.id, checked)" />
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ post.source.internal_name }}
                            </td>
                            <td class="max-w-xs px-6 py-4 text-sm">
                                <a :href="post.uri" target="_blank" class="text-blue-600 hover:text-blue-800 hover:underline dark:text-blue-400">
                                    {{ post.external_title || post.internal_title || post.uri }}
                                </a>
                            </td>
                            <td class="max-w-md px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                <div class="line-clamp-4">{{ post.summary || 'No summary' }}</div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span
                                    v-if="post.relevancy_score !== null"
                                    :class="{
                                        'text-green-600': post.relevancy_score >= 70,
                                        'text-yellow-600': post.relevancy_score >= 40 && post.relevancy_score < 70,
                                        'text-red-600': post.relevancy_score < 40,
                                    }"
                                    class="font-medium"
                                >
                                    {{ post.relevancy_score }}%
                                </span>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <Select :model-value="post.status" @update:model-value="updateStatus(post.id, $event)">
                                    <SelectTrigger class="h-8 w-36">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="NOT_RELEVANT">Not Relevant</SelectItem>
                                        <SelectItem value="CREATE_CONTENT">Create Content</SelectItem>
                                    </SelectContent>
                                </Select>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ post.found_at }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-3">
                                    <button
                                        type="button"
                                        class="text-blue-500 hover:text-blue-800 dark:text-blue-300"
                                        title="Create content piece"
                                        @click="createContentPieceFromPost(post.id)"
                                    >
                                        <WandSparkles class="h-4 w-4" />
                                        <span class="sr-only">Create content piece</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="text-gray-500 hover:text-gray-800 dark:text-gray-200"
                                        :title="post.is_hidden ? 'Unhide post' : 'Hide post'"
                                        @click="toggleHidden(post.id)"
                                    >
                                        <component :is="post.is_hidden ? Eye : EyeOff" class="h-4 w-4" />
                                        <span class="sr-only">{{ post.is_hidden ? 'Unhide' : 'Hide' }}</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="posts.data.length === 0">
                            <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No posts found. Posts will appear here when sources are monitored.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="posts.links" :from="posts.meta?.from" :to="posts.meta?.to" :total="posts.meta?.total" />
        </div>
    </AppLayout>
</template>
