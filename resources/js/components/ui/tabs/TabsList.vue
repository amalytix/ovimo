<script setup lang="ts">
import { TabsList, type TabsListProps } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = withDefaults(
  defineProps<TabsListProps & { class?: string }>(),
  {}
)
</script>

<template>
  <TabsList
    data-slot="tabs-list"
    v-bind="props"
    :class="cn(
      'flex flex-wrap items-center gap-3 rounded-none bg-transparent p-0 text-muted-foreground',
      props.class
    )"
  >
    <slot />
  </TabsList>
</template>
