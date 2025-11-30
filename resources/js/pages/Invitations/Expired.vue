<script setup lang="ts">
import { login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    teamName: string;
}>();
</script>

<template>
    <Head title="Invitation Expired" />

    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4">
        <div class="w-full max-w-md text-center">
            <div
                class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-yellow-100"
            >
                <svg
                    class="h-8 w-8 text-yellow-600"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Invitation Expired</h1>
            <p class="mt-4 text-gray-600">
                The invitation to join <strong>{{ teamName }}</strong> has
                expired. Invitations are valid for 48 hours.
            </p>
            <p class="mt-2 text-gray-600">
                Please ask the team owner to send you a new invitation.
            </p>
            <div class="mt-8 flex justify-center gap-4">
                <Link
                    :href="login()"
                    class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
                >
                    Log in
                </Link>
                <Link
                    :href="register()"
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Register
                </Link>
            </div>
        </div>
    </div>
</template>
