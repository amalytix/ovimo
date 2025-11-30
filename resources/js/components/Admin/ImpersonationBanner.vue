<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { usePage, router } from '@inertiajs/vue3';
import { UserX } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();

const impersonating = computed(() => page.props.impersonating as { isImpersonating: boolean; impersonatedUser: string } | null);

const stopImpersonating = () => {
    router.post('/admin/impersonate/stop');
};
</script>

<template>
    <div
        v-if="impersonating?.isImpersonating"
        class="fixed top-0 left-0 right-0 z-50 flex items-center justify-center gap-4 bg-amber-500 px-4 py-2 text-sm font-medium text-white shadow-lg"
    >
        <span>
            You are impersonating <strong>{{ impersonating.impersonatedUser }}</strong>
        </span>
        <Button variant="secondary" size="sm" @click="stopImpersonating" class="h-7">
            <UserX class="mr-1 h-3 w-3" />
            Exit Impersonation
        </Button>
    </div>
</template>
