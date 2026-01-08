<script setup lang="ts">
import { cn } from '@/lib/utils'
import { CalendarGridRow } from 'reka-ui'
import type { HTMLAttributes } from 'vue'

const props = defineProps<{ class?: HTMLAttributes['class'] }>()
</script>

<template>
  <CalendarGridRow :class="cn('flex', props.class)">
    <slot />
  </CalendarGridRow>
</template>
