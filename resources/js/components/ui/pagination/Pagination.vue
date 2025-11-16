<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    links: PaginationLink[];
    from?: number;
    to?: number;
    total?: number;
}

const props = defineProps<Props>();

const displayLinks = computed(() => {
    // Filter out first (Previous) and last (Next) for separate handling
    return props.links.slice(1, -1);
});

const prevLink = computed(() => props.links[0]);
const nextLink = computed(() => props.links[props.links.length - 1]);
</script>

<template>
    <div v-if="links.length > 3" class="mt-6 flex items-center justify-between">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            <span v-if="from && to && total"> Showing {{ from }} to {{ to }} of {{ total }} results </span>
        </div>
        <nav class="flex items-center gap-1">
            <Link v-if="prevLink.url" :href="prevLink.url" preserve-scroll preserve-state>
                <Button variant="outline" size="sm">&laquo; Previous</Button>
            </Link>
            <Button v-else variant="outline" size="sm" disabled>&laquo; Previous</Button>

            <template v-for="link in displayLinks" :key="link.label">
                <Link v-if="link.url && !link.active" :href="link.url" preserve-scroll preserve-state>
                    <Button variant="outline" size="sm" v-html="link.label" />
                </Link>
                <Button v-else-if="link.active" size="sm" v-html="link.label" />
                <span v-else class="px-2 text-gray-400" v-html="link.label" />
            </template>

            <Link v-if="nextLink.url" :href="nextLink.url" preserve-scroll preserve-state>
                <Button variant="outline" size="sm">Next &raquo;</Button>
            </Link>
            <Button v-else variant="outline" size="sm" disabled>Next &raquo;</Button>
        </nav>
    </div>
</template>
