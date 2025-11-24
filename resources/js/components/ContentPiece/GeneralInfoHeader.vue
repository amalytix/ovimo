<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Pencil } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

type StatusOption = {
    value: string;
    label: string;
};

type ChannelOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    form: Record<string, any>;
    contentPieceTitle?: string | null;
    channels?: ChannelOption[];
    statuses?: StatusOption[];
}>();

const defaultStatuses: StatusOption[] = [
    { value: 'NOT_STARTED', label: 'Not started' },
    { value: 'DRAFT', label: 'Draft' },
    { value: 'FINAL', label: 'Final' },
];

const defaultChannels: ChannelOption[] = [
    { value: 'BLOG_POST', label: 'Blog post' },
    { value: 'LINKEDIN_POST', label: 'LinkedIn post' },
    { value: 'YOUTUBE_SCRIPT', label: 'YouTube script' },
];

const placeholderTitle = 'New Content Piece';
const publishedAtLabel = () => (props.form.published_at ? 'Scheduled' : 'Schedule');

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

const initializeTitle = () => {
    if (!titleRef.value) return;

    const initialValue = props.contentPieceTitle || props.form.internal_name || '';
    titleRef.value.textContent = initialValue;
};

onMounted(() => {
    initializeTitle();
});
</script>

<template>
    <div class="rounded-xl border bg-card p-4 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="space-y-1">
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Content piece</p>
                <div class="flex items-center gap-2">
                    <div
                        ref="titleRef"
                        class="min-w-[200px] rounded-md px-2 py-1 text-2xl font-semibold leading-tight text-foreground hover:bg-muted focus-visible:outline-none"
                        contenteditable="true"
                        :data-placeholder="placeholderTitle"
                        dir="ltr"
                        style="unicode-bidi: plaintext;"
                        role="textbox"
                        aria-label="Content piece title"
                        @input="handleTitleInput"
                        @focus="handleTitleFocus"
                        @keydown.enter.prevent
                    />
                    <Pencil class="h-4 w-4 text-muted-foreground" />
                </div>
                <InputError :message="props.form.errors?.internal_name" />
                <p class="text-sm text-muted-foreground">Keep the basics aligned while you switch between tabs.</p>
            </div>
            <!-- eslint-disable-next-line vue/no-mutating-props -->
            <Select v-model="form.status">
                <SelectTrigger class="w-40">
                    <SelectValue placeholder="Status" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="status in props.statuses ?? defaultStatuses" :key="status.value" :value="status.value">
                        {{ status.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div class="space-y-2">
                <Label for="channel">Channel</Label>
                <!-- eslint-disable-next-line vue/no-mutating-props -->
                <Select v-model="form.channel">
                    <SelectTrigger id="channel">
                        <SelectValue placeholder="Channel" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="channel in props.channels ?? defaultChannels" :key="channel.value" :value="channel.value">
                            {{ channel.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.channel" />
            </div>

            <div class="space-y-2">
                <Label for="target_language">Target language</Label>
                <!-- eslint-disable-next-line vue/no-mutating-props -->
                <Select v-model="form.target_language">
                    <SelectTrigger id="target_language">
                        <SelectValue placeholder="Language" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="ENGLISH">English</SelectItem>
                        <SelectItem value="GERMAN">German</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.target_language" />
            </div>

            <div class="space-y-2">
                <Label for="published_at">{{ publishedAtLabel() }}</Label>
                <!-- eslint-disable-next-line vue/no-mutating-props -->
                <Input id="published_at" v-model="form.published_at" type="datetime-local" />
                <InputError :message="form.errors.published_at" />
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep([data-placeholder]:empty)::before {
    content: attr(data-placeholder);
    color: rgb(148 163 184);
}
</style>
