<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Typography from '@tiptap/extension-typography';
import { Markdown } from '@tiptap/markdown';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import {
    Bold,
    Code,
    Heading1,
    Heading2,
    Heading3,
    Image as ImageIcon,
    Italic,
    Link2,
    List,
    ListOrdered,
    Quote,
    Strikethrough,
    Type,
    WrapText,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { ResizableImage } from './ResizableImage';

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
    contentType?: 'html' | 'markdown';
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void;
    (event: 'request-image'): void;
    (event: 'content-type-change', value: 'html' | 'markdown'): void;
}>();

const isMarkdownMode = ref(false);
const markdownValue = ref(props.modelValue ?? '');
const isEmittingFromMarkdown = ref(false);

const editor = useEditor({
    content: props.modelValue || '',
    contentType: props.contentType === 'markdown' ? 'markdown' : 'html',
    editable: !props.disabled,
    extensions: [
        StarterKit.configure({
            codeBlock: true,
            link: false,
        }),
        Typography,
        Link.configure({
            openOnClick: false,
            autolink: true,
            linkOnPaste: true,
        }),
        ResizableImage.configure({
            inline: false,
            allowBase64: false,
            HTMLAttributes: {
                class: 'tiptap-image',
            },
        }),
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
    onCreate({ editor }) {
        if (props.contentType === 'markdown') {
            const html = editor.getHTML();
            emit('update:modelValue', html);
            emit('content-type-change', 'html');
        }
    },
});

const isActive = (name: string, attrs: Record<string, unknown> = {}) => {
    return editor?.value?.isActive(name, attrs) ?? false;
};

const toggleMarkdownMode = () => {
    isMarkdownMode.value = !isMarkdownMode.value;
    if (isMarkdownMode.value) {
        markdownValue.value =
            editor?.value?.getMarkdown?.() ?? editor?.value?.getText() ?? '';
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
        'h-9 w-9 rounded-md border border-transparent text-sm transition hover:border-border hover:bg-muted aria-pressed:bg-primary aria-pressed:text-primary-foreground',
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
            // Don't overwrite markdownValue if we're the ones who emitted
            if (!isEmittingFromMarkdown.value) {
                markdownValue.value = value || '';
            }
            isEmittingFromMarkdown.value = false;
            return;
        }

        const current = editor.value.getHTML();
        if ((value || '') !== current) {
            if (type === 'markdown') {
                editor.value.commands.setContent(value || '', {
                    contentType: 'markdown',
                });
                const html = editor.value.getHTML();
                emit('update:modelValue', html);
                emit('content-type-change', 'html');
            } else {
                editor.value.commands.setContent(value || '', false);
            }
        }
    },
);

// When in markdown mode, convert and emit HTML as user types
watch(markdownValue, (value) => {
    if (!isMarkdownMode.value || !editor?.value) return;
    // Convert markdown to HTML via the editor
    editor.value.commands.setContent(value || '', {
        contentType: 'markdown',
    });
    // Set flag to prevent feedback loop
    isEmittingFromMarkdown.value = true;
    emit('update:modelValue', editor.value.getHTML());
});

// Toggle editor editable state when disabled prop changes
watch(
    () => props.disabled,
    (disabled) => {
        if (editor?.value) {
            editor.value.setEditable(!disabled);
        }
    },
);

onBeforeUnmount(() => {
    editor?.value?.destroy();
});
</script>

<template>
    <div class="flex flex-col gap-3">
        <div
            class="flex flex-wrap items-center gap-2 rounded-md border bg-card p-2"
            :class="{ 'pointer-events-none opacity-50': disabled }"
        >
            <Button
                variant="ghost"
                size="icon"
                :class="toolbarButtonClass"
                :aria-pressed="isActive('heading', { level: 1 })"
                aria-label="Heading 1"
                @click="
                    editor?.chain().focus().toggleHeading({ level: 1 }).run()
                "
            >
                <Heading1 class="h-4 w-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                :class="toolbarButtonClass"
                :aria-pressed="isActive('heading', { level: 2 })"
                aria-label="Heading 2"
                @click="
                    editor?.chain().focus().toggleHeading({ level: 2 }).run()
                "
            >
                <Heading2 class="h-4 w-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                :class="toolbarButtonClass"
                :aria-pressed="isActive('heading', { level: 3 })"
                aria-label="Heading 3"
                @click="
                    editor?.chain().focus().toggleHeading({ level: 3 }).run()
                "
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
            <Button
                variant="ghost"
                size="icon"
                :class="toolbarButtonClass"
                aria-label="Insert link"
                @click="handleLink"
            >
                <Link2 class="h-4 w-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                :class="toolbarButtonClass"
                aria-label="Insert image"
                @click="emit('request-image')"
            >
                <ImageIcon class="h-4 w-4" />
            </Button>
            <span class="h-6 w-px bg-border" />
            <Button
                variant="outline"
                size="sm"
                class="ml-auto h-9 gap-2"
                @click="toggleMarkdownMode"
            >
                <Type class="h-4 w-4" />
                <span>{{
                    isMarkdownMode ? 'Back to editor' : 'Markdown mode'
                }}</span>
            </Button>
        </div>

        <div
            class="rounded-lg border bg-card"
            :class="{ 'opacity-50': disabled }"
        >
            <div v-if="isMarkdownMode" class="p-3">
                <textarea
                    v-model="markdownValue"
                    :disabled="disabled"
                    class="min-h-[320px] w-full rounded-md border border-input bg-background p-3 text-sm shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed"
                    placeholder="Write in markdown..."
                />
            </div>
            <EditorContent
                v-else
                :editor="editor"
                class="tiptap prose prose-slate dark:prose-invert min-h-[320px] max-w-none p-4"
            />
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
    background: rgb(30 41 59);
    color: rgb(226 232 240);
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    overflow-x: auto;
    border: 1px solid rgb(51 65 85);
    font-family:
        ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.875rem;
    line-height: 1.5;
}

:deep(.dark .tiptap pre) {
    background: rgb(15 23 42);
    color: rgb(203 213 225);
    border-color: rgb(30 41 59);
}

:deep(.tiptap pre code) {
    background: transparent;
    color: inherit;
    padding: 0;
    border-radius: 0;
    font-size: inherit;
}

:deep(.tiptap code) {
    background: rgb(241 245 249);
    color: rgb(51 65 85);
    padding: 0.2rem 0.4rem;
    border-radius: 0.3rem;
    font-family:
        ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.875em;
}

:deep(.dark .tiptap code) {
    background: rgb(30 41 59);
    color: rgb(203 213 225);
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

:deep(.tiptap img) {
    max-width: 100%;
    height: auto;
    border-radius: 0.5rem;
    margin: 1rem 0;
    cursor: pointer;
}

:deep(.tiptap img.ProseMirror-selectednode) {
    outline: 2px solid rgb(37 99 235);
    outline-offset: 2px;
}

:deep(.tiptap img:hover) {
    opacity: 0.9;
}

:deep(.image-resizer) {
    position: relative;
    display: inline-block;
    max-width: 100%;
}

:deep(.image-resizer.ProseMirror-selectednode) {
    outline: 2px solid rgb(37 99 235);
    outline-offset: 2px;
    border-radius: 0.5rem;
}

:deep(.resize-handle) {
    position: absolute;
    right: -4px;
    bottom: -4px;
    width: 12px;
    height: 12px;
    background: rgb(37 99 235);
    border: 2px solid white;
    border-radius: 50%;
    cursor: nwse-resize;
    opacity: 0;
    transition: opacity 0.2s;
    z-index: 10;
}

:deep(.image-resizer:hover .resize-handle),
:deep(.image-resizer.ProseMirror-selectednode .resize-handle) {
    opacity: 1;
}

:deep(.image-resizer img) {
    margin: 0;
    display: block;
}
</style>
