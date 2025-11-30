<script setup lang="ts">
import { login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    message: string;
}>();
</script>

<template>
    <Head title="Invalid Invitation" />

    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4">
        <div class="w-full max-w-md text-center">
            <div
                class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-red-100"
            >
                <svg
                    class="h-8 w-8 text-red-600"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M6 18L18 6M6 6l12 12"
                    />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Invalid Invitation</h1>
            <p class="mt-4 text-gray-600">{{ message }}</p>
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
