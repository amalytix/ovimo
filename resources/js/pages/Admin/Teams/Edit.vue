<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

interface Team {
    id: number;
    name: string;
    is_active: boolean;
    created_at: string;
}

interface Props {
    team: Team;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Teams', href: '/admin/teams' },
    { title: 'Edit', href: `/admin/teams/${props.team.id}/edit` },
];

const form = useForm({
    name: props.team.name,
    is_active: props.team.is_active,
});

const submit = () => {
    form.put(`/admin/teams/${props.team.id}`);
};
</script>

<template>
    <Head title="Edit Team - Admin" />

    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Edit Team</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Created {{ team.created_at }}
                </p>
            </div>

            <form @submit.prevent="submit" class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" type="text" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-4">
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="is_active"
                            :default-value="form.is_active"
                            @update:model-value="form.is_active = $event"
                        />
                        <Label for="is_active" class="font-normal">Active</Label>
                    </div>
                    <p class="ml-6 -mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Inactive teams cannot access the platform.
                    </p>
                </div>

                <div class="flex justify-end gap-3">
                    <Button variant="outline" as="a" href="/admin/teams">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
