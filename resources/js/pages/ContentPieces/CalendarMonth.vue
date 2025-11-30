<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { calendar, edit } from '@/routes/content-pieces';
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

interface CalendarEvent {
    id: number;
    internal_name: string;
    prompt_name: string | null;
    published_at: string | null;
    published_at_human: string | null;
    status: string;
}

interface CalendarDay {
    date: string;
    label: string;
    isCurrentMonth: boolean;
    isToday: boolean;
    events: CalendarEvent[];
}

const props = defineProps<{
    date: string;
}>();

const emit = defineEmits<{
    (e: 'update:date', value: string): void;
}>();

const events = ref<Record<string, CalendarEvent[]>>({});
const loading = ref(false);
const todayString = new Date().toISOString().slice(0, 10);

const weekDayHeadings = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const parseDate = (value: string): Date => new Date(`${value}T00:00:00`);

const formatInputDate = (date: Date): string => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const startOfWeek = (date: Date): Date => {
    const clone = new Date(date);
    const day = clone.getDay() || 7; // Sunday -> 7
    clone.setDate(clone.getDate() - (day - 1));
    return clone;
};

const endOfWeek = (date: Date): Date => {
    const start = startOfWeek(date);
    const end = new Date(start);
    end.setDate(start.getDate() + 6);
    return end;
};

const startOfMonthGrid = computed(() => {
    const current = parseDate(props.date);
    const start = new Date(current.getFullYear(), current.getMonth(), 1);
    return startOfWeek(start);
});

const endOfMonthGrid = computed(() => {
    const current = parseDate(props.date);
    const end = new Date(current.getFullYear(), current.getMonth() + 1, 0);
    return endOfWeek(end);
});

const calendarDays = computed<CalendarDay[]>(() => {
    const days: CalendarDay[] = [];
    const cursor = new Date(startOfMonthGrid.value);
    const finalDate = endOfMonthGrid.value;
    const current = parseDate(props.date);

    while (cursor <= finalDate) {
        const dateString = formatInputDate(cursor);

        days.push({
            date: dateString,
            label: cursor.getDate().toString(),
            isCurrentMonth: cursor.getMonth() === current.getMonth(),
            isToday: dateString === todayString,
            events: events.value[dateString] || [],
        });

        cursor.setDate(cursor.getDate() + 1);
    }

    return days;
});

const monthLabel = computed(() =>
    new Intl.DateTimeFormat(undefined, {
        month: 'long',
        year: 'numeric',
    }).format(parseDate(props.date)),
);

const formatEventTime = (value: string | null) => {
    if (!value) {
        return null;
    }

    return new Intl.DateTimeFormat(undefined, {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

const loadEvents = async () => {
    loading.value = true;
    try {
        const response = await fetch(
            calendar.url({
                query: {
                    view: 'month',
                    date: props.date,
                },
            }),
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        );

        if (!response.ok) {
            throw new Error('Unable to load calendar data');
        }

        const data = await response.json();
        events.value = data.events ?? {};
    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
};

const goToToday = () => emit('update:date', todayString);

const goToPreviousMonth = () => {
    const current = parseDate(props.date);
    const previous = new Date(current.getFullYear(), current.getMonth() - 1, 1);
    emit('update:date', formatInputDate(previous));
};

const goToNextMonth = () => {
    const current = parseDate(props.date);
    const next = new Date(current.getFullYear(), current.getMonth() + 1, 1);
    emit('update:date', formatInputDate(next));
};

onMounted(loadEvents);
watch(
    () => props.date,
    () => loadEvents(),
);
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold">{{ monthLabel }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Week starts on Monday
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" @click="goToToday"
                    >Today</Button
                >
                <div class="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="icon"
                        @click="goToPreviousMonth"
                        >‹</Button
                    >
                    <Button variant="outline" size="icon" @click="goToNextMonth"
                        >›</Button
                    >
                </div>
            </div>
        </div>

        <div
            v-if="loading"
            class="rounded-lg border border-dashed border-gray-300 p-6 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300"
        >
            Loading calendar...
        </div>

        <div
            v-else
            class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950"
        >
            <div class="min-w-[960px]">
                <div
                    class="grid grid-cols-7 bg-gray-50 text-center text-xs font-semibold tracking-wide text-gray-500 uppercase dark:bg-gray-900 dark:text-gray-400"
                >
                    <div
                        v-for="heading in weekDayHeadings"
                        :key="heading"
                        class="border-b border-gray-200 px-2 py-3 dark:border-gray-800"
                    >
                        {{ heading }}
                    </div>
                </div>
                <div
                    class="grid grid-cols-7 gap-px bg-gray-100 dark:bg-gray-800"
                >
                    <div
                        v-for="day in calendarDays"
                        :key="day.date"
                        class="relative min-h-[9rem] bg-white p-3 text-sm dark:bg-gray-900"
                        :class="{
                            'bg-gray-50 dark:bg-gray-800': !day.isCurrentMonth,
                            'ring-1 ring-blue-500 ring-offset-1 ring-offset-white dark:ring-offset-gray-900':
                                day.isToday,
                        }"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400"
                            >
                                {{ day.date }}
                            </span>
                            <span
                                v-if="day.isToday"
                                class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700 dark:bg-blue-900/40 dark:text-blue-200"
                            >
                                Today
                            </span>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="event in day.events"
                                :key="event.id"
                                class="rounded-lg border border-gray-200 bg-white/80 p-2 shadow-sm transition hover:border-blue-300 hover:bg-blue-50 dark:border-gray-700 dark:bg-gray-900/70 dark:hover:border-blue-500"
                            >
                                <Link
                                    :href="edit.url(event.id)"
                                    class="text-sm font-semibold text-gray-900 hover:text-blue-600 dark:text-gray-100 dark:hover:text-blue-300"
                                >
                                    {{ event.internal_name }}
                                </Link>
                                <div
                                    class="mt-1 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400"
                                >
                                    <span>{{
                                        event.prompt_name || 'Prompt not set'
                                    }}</span>
                                    <span
                                        v-if="
                                            formatEventTime(event.published_at)
                                        "
                                        >{{
                                            formatEventTime(event.published_at)
                                        }}</span
                                    >
                                </div>
                            </div>
                            <p
                                v-if="day.events.length === 0"
                                class="text-xs text-gray-400 dark:text-gray-500"
                            >
                                No content
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
