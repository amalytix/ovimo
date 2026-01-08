<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { index as fetchActivities, store as storeActivity } from '@/actions/App/Http/Controllers/DerivativeActivityController';
import { MessageCircle, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import ActivityItem from './ActivityItem.vue';
import ActivityComment from './ActivityComment.vue';
import NewCommentInput from './NewCommentInput.vue';

type Activity = {
    id: number;
    event_type: string;
    event_type_label: string;
    level: string;
    description: string;
    created_at: string;
    created_at_human: string;
    user: { id: number; name: string } | null;
    is_comment: boolean;
};

const props = defineProps<{
    contentPieceId: number;
    derivativeId: number;
}>();

const activities = ref<Activity[]>([]);
const loading = ref(false);
const submitting = ref(false);
const error = ref<string | null>(null);

const sortedActivities = computed(() => {
    // Activities come sorted by created_at desc, reverse for chronological display
    return [...activities.value].reverse();
});

const loadActivities = async () => {
    loading.value = true;
    error.value = null;
    try {
        const response = await fetch(fetchActivities.url([props.contentPieceId, props.derivativeId]));
        if (!response.ok) throw new Error('Failed to load activities');
        const data = await response.json();
        activities.value = data.activities;
    } catch (e) {
        error.value = 'Failed to load activities';
        console.error(e);
    } finally {
        loading.value = false;
    }
};

const addComment = async (comment: string) => {
    submitting.value = true;
    try {
        const response = await fetch(storeActivity.url([props.contentPieceId, props.derivativeId]), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.split('; ').find(row => row.startsWith('XSRF-TOKEN='))?.split('=')[1] || ''
                ),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ comment }),
        });

        if (!response.ok) throw new Error('Failed to add comment');
        const data = await response.json();
        activities.value.unshift(data.activity);
    } catch (e) {
        console.error(e);
    } finally {
        submitting.value = false;
    }
};

// Watch for derivative changes and reload
watch(() => props.derivativeId, () => {
    loadActivities();
});

onMounted(() => {
    loadActivities();
});

// Expose methods to parent
defineExpose({
    refresh: loadActivities,
});
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between border-b px-3 py-2">
            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-50">Activity</h4>
            <Button variant="ghost" size="sm" @click="loadActivities" :disabled="loading">
                <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': loading }" />
            </Button>
        </div>

        <!-- Activity List -->
        <div class="flex-1 overflow-y-auto">
            <!-- Loading State -->
            <div v-if="loading && activities.length === 0" class="space-y-3 p-3">
                <div v-for="i in 3" :key="i" class="flex gap-2">
                    <Skeleton class="h-8 w-8 shrink-0 rounded-full" />
                    <div class="flex-1 space-y-2">
                        <Skeleton class="h-4 w-3/4" />
                        <Skeleton class="h-3 w-1/2" />
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="activities.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                <div class="mb-3 rounded-full bg-muted p-3">
                    <MessageCircle class="h-6 w-6 text-muted-foreground" />
                </div>
                <p class="text-sm text-muted-foreground">No activity yet</p>
                <p class="text-xs text-muted-foreground">Comments and events will appear here</p>
            </div>

            <!-- Activity Items -->
            <ul v-else class="space-y-1 p-3">
                <li v-for="activity in sortedActivities" :key="activity.id">
                    <ActivityComment
                        v-if="activity.is_comment"
                        :user="activity.user"
                        :description="activity.description"
                        :created-at="activity.created_at_human"
                    />
                    <ActivityItem
                        v-else
                        :event-type="activity.event_type"
                        :event-type-label="activity.event_type_label"
                        :level="activity.level"
                        :description="activity.description"
                        :created-at="activity.created_at_human"
                        :user="activity.user"
                    />
                </li>
            </ul>
        </div>

        <!-- New Comment Input -->
        <div class="border-t p-3">
            <NewCommentInput
                :disabled="submitting"
                @submit="addComment"
            />
        </div>
    </div>
</template>
