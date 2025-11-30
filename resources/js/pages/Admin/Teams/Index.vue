<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Team {
    id: number;
    name: string;
    is_active: boolean;
    users_count: number;
    sources_count: number;
    posts_count: number;
    tokens_7d: number;
    created_at: string;
}

interface Props {
    teams: {
        data: Team[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search: string;
        status: string;
        sort_by: string;
        sort_dir: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Teams', href: '/admin/teams' },
];

const search = ref(props.filters.search);
const status = ref(props.filters.status);

const formatNumber = (num: number) => new Intl.NumberFormat().format(num);

const applyFilters = () => {
    router.get(
        '/admin/teams',
        {
            search: search.value || undefined,
            status: status.value || undefined,
            sort_by: props.filters.sort_by,
            sort_dir: props.filters.sort_dir,
        },
        { preserveState: true },
    );
};

const sort = (column: string) => {
    const newDir =
        props.filters.sort_by === column && props.filters.sort_dir === 'asc'
            ? 'desc'
            : 'asc';
    router.get(
        '/admin/teams',
        {
            search: search.value || undefined,
            status: status.value || undefined,
            sort_by: column,
            sort_dir: newDir,
        },
        { preserveState: true },
    );
};

let searchTimeout: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});
</script>

<template>
    <Head title="Teams - Admin" />

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Teams</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Manage all teams on the platform.
                </p>
            </div>

            <!-- Filters -->
            <div class="mb-6 flex flex-wrap items-center gap-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name..."
                    class="rounded-lg border px-4 py-2 dark:border-gray-700 dark:bg-gray-800"
                />
                <select
                    v-model="status"
                    @change="applyFilters"
                    class="rounded-lg border px-4 py-2 dark:border-gray-700 dark:bg-gray-800"
                >
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-lg border dark:border-gray-700">
                <table
                    class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                >
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase hover:text-gray-700 dark:text-gray-400"
                                @click="sort('name')"
                            >
                                Name
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >
                                Status
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-center text-xs font-medium tracking-wider text-gray-500 uppercase hover:text-gray-700 dark:text-gray-400"
                                @click="sort('users_count')"
                            >
                                Users
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-center text-xs font-medium tracking-wider text-gray-500 uppercase hover:text-gray-700 dark:text-gray-400"
                                @click="sort('sources_count')"
                            >
                                Sources
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-center text-xs font-medium tracking-wider text-gray-500 uppercase hover:text-gray-700 dark:text-gray-400"
                                @click="sort('posts_count')"
                            >
                                Posts
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase hover:text-gray-700 dark:text-gray-400"
                                @click="sort('tokens_7d')"
                            >
                                Tokens (7d)
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase hover:text-gray-700 dark:text-gray-400"
                                @click="sort('created_at')"
                            >
                                Created
                            </th>
                            <th
                                class="px-6 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900"
                    >
                        <tr v-for="team in teams.data" :key="team.id">
                            <td
                                class="px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-900 dark:text-white"
                            >
                                {{ team.name }}
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap">
                                <span
                                    class="inline-flex rounded-full px-2 text-xs leading-5 font-semibold"
                                    :class="
                                        team.is_active
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                    "
                                >
                                    {{ team.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td
                                class="px-6 py-4 text-center text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                            >
                                {{ team.users_count }}
                            </td>
                            <td
                                class="px-6 py-4 text-center text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                            >
                                {{ formatNumber(team.sources_count) }}
                            </td>
                            <td
                                class="px-6 py-4 text-center text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                            >
                                {{ formatNumber(team.posts_count) }}
                            </td>
                            <td
                                class="px-6 py-4 text-right text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                            >
                                {{ formatNumber(team.tokens_7d) }}
                            </td>
                            <td
                                class="px-6 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                            >
                                {{ team.created_at }}
                            </td>
                            <td
                                class="px-6 py-4 text-right text-sm whitespace-nowrap"
                            >
                                <Link
                                    :href="`/admin/teams/${team.id}/edit`"
                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400"
                                    title="Edit"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="teams.links.length > 3"
                class="mt-4 flex justify-center gap-1"
            >
                <template v-for="link in teams.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded px-3 py-1 text-sm"
                        :class="
                            link.active
                                ? 'bg-blue-500 text-white'
                                : 'bg-gray-100 dark:bg-gray-800'
                        "
                    >
                        <!-- eslint-disable-next-line vue/no-v-html -->
                        <span v-html="link.label" />
                    </Link>
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <span
                        v-else
                        class="rounded bg-gray-50 px-3 py-1 text-sm text-gray-400 dark:bg-gray-900"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AdminLayout>
</template>
