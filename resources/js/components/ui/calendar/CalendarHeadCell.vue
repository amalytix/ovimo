<script setup lang="ts">
import { cn } from '@/lib/utils'
import { CalendarHeadCell } from 'reka-ui'
import type { HTMLAttributes } from 'vue'

const props = defineProps<{ class?: HTMLAttributes['class'] }>()
</script>

<template>
  <CalendarHeadCell
    :class="cn('text-muted-foreground w-8 rounded-md text-[0.8rem] font-normal', props.class)"
  >
    <slot />
  </CalendarHeadCell>
</template>
