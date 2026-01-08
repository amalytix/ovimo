<script setup lang="ts">
import type { DateValue } from '@internationalized/date'
import { CalendarDate, getLocalTimeZone, parseDate } from '@internationalized/date'
import { CalendarIcon, Clock } from 'lucide-vue-next'
import { computed, ref, type HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Calendar } from '@/components/ui/calendar'
import { Input } from '@/components/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'

const props = withDefaults(
  defineProps<{
    modelValue?: string
    placeholder?: string
    disabled?: boolean
    class?: HTMLAttributes['class']
  }>(),
  {
    placeholder: 'Select date & time',
    disabled: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string | undefined]
}>()

const open = ref(false)

// Parse the datetime-local format (YYYY-MM-DDTHH:mm)
const dateValue = computed<DateValue | undefined>(() => {
  if (!props.modelValue) return undefined
  try {
    const datePart = props.modelValue.split('T')[0]
    return parseDate(datePart)
  } catch {
    return undefined
  }
})

const timeValue = computed<string>(() => {
  if (!props.modelValue) return '12:00'
  const parts = props.modelValue.split('T')
  return parts[1] || '12:00'
})

// Format the display value
const displayValue = computed(() => {
  if (!dateValue.value) return ''
  const date = dateValue.value.toDate(getLocalTimeZone())
  const dateStr = new Intl.DateTimeFormat('en-US', {
    dateStyle: 'medium',
  }).format(date)
  return `${dateStr} at ${timeValue.value}`
})

function updateDateTime(newDate: DateValue | undefined, newTime: string) {
  if (!newDate) {
    emit('update:modelValue', undefined)
    return
  }
  const dateStr = `${newDate.year}-${String(newDate.month).padStart(2, '0')}-${String(newDate.day).padStart(2, '0')}`
  emit('update:modelValue', `${dateStr}T${newTime}`)
}

function handleDateSelect(value: DateValue | undefined) {
  updateDateTime(value, timeValue.value)
}

function handleTimeChange(event: Event) {
  const target = event.target as HTMLInputElement
  if (dateValue.value) {
    updateDateTime(dateValue.value, target.value)
  }
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
        @update:model-value="handleDateSelect"
      />
      <div class="border-t p-3">
        <div class="flex items-center gap-2">
          <Clock class="h-4 w-4 text-muted-foreground" />
          <Input
            type="time"
            :value="timeValue"
            class="w-full"
            @input="handleTimeChange"
          />
        </div>
      </div>
    </PopoverContent>
  </Popover>
</template>
