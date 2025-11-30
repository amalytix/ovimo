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

const parseDate = (value: string): Date => new Date(`${value}T00:00:00`);

const formatInputDate = (date: Date): string => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const startOfWeek = (date: Date): Date => {
    const clone = new Date(date);
    const day = clone.getDay() || 7;
    clone.setDate(clone.getDate() - (day - 1));
    return clone;
};

const weekStart = computed(() => startOfWeek(parseDate(props.date)));

const weekDays = computed<CalendarDay[]>(() => {
    const days: CalendarDay[] = [];
    const cursor = new Date(weekStart.value);

    for (let i = 0; i < 7; i++) {
        const dateString = formatInputDate(cursor);
        days.push({
            date: dateString,
            label: new Intl.DateTimeFormat(undefined, {
                weekday: 'short',
                month: 'short',
                day: 'numeric',
            }).format(cursor),
            isToday: dateString === todayString,
            events: events.value[dateString] || [],
        });
        cursor.setDate(cursor.getDate() + 1);
    }

    return days;
});

const weekLabel = computed(() => {
    const end = new Date(weekStart.value);
    end.setDate(end.getDate() + 6);

    const startText = new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(weekStart.value);
    const endText = new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(end);

    return `${startText} - ${endText}`;
});

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
                    view: 'week',
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

const goToPreviousWeek = () => {
    const current = parseDate(props.date);
    current.setDate(current.getDate() - 7);
    emit('update:date', formatInputDate(current));
};

const goToNextWeek = () => {
    const current = parseDate(props.date);
    current.setDate(current.getDate() + 7);
    emit('update:date', formatInputDate(current));
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
                <h2 class="text-xl font-semibold">Week Overview</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ weekLabel }}
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
                        @click="goToPreviousWeek"
                        >‹</Button
                    >
                    <Button variant="outline" size="icon" @click="goToNextWeek"
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

        <div v-else class="grid gap-3 lg:grid-cols-7">
            <div
                v-for="day in weekDays"
                :key="day.date"
                class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-950"
            >
                <div class="mb-2 flex items-center justify-between">
                    <div>
                        <p
                            class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400"
                        >
                            {{ day.date }}
                        </p>
                        <p
                            class="text-sm font-semibold text-gray-900 dark:text-gray-100"
                        >
                            {{ day.label }}
                        </p>
                    </div>
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
                            <span v-if="formatEventTime(event.published_at)">
                                {{ formatEventTime(event.published_at) }}
                            </span>
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
</template>
