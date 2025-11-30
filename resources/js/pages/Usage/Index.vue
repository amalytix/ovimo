<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

interface Props {
    totalStats: {
        total_input: number;
        total_output: number;
        total_tokens: number;
        total_requests: number;
    };
    byOperation: Array<{
        operation: string;
        tokens: number;
        requests: number;
    }>;
    byModel: Array<{
        model: string;
        tokens: number;
        requests: number;
    }>;
    dailyUsage: Array<{
        date: string;
        tokens: number;
        requests: number;
    }>;
    recentLogs: Array<{
        id: number;
        user_name: string;
        operation: string;
        model: string;
        input_tokens: number;
        output_tokens: number;
        total_tokens: number;
        created_at: string;
    }>;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Usage', href: '/usage' }];

const formatNumber = (num: number) => {
    return new Intl.NumberFormat().format(num);
};

const formatOperation = (operation: string) => {
    const map: Record<string, string> = {
        post_summarization: 'Post Summarization',
        content_generation: 'Content Generation',
    };
    return map[operation] || operation;
};
</script>

<template>
    <Head title="Token Usage" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Token Usage</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Monitor your OpenAI API token consumption.
                </p>
            </div>

            <!-- Summary Cards -->
            <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-4">
                <div
                    class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div
                        class="text-sm font-medium text-gray-500 dark:text-gray-400"
                    >
                        Total Tokens
                    </div>
                    <div class="mt-2 text-3xl font-bold">
                        {{ formatNumber(totalStats.total_tokens) }}
                    </div>
                </div>
                <div
                    class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div
                        class="text-sm font-medium text-gray-500 dark:text-gray-400"
                    >
                        Total Requests
                    </div>
                    <div class="mt-2 text-3xl font-bold">
                        {{ formatNumber(totalStats.total_requests) }}
                    </div>
                </div>
                <div
                    class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div
                        class="text-sm font-medium text-gray-500 dark:text-gray-400"
                    >
                        Input Tokens
                    </div>
                    <div class="mt-2 text-3xl font-bold">
                        {{ formatNumber(totalStats.total_input) }}
                    </div>
                </div>
                <div
                    class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div
                        class="text-sm font-medium text-gray-500 dark:text-gray-400"
                    >
                        Output Tokens
                    </div>
                    <div class="mt-2 text-3xl font-bold">
                        {{ formatNumber(totalStats.total_output) }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <!-- Usage by Operation -->
                <div
                    class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                >
                    <h2 class="mb-4 text-lg font-semibold">
                        Usage by Operation
                    </h2>
                    <div v-if="byOperation.length > 0" class="space-y-4">
                        <div
                            v-for="item in byOperation"
                            :key="item.operation"
                            class="flex items-center justify-between"
                        >
                            <div>
                                <div class="font-medium">
                                    {{ formatOperation(item.operation) }}
                                </div>
                                <div
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ formatNumber(item.requests) }} requests
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold">
                                    {{ formatNumber(item.tokens) }}
                                </div>
                                <div
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    tokens
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="text-sm text-gray-500 dark:text-gray-400"
                    >
                        No usage data yet.
                    </div>
                </div>

                <!-- Usage by Model -->
                <div
                    class="rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                >
                    <h2 class="mb-4 text-lg font-semibold">Usage by Model</h2>
                    <div v-if="byModel.length > 0" class="space-y-4">
                        <div
                            v-for="item in byModel"
                            :key="item.model"
                            class="flex items-center justify-between"
                        >
                            <div>
                                <div class="font-medium">{{ item.model }}</div>
                                <div
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ formatNumber(item.requests) }} requests
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold">
                                    {{ formatNumber(item.tokens) }}
                                </div>
                                <div
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    tokens
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="text-sm text-gray-500 dark:text-gray-400"
                    >
                        No usage data yet.
                    </div>
                </div>
            </div>

            <!-- Daily Usage Chart -->
            <div
                class="mt-8 rounded-lg border bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
            >
                <h2 class="mb-4 text-lg font-semibold">
                    Daily Usage (Last 30 Days)
                </h2>
                <div v-if="dailyUsage.length > 0" class="overflow-x-auto">
                    <div
                        class="flex min-w-full items-end gap-1"
                        style="height: 200px"
                    >
                        <div
                            v-for="day in dailyUsage"
                            :key="day.date"
                            class="relative flex flex-1 flex-col items-center"
                            :title="`${day.date}: ${formatNumber(day.tokens)} tokens`"
                        >
                            <div
                                class="w-full rounded-t bg-blue-500 transition-all hover:bg-blue-600"
                                :style="{
                                    height: `${Math.max(4, (day.tokens / Math.max(...dailyUsage.map((d) => d.tokens))) * 180)}px`,
                                }"
                            ></div>
                        </div>
                    </div>
                    <div
                        class="mt-2 flex justify-between text-xs text-gray-500"
                    >
                        <span>{{ dailyUsage[0]?.date }}</span>
                        <span>{{
                            dailyUsage[dailyUsage.length - 1]?.date
                        }}</span>
                    </div>
                </div>
                <div v-else class="text-sm text-gray-500 dark:text-gray-400">
                    No daily usage data yet.
                </div>
            </div>

            <!-- Recent Logs -->
            <div class="mt-8">
                <h2 class="mb-4 text-lg font-semibold">Recent Activity</h2>
                <div
                    class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                    >
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    User
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Operation
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Model
                                </th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Input
                                </th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Output
                                </th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Total
                                </th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                >
                                    When
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900"
                        >
                            <tr v-for="log in recentLogs" :key="log.id">
                                <td
                                    class="px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-900 dark:text-white"
                                >
                                    {{ log.user_name }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                                >
                                    {{ formatOperation(log.operation) }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                                >
                                    {{ log.model }}
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                                >
                                    {{ formatNumber(log.input_tokens) }}
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                                >
                                    {{ formatNumber(log.output_tokens) }}
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap text-gray-900 dark:text-white"
                                >
                                    {{ formatNumber(log.total_tokens) }}
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                                >
                                    {{ log.created_at }}
                                </td>
                            </tr>
                            <tr v-if="recentLogs.length === 0">
                                <td
                                    colspan="7"
                                    class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                                >
                                    No usage logs yet. Generate some content to
                                    see your token usage.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
