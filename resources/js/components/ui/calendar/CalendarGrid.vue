<script setup lang="ts">
import { cn } from '@/lib/utils'
import { CalendarGrid, type CalendarGridProps } from 'reka-ui'
import { computed, type HTMLAttributes } from 'vue'

const props = defineProps<CalendarGridProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props
  return delegated
})
</script>

<template>
  <CalendarGrid
    :class="cn('w-full border-collapse space-y-1', props.class)"
    v-bind="delegatedProps"
  >
    <slot />
  </CalendarGrid>
</template>
