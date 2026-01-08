<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Pencil } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const props = defineProps<{
    form: Record<string, any>;
    contentPieceTitle?: string | null;
}>();

const emit = defineEmits<{
    save: [];
}>();

const placeholderTitle = 'New Content Piece';

const titleRef = ref<HTMLElement | null>(null);

const handleTitleInput = () => {
    const value = titleRef.value?.textContent ?? '';
    // eslint-disable-next-line vue/no-mutating-props
    props.form.internal_name = value;
};

const handleTitleFocus = () => {
    if (!titleRef.value) return;

    // Move cursor to the end of the content
    const range = document.createRange();
    const selection = window.getSelection();

    if (titleRef.value.childNodes.length > 0) {
        range.selectNodeContents(titleRef.value);
        range.collapse(false);
        selection?.removeAllRanges();
        selection?.addRange(range);
    }
};

const handleTitleBlur = () => {
    emit('save');
};

const initializeTitle = () => {
    if (!titleRef.value) return;

    const initialValue =
        props.contentPieceTitle || props.form.internal_name || '';
    titleRef.value.textContent = initialValue;
};

onMounted(() => {
    initializeTitle();
});
</script>

<template>
    <div class="rounded-xl border bg-card p-4 shadow-sm">
        <div class="space-y-1">
            <p class="text-xs tracking-wide text-muted-foreground uppercase">
                Content piece
            </p>
            <div class="flex items-center gap-2">
                <div
                    ref="titleRef"
                    class="min-w-[200px] rounded-md px-2 py-1 text-2xl leading-tight font-semibold text-foreground hover:bg-muted focus-visible:outline-none"
                    contenteditable="true"
                    :data-placeholder="placeholderTitle"
                    dir="ltr"
                    style="unicode-bidi: plaintext"
                    role="textbox"
                    aria-label="Content piece title"
                    @input="handleTitleInput"
                    @focus="handleTitleFocus"
                    @blur="handleTitleBlur"
                    @keydown.enter.prevent
                />
                <Pencil class="h-4 w-4 text-muted-foreground" />
            </div>
            <InputError :message="props.form.errors?.internal_name" />
            <p class="text-sm text-muted-foreground">
                Keep the basics aligned while you switch between tabs.
            </p>
        </div>
    </div>
</template>

<style scoped>
:deep([data-placeholder]:empty)::before {
    content: attr(data-placeholder);
    color: rgb(148 163 184);
}
</style>
