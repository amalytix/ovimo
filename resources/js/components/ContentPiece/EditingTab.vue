<script setup lang="ts">
import MediaCard from '@/components/Media/MediaCard.vue';
import { Button } from '@/components/ui/button';
import type { MediaItem } from '@/types/media';
import { ref } from 'vue';
import TiptapEditor from './TiptapEditor.vue';

const props = defineProps<{
    form: Record<string, any>;
    selectedMedia: MediaItem[];
    contentType: 'html' | 'markdown';
}>();

const emit = defineEmits<{
    (event: 'open-media-picker'): void;
    (event: 'remove-media', id: number): void;
    (event: 'request-image'): void;
    (event: 'content-type-change', value: 'html' | 'markdown'): void;
}>();

const editorRef = ref<InstanceType<typeof TiptapEditor> | null>(null);

const removeMedia = (id: number) => emit('remove-media', id);

const insertImage = (src: string) => {
    editorRef.value?.insertImage(src);
};

defineExpose({
    insertImage,
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold">Edited content</h3>
                <p class="text-sm text-muted-foreground">Polish the piece, embed media, and toggle modes.</p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" @click="emit('open-media-picker')">Add Media</Button>
                <Button variant="ghost" @click="emit('request-image')">Insert image in editor</Button>
            </div>
        </div>

        <TiptapEditor
            ref="editorRef"
            v-model="form.edited_text"
            :content-type="contentType"
            placeholder="Start editing..."
            @request-image="emit('request-image')"
            @content-type-change="emit('content-type-change', $event)"
        />

        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h4 class="text-sm font-semibold">Attached media</h4>
                    <Button size="sm" variant="outline" @click="emit('open-media-picker')">Add Media</Button>
                </div>
                <span class="text-xs text-muted-foreground">{{ selectedMedia.length }} files</span>
            </div>
            <div v-if="selectedMedia.length" class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-6">
                <MediaCard
                    v-for="media in selectedMedia"
                    :key="media.id"
                    :media="media"
                    :selected="true"
                    @toggle="removeMedia(media.id)"
                    @preview="removeMedia(media.id)"
                />
            </div>
            <div v-else class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">No media attached yet.</div>
        </div>
    </div>
</template>
