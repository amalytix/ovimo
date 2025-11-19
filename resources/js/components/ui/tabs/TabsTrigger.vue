<script setup lang="ts">
import { TabsTrigger, type TabsTriggerProps } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = withDefaults(
  defineProps<TabsTriggerProps & { class?: string }>(),
  {}
)
</script>

<template>
  <TabsTrigger
    data-slot="tabs-trigger"
    v-bind="props"
    :class="cn(
      'inline-flex items-center justify-center whitespace-nowrap rounded-md px-4 py-2 text-sm font-medium text-muted-foreground ring-offset-background transition-colors hover:bg-muted/60 hover:text-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
      'data-[state=active]:bg-muted data-[state=active]:text-foreground data-[state=active]:shadow-none',
      props.class
    )"
  >
    <slot />
  </TabsTrigger>
</template>
