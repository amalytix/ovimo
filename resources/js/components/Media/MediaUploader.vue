<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import axios from 'axios';
import { CloudUpload, FileWarning, Loader2, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

type UploadStatus = 'pending' | 'uploading' | 'storing' | 'complete' | 'error';

interface UploadItem {
    id: string;
    file: File;
    progress: number;
    status: UploadStatus;
    error?: string | null;
    preview?: string | null;
}

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'uploaded'): void;
}>();

const uploads = reactive<UploadItem[]>([]);
const maxSize = 50 * 1024 * 1024;
const allowedTypes = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/svg+xml',
    'application/pdf',
];
const fileInput = ref<HTMLInputElement | null>(null);
const dragActive = ref(false);

watch(
    () => props.open,
    (open) => {
        if (!open) {
            uploads.splice(0, uploads.length);
            dragActive.value = false;
        }
    }
);

const isUploading = computed(() => uploads.some((item) => ['uploading', 'storing', 'pending'].includes(item.status)));

const randomId = () => {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }
    return Math.random().toString(36).slice(2);
};

const validateFile = (file: File): string | null => {
    if (!allowedTypes.includes(file.type)) {
        return 'Unsupported file type';
    }
    if (file.size > maxSize) {
        return 'File exceeds 50MB limit';
    }
    return null;
};

const handleFiles = (files: FileList | null) => {
    if (!files?.length) return;
    Array.from(files).forEach((file) => queueUpload(file));
};

const queueUpload = (file: File) => {
    const error = validateFile(file);
    const upload: UploadItem = {
        id: randomId(),
        file,
        progress: 0,
        status: error ? 'error' : 'pending',
        error,
        preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
    };

    uploads.push(upload);

    if (!error) {
        startUpload(upload);
    }
};

const startUpload = async (item: UploadItem) => {
    item.status = 'uploading';
    try {
        const presignResponse = await axios.post('/media/presign', {
            filename: item.file.name,
            mime_type: item.file.type,
            file_size: item.file.size,
        });

        const presign = presignResponse.data;
        const formData = new FormData();
        Object.entries(presign.fields || {}).forEach(([key, value]) => {
            formData.append(key, value as string);
        });
        formData.append('file', item.file);

        await axios.post(presign.url, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (event) => {
                if (event.total) {
                    item.progress = Math.round((event.loaded / event.total) * 100);
                }
            },
        });

        item.status = 'storing';
        await axios.post('/media', {
            s3_key: presign.s3_key,
            filename: item.file.name,
            stored_filename: presign.stored_filename,
            file_path: presign.file_path,
            mime_type: item.file.type,
            file_size: item.file.size,
        });

        item.progress = 100;
        item.status = 'complete';
    } catch (error) {
        console.error(error);
        item.status = 'error';
        item.error = 'Upload failed';
    } finally {
        const allDone = uploads.every((upload) => upload.status === 'complete' || upload.status === 'error');
        if (allDone) {
            emit('uploaded');
        }
    }
};

const onDrop = (event: DragEvent) => {
    event.preventDefault();
    dragActive.value = false;
    handleFiles(event.dataTransfer?.files || null);
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-3xl">
            <DialogHeader>
                <DialogTitle>Upload Media</DialogTitle>
                <DialogDescription>Images and PDFs up to 50MB each.</DialogDescription>
            </DialogHeader>

            <div
                class="flex min-h-[220px] cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 text-center transition hover:border-blue-400 hover:bg-blue-50 dark:border-gray-800 dark:bg-gray-900/50 dark:hover:border-blue-500"
                :class="dragActive ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30' : ''"
                @dragover.prevent="dragActive = true"
                @dragleave.prevent="dragActive = false"
                @drop="onDrop"
                @click="fileInput?.click()"
            >
                <CloudUpload class="mb-3 h-10 w-10 text-blue-500" />
                <p class="text-base font-semibold text-gray-900 dark:text-gray-100">Drag & drop files or click to browse</p>
                <p class="mt-1 text-sm text-gray-500">Supported: JPEG, PNG, GIF, WebP, SVG, PDF</p>
                <input ref="fileInput" type="file" class="hidden" multiple accept="image/*,application/pdf" @change="handleFiles($event.target?.files || null)" />
            </div>

            <div v-if="uploads.length > 0" class="space-y-3">
                <div
                    v-for="upload in uploads"
                    :key="upload.id"
                    class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-md bg-gray-100 dark:bg-gray-800">
                        <img v-if="upload.preview" :src="upload.preview" class="h-full w-full object-cover" />
                        <FileWarning v-else class="h-6 w-6 text-gray-500" />
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-50">{{ upload.file.name }}</p>
                            <span class="text-xs text-gray-500">{{ (upload.file.size / 1024 / 1024).toFixed(2) }} MB</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100">
                            <div
                                class="h-full rounded-full bg-blue-500 transition-all"
                                :style="{ width: `${upload.progress}%` }"
                            />
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            <span v-if="upload.status === 'complete'">Upload complete</span>
                            <span v-else-if="upload.status === 'storing'">Saving to library…</span>
                            <span v-else-if="upload.status === 'uploading'">Uploading…</span>
                            <span v-else-if="upload.status === 'error'" class="text-red-500">{{ upload.error }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Loader2 v-if="upload.status === 'uploading' || upload.status === 'storing'" class="h-4 w-4 animate-spin text-blue-500" />
                        <X
                            v-if="upload.status === 'error'"
                            class="h-4 w-4 cursor-pointer text-gray-500 hover:text-gray-700"
                            @click="uploads.splice(uploads.indexOf(upload), 1)"
                        />
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <Button variant="ghost" :disabled="isUploading" @click="emit('update:open', false)">Close</Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
