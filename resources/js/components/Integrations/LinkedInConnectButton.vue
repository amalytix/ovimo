<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { connect as connectRoute } from '@/routes/integrations/linkedin';
import { ref } from 'vue';

const isConnecting = ref(false);

const start = () => {
    isConnecting.value = true;
    window.location.href = connectRoute.url();
};
</script>

<template>
    <Button :disabled="isConnecting" @click="start">
        {{ isConnecting ? 'Opening LinkedIn…' : 'Connect LinkedIn' }}
    </Button>
</template>
