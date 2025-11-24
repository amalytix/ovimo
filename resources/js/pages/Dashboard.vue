<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

interface Props {
    stats: {
        sources: {
            total: number;
            active: number;
        };
        posts: {
            total: number;
            today: number;
            this_week: number;
            create_content: number;
            avg_relevancy: number | null;
        };
        tokens: {
            today: number;
            last_7_days: number;
            last_30_days: number;
        };
    };
    content_pieces_today: {
        id: number;
        internal_name: string;
        channel: string;
        published_at: string | null;
        status: string;
        published_platforms: Record<string, unknown> | null;
    }[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const formatNumber = (num: number): string => {
    return num.toLocaleString();
};

const todayLabel = new Date().toLocaleDateString();

const formatTime = (value: string | null): string => {
    if (!value) {
        return 'Any time';
    }

    return new Date(value).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Stats Grid -->
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <!-- Sources Card -->
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Sources Monitored</h3>
                        <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <div class="mt-4">
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ formatNumber(stats.sources.total) }}</p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            <span class="text-green-600 dark:text-green-400">{{ stats.sources.active }} active</span>
                            <span v-if="stats.sources.total - stats.sources.active > 0" class="ml-2 text-gray-500">
                                {{ stats.sources.total - stats.sources.active }} inactive
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Posts Last 7 Days Card -->
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Posts Found Last 7 Days</h3>
                        <svg class="h-5 w-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div class="mt-4">
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ formatNumber(stats.posts.this_week) }}</p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            <span class="inline-flex items-center gap-2 rounded-full bg-purple-100 px-2 py-1 text-xs font-medium text-purple-800 dark:bg-purple-900/50 dark:text-purple-200">
                                {{ formatNumber(stats.posts.today) }} today
                            </span>
                        </p>
                    </div>
                </div>
                <!-- Token Usage Card -->
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Token Usage</h3>
                        <svg class="h-5 w-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                        </svg>
                    </div>
                    <div class="mt-4 space-y-3">
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Today</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ formatNumber(stats.tokens.today) }}</span>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Last 7 days</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ formatNumber(stats.tokens.last_7_days) }}</span>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Last 30 days</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ formatNumber(stats.tokens.last_30_days) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Second Row -->
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            </div>

            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900 md:col-span-2 lg:col-span-1">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Content to be published today</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Scheduled for {{ todayLabel }}</p>
                        </div>
                        <svg class="h-5 w-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>

                    <div v-if="content_pieces_today.length === 0" class="mt-4 rounded-lg border border-dashed border-gray-200 p-4 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-300">
                        No content is scheduled to publish today.
                    </div>

                    <div v-else class="mt-4 divide-y divide-gray-100 dark:divide-gray-800">
                        <div
                            v-for="piece in content_pieces_today"
                            :key="piece.id"
                            class="flex items-center justify-between py-3"
                        >
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ piece.internal_name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ piece.channel }} · {{ formatTime(piece.published_at) }}
                                </p>
                            </div>
                            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                                {{ piece.published_platforms ? 'Published' : 'Scheduled' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
