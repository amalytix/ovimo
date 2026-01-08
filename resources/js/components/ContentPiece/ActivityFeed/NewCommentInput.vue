<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Send } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (event: 'submit', comment: string): void;
}>();

const comment = ref('');

const handleSubmit = () => {
    const trimmed = comment.value.trim();
    if (!trimmed || props.disabled) return;

    emit('submit', trimmed);
    comment.value = '';
};

const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        handleSubmit();
    }
};
</script>

<template>
    <div class="flex gap-2">
        <Textarea
            v-model="comment"
            placeholder="Add a comment..."
            class="min-h-[60px] resize-none text-sm"
            :disabled="disabled"
            @keydown="handleKeydown"
        />
        <Button
            variant="ghost"
            size="sm"
            class="shrink-0 self-end"
            :disabled="!comment.trim() || disabled"
            @click="handleSubmit"
        >
            <Send class="h-4 w-4" />
        </Button>
    </div>
</template>
