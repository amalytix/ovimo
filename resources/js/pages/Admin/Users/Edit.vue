<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

interface User {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    is_admin: boolean;
    created_at: string;
}

interface Props {
    user: User;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Users', href: '/admin/users' },
    { title: 'Edit', href: `/admin/users/${props.user.id}/edit` },
];

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    is_active: props.user.is_active,
    is_admin: props.user.is_admin,
});

const submit = () => {
    form.put(`/admin/users/${props.user.id}`);
};
</script>

<template>
    <Head title="Edit User - Admin" />

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Edit User</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Member since {{ user.created_at }}
                </p>
            </div>

            <form @submit.prevent="submit" class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" type="text" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-4">
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="is_active"
                            :default-value="form.is_active"
                            @update:model-value="form.is_active = $event"
                        />
                        <Label for="is_active" class="font-normal"
                            >Active</Label
                        >
                    </div>
                    <p
                        class="-mt-2 ml-6 text-xs text-gray-500 dark:text-gray-400"
                    >
                        Inactive users cannot log in to the platform.
                    </p>

                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="is_admin"
                            :default-value="form.is_admin"
                            @update:model-value="form.is_admin = $event"
                        />
                        <Label for="is_admin" class="font-normal"
                            >Administrator</Label
                        >
                    </div>
                    <p
                        class="-mt-2 ml-6 text-xs text-gray-500 dark:text-gray-400"
                    >
                        Administrators have access to the admin panel.
                    </p>
                </div>

                <div class="flex justify-end gap-3">
                    <Button variant="outline" as="a" href="/admin/users"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
