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
        content_pieces: {
            total: number;
            this_month: number;
        };
    };
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
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Stats Grid -->
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
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

                <!-- Total Posts Card -->
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Posts Found</h3>
                        <svg class="h-5 w-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div class="mt-4">
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ formatNumber(stats.posts.total) }}</p>
                    </div>
                </div>

                <!-- Posts Today Card -->
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Posts Found Today</h3>
                        <svg class="h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="mt-4">
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ formatNumber(stats.posts.today) }}</p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ formatNumber(stats.posts.this_week) }} this week
                        </p>
                    </div>
                </div>

                <!-- Create Content Posts Card -->
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Create Content</h3>
                        <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </div>
                    <div class="mt-4">
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ formatNumber(stats.posts.create_content) }}</p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">posts marked for content</p>
                    </div>
                </div>
            </div>

            <!-- Second Row -->
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
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

                <!-- Content Pieces Card -->
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Content Pieces</h3>
                        <svg class="h-5 w-5 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                        </svg>
                    </div>
                    <div class="mt-4">
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ formatNumber(stats.content_pieces.total) }}</p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ stats.content_pieces.this_month }} created this month
                        </p>
                    </div>
                </div>

                <!-- Average Relevancy Card -->
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Average Relevancy</h3>
                        <svg class="h-5 w-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                    </div>
                    <div class="mt-4">
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">
                            <span v-if="stats.posts.avg_relevancy !== null">{{ stats.posts.avg_relevancy }}%</span>
                            <span v-else class="text-gray-400">-</span>
                        </p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">across all scored posts</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
