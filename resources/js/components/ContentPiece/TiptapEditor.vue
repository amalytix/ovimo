<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import StarterKit from '@tiptap/starter-kit';
import { Bold, Code, Heading1, Heading2, Heading3, Image as ImageIcon, Italic, Link2, ListOrdered, List, Quote, Strikethrough, WrapText, Type } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
}>();

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void;
    (event: 'request-image'): void;
}>();

const isMarkdownMode = ref(false);
const markdownValue = ref(props.modelValue ?? '');

const editor = useEditor({
    content: props.modelValue || '',
    extensions: [
        StarterKit.configure({
            codeBlock: true,
        }),
        Link.configure({
            openOnClick: false,
            autolink: true,
            linkOnPaste: true,
        }),
        Image,
        Placeholder.configure({
            placeholder: props.placeholder || 'Start writing...',
        }),
    ],
    onUpdate: ({ editor }) => {
        if (isMarkdownMode.value) {
            return;
        }
        emit('update:modelValue', editor.getHTML());
    },
});

const isActive = (name: string, attrs: Record<string, unknown> = {}) => {
    return editor?.value?.isActive(name, attrs) ?? false;
};

const toggleMarkdownMode = () => {
    isMarkdownMode.value = !isMarkdownMode.value;
    if (isMarkdownMode.value) {
        markdownValue.value = editor?.value?.getText() ?? '';
    } else {
        editor?.value?.commands.setContent(markdownValue.value || '', false);
        emit('update:modelValue', editor?.value?.getHTML() ?? '');
    }
};

const toolbarButtonClass = computed(
    () =>
        'h-9 w-9 rounded-md border border-transparent text-sm transition hover:border-border hover:bg-muted aria-pressed:bg-primary aria-pressed:text-primary-foreground'
);

const handleLink = () => {
    const previousUrl = editor?.value?.getAttributes('link').href;
    const url = window.prompt('Enter URL', previousUrl);
    if (url === null) {
        return;
    }
    if (url === '') {
        editor?.value?.chain().focus().unsetLink().run();
        return;
    }
    editor?.value?.chain().focus().setLink({ href: url }).run();
};

const insertImage = (src: string) => {
    editor?.value?.chain().focus().setImage({ src }).run();
};

defineExpose({
    insertImage,
});

watch(
    () => props.modelValue,
    (value) => {
        if (!editor?.value) {
            return;
        }

        if (isMarkdownMode.value) {
            markdownValue.value = value || '';
            return;
        }

        const current = editor.value.getHTML();
        if ((value || '') !== current) {
            editor.value.commands.setContent(value || '', false);
        }
    }
);

onBeforeUnmount(() => {
    editor?.value?.destroy();
});
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center gap-2 rounded-md border bg-card p-2">
            <Button
                variant="ghost"
                size="icon"
                :class="toolbarButtonClass"
                :aria-pressed="isActive('heading', { level: 1 })"
                aria-label="Heading 1"
                @click="editor?.chain().focus().toggleHeading({ level: 1 }).run()"
            >
                <Heading1 class="h-4 w-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                :class="toolbarButtonClass"
                :aria-pressed="isActive('heading', { level: 2 })"
                aria-label="Heading 2"
                @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
            >
                <Heading2 class="h-4 w-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                :class="toolbarButtonClass"
                :aria-pressed="isActive('heading', { level: 3 })"
                aria-label="Heading 3"
                @click="editor?.chain().focus().toggleHeading({ level: 3 }).run()"
            >
                <Heading3 class="h-4 w-4" />
            </Button>
            <span class="h-6 w-px bg-border" />
            <Button
                variant="ghost"
                size="icon"
                :class="toolbarButtonClass"
                :aria-pressed="isActive('bold')"
                aria-label="Bold"
                @click="editor?.chain().focus().toggleBold().run()"
            >
                <Bold class="h-4 w-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                :class="toolbarButtonClass"
                :aria-pressed="isActive('italic')"
                aria-label="Italic"
                @click="editor?.chain().focus().toggleItalic().run()"
            >
                <Italic class="h-4 w-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                :class="toolbarButtonClass"
                :aria-pressed="isActive('strike')"
                aria-label="Strikethrough"
                @click="editor?.chain().focus().toggleStrike().run()"
            >
                <Strikethrough class="h-4 w-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                :class="toolbarButtonClass"
                :aria-pressed="isActive('code')"
                aria-label="Inline code"
                @click="editor?.chain().focus().toggleCode().run()"
            >
                <Code class="h-4 w-4" />
            </Button>
            <span class="h-6 w-px bg-border" />
            <Button
                variant="ghost"
                size="icon"
                :class="toolbarButtonClass"
                :aria-pressed="isActive('bulletList')"
                aria-label="Bullet list"
                @click="editor?.chain().focus().toggleBulletList().run()"
            >
                <List class="h-4 w-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                :class="toolbarButtonClass"
                :aria-pressed="isActive('orderedList')"
                aria-label="Ordered list"
                @click="editor?.chain().focus().toggleOrderedList().run()"
            >
                <ListOrdered class="h-4 w-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                :class="toolbarButtonClass"
                :aria-pressed="isActive('blockquote')"
                aria-label="Blockquote"
                @click="editor?.chain().focus().toggleBlockquote().run()"
            >
                <Quote class="h-4 w-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                :class="toolbarButtonClass"
                :aria-pressed="isActive('codeBlock')"
                aria-label="Code block"
                @click="editor?.chain().focus().toggleCodeBlock().run()"
            >
                <WrapText class="h-4 w-4" />
            </Button>
            <span class="h-6 w-px bg-border" />
            <Button variant="ghost" size="icon" :class="toolbarButtonClass" aria-label="Insert link" @click="handleLink">
                <Link2 class="h-4 w-4" />
            </Button>
            <Button variant="ghost" size="icon" :class="toolbarButtonClass" aria-label="Insert image" @click="emit('request-image')">
                <ImageIcon class="h-4 w-4" />
            </Button>
            <span class="h-6 w-px bg-border" />
            <Button variant="outline" size="sm" class="ml-auto h-9 gap-2" @click="toggleMarkdownMode">
                <Type class="h-4 w-4" />
                <span>{{ isMarkdownMode ? 'Back to editor' : 'Markdown mode' }}</span>
            </Button>
        </div>

        <div class="rounded-lg border bg-card">
            <div v-if="isMarkdownMode" class="p-3">
                <textarea
                    v-model="markdownValue"
                    class="min-h-[320px] w-full rounded-md border border-input bg-background p-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    placeholder="Write in markdown..."
                />
            </div>
            <EditorContent v-else :editor="editor" class="prose prose-slate max-w-none min-h-[320px] p-4 dark:prose-invert" />
        </div>
    </div>
</template>
