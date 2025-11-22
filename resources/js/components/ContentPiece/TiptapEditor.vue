<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import StarterKit from '@tiptap/starter-kit';
import { Markdown } from '@tiptap/markdown';
import Typography from '@tiptap/extension-typography';
import { Bold, Code, Heading1, Heading2, Heading3, Image as ImageIcon, Italic, Link2, ListOrdered, List, Quote, Strikethrough, WrapText, Type } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
    contentType?: 'html' | 'markdown';
}>();

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void;
    (event: 'request-image'): void;
    (event: 'content-type-change', value: 'html' | 'markdown'): void;
}>();

const isMarkdownMode = ref(false);
const markdownValue = ref(props.modelValue ?? '');

const editor = useEditor({
    content: props.modelValue || '',
    contentType: props.contentType === 'markdown' ? 'markdown' : 'html',
    extensions: [
        StarterKit.configure({
            codeBlock: true,
        }),
        Typography,
        Link.configure({
            openOnClick: false,
            autolink: true,
            linkOnPaste: true,
        }),
        Image,
        Placeholder.configure({
            placeholder: props.placeholder || 'Start writing...',
        }),
        Markdown.configure({
            html: true,
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
        markdownValue.value = editor?.value?.getMarkdown?.() ?? editor?.value?.getText() ?? '';
    } else {
        editor?.value?.commands.setContent(markdownValue.value || '', {
            contentType: 'markdown',
        });
        emit('update:modelValue', editor?.value?.getHTML() ?? '');
        emit('content-type-change', 'html');
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
    () => [props.modelValue, props.contentType],
    ([value, type]) => {
        if (!editor?.value) {
            return;
        }

        if (isMarkdownMode.value) {
            markdownValue.value = value || '';
            return;
        }

        const current = editor.value.getHTML();
        if ((value || '') !== current) {
            if (type === 'markdown') {
                editor.value.commands.setContent(value || '', {
                    contentType: 'markdown',
                });
                emit('content-type-change', 'html');
            } else {
                editor.value.commands.setContent(value || '', false);
            }
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
            <EditorContent v-else :editor="editor" class="tiptap prose prose-slate max-w-none min-h-[320px] p-4 dark:prose-invert" />
        </div>
    </div>
</template>

<style scoped>
:deep(.tiptap) {
    min-height: 320px;
}

:deep(.tiptap :first-child) {
    margin-top: 0;
}

:deep(.tiptap h1) {
    font-size: 1.5rem;
    line-height: 1.2;
    margin: 1.5rem 0 0.75rem;
    font-weight: 700;
}

:deep(.tiptap h2) {
    font-size: 1.25rem;
    line-height: 1.2;
    margin: 1.25rem 0 0.6rem;
    font-weight: 700;
}

:deep(.tiptap h3) {
    font-size: 1.1rem;
    line-height: 1.2;
    margin: 1rem 0 0.5rem;
    font-weight: 600;
}

:deep(.tiptap p) {
    margin: 0.5rem 0;
    line-height: 1.6;
}

:deep(.tiptap ul),
:deep(.tiptap ol) {
    padding-left: 1.25rem;
    margin: 0.75rem 0;
    list-style-position: outside;
}

:deep(.tiptap ul) {
    list-style-type: disc;
}

:deep(.tiptap ol) {
    list-style-type: decimal;
}

:deep(.tiptap li p) {
    margin: 0.25rem 0;
}

:deep(.tiptap blockquote) {
    border-left: 3px solid rgb(226 232 240);
    padding-left: 1rem;
    margin: 1rem 0;
    color: rgb(71 85 105);
}

:deep(.tiptap pre) {
    background: rgb(15 23 42);
    color: white;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    overflow-x: auto;
}

:deep(.tiptap code) {
    background: rgb(241 245 249);
    padding: 0.2rem 0.4rem;
    border-radius: 0.3rem;
}

:deep(.tiptap a) {
    color: rgb(37 99 235);
    text-decoration: underline;
    text-decoration-thickness: 1.5px;
}

:deep(.tiptap:focus-visible) {
    outline: none;
    box-shadow: none;
}
</style>
