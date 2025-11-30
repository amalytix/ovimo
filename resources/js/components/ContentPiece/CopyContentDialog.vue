<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps<{
    open: boolean;
    hasExistingContent: boolean;
}>();

const emit = defineEmits<{
    (event: 'confirm', value: 'replace' | 'append'): void;
    (event: 'cancel'): void;
    (event: 'update:open', value: boolean): void;
}>();

const close = () => emit('update:open', false);

const handleConfirm = (mode: 'replace' | 'append') => {
    emit('confirm', mode);
    close();
};

const handleCancel = () => {
    emit('cancel');
    close();
};
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Copy research to editor</DialogTitle>
                <DialogDescription>
                    {{
                        hasExistingContent
                            ? 'Edited text already contains content.'
                            : 'Move the generated research into the editor.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-3 text-sm text-muted-foreground">
                <p>
                    Choose how you want to move the research text into the
                    editing tab.
                </p>
                <ul class="list-disc space-y-1 pl-5">
                    <li>
                        <strong>Replace</strong> will overwrite the current
                        edited text.
                    </li>
                    <li>
                        <strong>Append</strong> will add the research text below
                        the existing content.
                    </li>
                </ul>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="secondary" @click="handleCancel"
                    >Cancel</Button
                >
                <Button variant="outline" @click="handleConfirm('append')"
                    >Append</Button
                >
                <Button @click="handleConfirm('replace')">Replace</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
