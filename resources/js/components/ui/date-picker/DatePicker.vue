<script setup lang="ts">
import type { DateValue } from '@internationalized/date'
import { CalendarDate, getLocalTimeZone, parseDate, today } from '@internationalized/date'
import { CalendarIcon } from 'lucide-vue-next'
import { computed, ref, watch, type HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Calendar } from '@/components/ui/calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'

const props = withDefaults(
  defineProps<{
    modelValue?: string
    placeholder?: string
    disabled?: boolean
    class?: HTMLAttributes['class']
  }>(),
  {
    placeholder: 'Select date',
    disabled: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string | undefined]
}>()

const open = ref(false)

// Convert string to DateValue for the calendar
const dateValue = computed<DateValue | undefined>({
  get() {
    if (!props.modelValue) return undefined
    try {
      return parseDate(props.modelValue)
    } catch {
      return undefined
    }
  },
  set(value: DateValue | undefined) {
    if (value) {
      const dateStr = `${value.year}-${String(value.month).padStart(2, '0')}-${String(value.day).padStart(2, '0')}`
      emit('update:modelValue', dateStr)
    } else {
      emit('update:modelValue', undefined)
    }
  },
})

// Format the display value
const displayValue = computed(() => {
  if (!dateValue.value) return ''
  const date = dateValue.value.toDate(getLocalTimeZone())
  return new Intl.DateTimeFormat('en-US', {
    dateStyle: 'medium',
  }).format(date)
})

function handleSelect(value: DateValue | undefined) {
  dateValue.value = value
  open.value = false
}
</script>

<template>
  <Popover v-model:open="open">
    <PopoverTrigger as-child>
      <Button
        variant="outline"
        :disabled="disabled"
        :class="cn(
          'w-full justify-start text-left font-normal',
          !dateValue && 'text-muted-foreground',
          props.class,
        )"
      >
        <CalendarIcon class="mr-2 h-4 w-4" />
        <span v-if="displayValue">{{ displayValue }}</span>
        <span v-else>{{ placeholder }}</span>
      </Button>
    </PopoverTrigger>
    <PopoverContent class="w-auto p-0" align="start">
      <Calendar
        :model-value="dateValue"
        initial-focus
        @update:model-value="handleSelect"
      />
    </PopoverContent>
  </Popover>
</template>
