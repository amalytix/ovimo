<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface User {
    id: number;
    name: string;
}

interface Source {
    id: number;
    internal_name: string;
}

interface Post {
    id: number;
    external_title: string;
}

interface ActivityLog {
    id: number;
    event_type: string;
    event_type_label: string;
    level: string;
    description: string | null;
    created_at: string;
    created_at_human: string;
    user: User | null;
    source: Source | null;
    post: Post | null;
    ip_address: string | null;
    user_agent: string | null;
    metadata: Record<string, any> | null;
}

interface Filters {
    event_type?: string;
    from?: string;
    to?: string;
}

interface Props {
    logs: {
        data: ActivityLog[];
        links: object;
        meta: object;
    };
    filters?: Filters;
    eventTypes: Record<string, string>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Activity Logs', href: '/activity-logs' }];

const selectedEventType = ref<string>(props.filters?.event_type || '');
const fromDate = ref<string>(props.filters?.from || '');
const toDate = ref<string>(props.filters?.to || '');

const applyFilters = () => {
    router.get(
        '/activity-logs',
        {
            event_type: selectedEventType.value || undefined,
            from: fromDate.value || undefined,
            to: toDate.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const clearFilters = () => {
    selectedEventType.value = '';
    fromDate.value = '';
    toDate.value = '';
    router.get('/activity-logs', {}, { preserveState: true, preserveScroll: true });
};

const getLevelVariant = (level: string) => {
    const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        info: 'default',
        warning: 'secondary',
        error: 'destructive',
    };
    return variants[level] || 'default';
};

const formatMetadata = (metadata: Record<string, any> | null) => {
    if (!metadata) return 'No metadata';
    return JSON.stringify(metadata, null, 2);
};
</script>

<template>
    <Head title="Activity Logs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Activity Logs</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    View and filter your team's activity history.
                </p>
            </div>

            <!-- Filters -->
            <div class="mb-6 rounded-lg border bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <Label for="event_type" class="mb-2">Event Type</Label>
                        <select
                            id="event_type"
                            v-model="selectedEventType"
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900"
                        >
                            <option value="">All Events</option>
                            <option v-for="(label, type) in eventTypes" :key="type" :value="type">
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <Label for="from" class="mb-2">From Date</Label>
                        <Input id="from" v-model="fromDate" type="date" />
                    </div>
                    <div>
                        <Label for="to" class="mb-2">To Date</Label>
                        <Input id="to" v-model="toDate" type="date" />
                    </div>
                    <div class="flex items-end gap-2">
                        <Button @click="applyFilters" class="w-full md:w-auto">Apply</Button>
                        <Button @click="clearFilters" variant="outline" class="w-full md:w-auto">
                            Clear
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                            >
                                Event
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                            >
                                Level
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                            >
                                Description
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                            >
                                User
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                            >
                                When
                            </th>
                            <th
                                class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        <tr v-for="log in logs.data" :key="log.id">
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                {{ log.event_type_label }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <Badge :variant="getLevelVariant(log.level)">
                                    {{ log.level }}
                                </Badge>
                            </td>
                            <td class="max-w-md px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ log.description || '-' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ log.user?.name || '-' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <span :title="log.created_at">{{ log.created_at_human }}</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                <Dialog>
                                    <DialogTrigger as-child>
                                        <Button variant="outline" size="sm">View Details</Button>
                                    </DialogTrigger>
                                    <DialogContent class="max-w-2xl">
                                        <DialogHeader>
                                            <DialogTitle>Activity Log Details</DialogTitle>
                                            <DialogDescription>
                                                Full details for {{ log.event_type_label }}
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div class="space-y-4">
                                            <div>
                                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                    Event Type
                                                </div>
                                                <div class="mt-1 text-sm">{{ log.event_type_label }}</div>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                    Level
                                                </div>
                                                <div class="mt-1">
                                                    <Badge :variant="getLevelVariant(log.level)">
                                                        {{ log.level }}
                                                    </Badge>
                                                </div>
                                            </div>
                                            <div v-if="log.description">
                                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                    Description
                                                </div>
                                                <div class="mt-1 text-sm">{{ log.description }}</div>
                                            </div>
                                            <div v-if="log.user">
                                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                    User
                                                </div>
                                                <div class="mt-1 text-sm">{{ log.user.name }}</div>
                                            </div>
                                            <div v-if="log.source">
                                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                    Source
                                                </div>
                                                <div class="mt-1 text-sm">{{ log.source.internal_name }}</div>
                                            </div>
                                            <div v-if="log.post">
                                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                    Post
                                                </div>
                                                <div class="mt-1 text-sm">{{ log.post.external_title }}</div>
                                            </div>
                                            <div v-if="log.ip_address">
                                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                    IP Address
                                                </div>
                                                <div class="mt-1 text-sm font-mono">{{ log.ip_address }}</div>
                                            </div>
                                            <div v-if="log.user_agent">
                                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                    User Agent
                                                </div>
                                                <div class="mt-1 text-sm font-mono">{{ log.user_agent }}</div>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                    Timestamp
                                                </div>
                                                <div class="mt-1 text-sm">{{ log.created_at }}</div>
                                            </div>
                                            <div v-if="log.metadata">
                                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                    Metadata
                                                </div>
                                                <pre
                                                    class="mt-2 overflow-auto rounded-md bg-gray-100 p-4 text-xs dark:bg-gray-800"
                                                    >{{ formatMetadata(log.metadata) }}</pre>
                                            </div>
                                        </div>
                                    </DialogContent>
                                </Dialog>
                            </td>
                        </tr>
                        <tr v-if="logs.data.length === 0">
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No activity logs found for the selected filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="logs.data.length > 0" class="mt-6">
                <Pagination :links="logs.links" :meta="logs.meta" />
            </div>
        </div>
    </AppLayout>
</template>
