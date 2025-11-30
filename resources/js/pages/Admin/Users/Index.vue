<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, UserCheck } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface User {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    is_admin: boolean;
    teams_count: number;
    sources_count: number;
    posts_count: number;
    tokens_7d: number;
    last_login_at: string;
}

interface Props {
    users: {
        data: User[];
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
    { title: 'Users', href: '/admin/users' },
];

const search = ref(props.filters.search);
const status = ref(props.filters.status);

const formatNumber = (num: number) => new Intl.NumberFormat().format(num);

const applyFilters = () => {
    router.get('/admin/users', {
        search: search.value || undefined,
        status: status.value || undefined,
        sort_by: props.filters.sort_by,
        sort_dir: props.filters.sort_dir,
    }, { preserveState: true });
};

const sort = (column: string) => {
    const newDir = props.filters.sort_by === column && props.filters.sort_dir === 'asc' ? 'desc' : 'asc';
    router.get('/admin/users', {
        search: search.value || undefined,
        status: status.value || undefined,
        sort_by: column,
        sort_dir: newDir,
    }, { preserveState: true });
};

let searchTimeout: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});
</script>

<template>
    <Head title="Users - Admin" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Users</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage all users on the platform.</p>
            </div>

            <!-- Filters -->
            <div class="mb-6 flex flex-wrap items-center gap-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name or email..."
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
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                @click="sort('email')"
                            >
                                Email
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                @click="sort('name')"
                            >
                                Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Status
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                @click="sort('last_login_at')"
                            >
                                Last Login
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Teams
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                @click="sort('sources_count')"
                            >
                                Sources
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                @click="sort('posts_count')"
                            >
                                Posts
                            </th>
                            <th
                                class="cursor-pointer px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 hover:text-gray-700 dark:text-gray-400"
                                @click="sort('tokens_7d')"
                            >
                                Tokens (7d)
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        <tr v-for="user in users.data" :key="user.id">
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                {{ user.email }}
                                <span v-if="user.is_admin" class="ml-2 rounded bg-purple-100 px-2 py-0.5 text-xs text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                    Admin
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ user.name }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span
                                    class="inline-flex rounded-full px-2 text-xs font-semibold leading-5"
                                    :class="user.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'"
                                >
                                    {{ user.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ user.last_login_at }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ user.teams_count }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ formatNumber(user.sources_count) }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ formatNumber(user.posts_count) }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-500 dark:text-gray-400">
                                {{ formatNumber(user.tokens_7d) }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                <div class="flex items-center justify-end gap-2">
                                    <Link
                                        :href="`/admin/users/${user.id}/edit`"
                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400"
                                        title="Edit"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                    <a
                                        :href="`/admin/impersonate/${user.id}`"
                                        class="text-gray-600 hover:text-gray-900 dark:text-gray-400"
                                        title="Impersonate"
                                    >
                                        <UserCheck class="h-4 w-4" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="users.links.length > 3" class="mt-4 flex justify-center gap-1">
                <template v-for="link in users.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded px-3 py-1 text-sm"
                        :class="link.active ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-800'"
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="rounded bg-gray-50 px-3 py-1 text-sm text-gray-400 dark:bg-gray-900"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
