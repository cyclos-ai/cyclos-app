<template>
    <div>
        <PageHeader title="Calendar" subtitle="ETAs, LFDs, and cut-off dates">
            <template #actions>
                <div class="flex items-center gap-2">
                    <Button icon="pi pi-chevron-left" text rounded size="small" @click="prevMonth" />
                    <span class="text-sm font-semibold text-gray-700 w-36 text-center">{{ currentMonthLabel }}</span>
                    <Button icon="pi pi-chevron-right" text rounded size="small" @click="nextMonth" />
                    <Button label="Today" text size="small" @click="goToday" />
                </div>
                <!-- Legend -->
                <div class="flex items-center gap-3 text-xs text-gray-500 border-l border-gray-200 pl-4">
                    <span v-for="type in eventTypes" :key="type.value" class="flex items-center gap-1">
                        <span class="w-2.5 h-2.5 rounded-sm" :style="{ background: type.color }"></span>
                        {{ type.label }}
                    </span>
                </div>
            </template>
        </PageHeader>

        <!-- Calendar grid -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <!-- Day headers -->
            <div class="grid grid-cols-7 border-b border-gray-200">
                <div
                    v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']"
                    :key="day"
                    class="py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider"
                >
                    {{ day }}
                </div>
            </div>

            <!-- Calendar days -->
            <div class="grid grid-cols-7">
                <div
                    v-for="(day, idx) in calendarDays"
                    :key="idx"
                    class="min-h-[100px] border-r border-b border-gray-100 p-1.5 last:border-r-0"
                    :class="{
                        'bg-gray-50 text-gray-400': !day.isCurrentMonth,
                        'bg-blue-50': day.isToday,
                    }"
                >
                    <div
                        class="text-xs font-medium mb-1 w-6 h-6 flex items-center justify-center rounded-full"
                        :class="day.isToday ? 'bg-blue-600 text-white' : 'text-gray-700'"
                    >
                        {{ day.date }}
                    </div>
                    <div class="space-y-0.5">
                        <div
                            v-for="event in day.events.slice(0, 3)"
                            :key="event.uuid"
                            class="text-xs px-1.5 py-0.5 rounded truncate cursor-pointer hover:opacity-80"
                            :style="{ background: eventColor(event.type) + '20', color: eventColor(event.type), borderLeft: `2px solid ${eventColor(event.type)}` }"
                            @click="selectedEvent = event"
                            :title="event.label"
                        >
                            {{ event.label }}
                        </div>
                        <div
                            v-if="day.events.length > 3"
                            class="text-xs text-gray-400 pl-1 cursor-pointer hover:text-gray-600"
                            @click="selectedDay = day"
                        >
                            +{{ day.events.length - 3 }} more
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Event detail popup -->
        <Dialog :visible="selectedEvent !== null" @update:visible="v => { if (!v) selectedEvent = null; }" header="Event Details" modal class="w-80">
            <div v-if="selectedEvent" class="space-y-3 pt-2 text-sm">
                <div>
                    <p class="text-xs text-gray-500">Type</p>
                    <span
                        class="inline-block text-xs px-2 py-0.5 rounded mt-1"
                        :style="{ background: eventColor(selectedEvent.type) + '20', color: eventColor(selectedEvent.type) }"
                    >
                        {{ eventTypeLabel(selectedEvent.type) }}
                    </span>
                </div>
                <div><p class="text-xs text-gray-500">Container / Reference</p><p class="font-medium">{{ selectedEvent.label }}</p></div>
                <div><p class="text-xs text-gray-500">Date</p><p class="font-medium">{{ formatDate(selectedEvent.date) }}</p></div>
                <div v-if="selectedEvent.carrier"><p class="text-xs text-gray-500">Carrier</p><p class="font-medium">{{ selectedEvent.carrier }}</p></div>
            </div>
            <template #footer>
                <Button label="Close" text @click="selectedEvent = null" />
                <router-link
                    v-if="selectedEvent?.container_uuid"
                    :to="{ name: 'container-detail', params: { uuid: selectedEvent.container_uuid } }"
                    @click="selectedEvent = null"
                >
                    <Button label="View Container" size="small" />
                </router-link>
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import dayjs from 'dayjs';
import PageHeader from '@/components/PageHeader.vue';
import api from '@/plugins/api';

const currentDate = ref(dayjs());
const events = ref([]);
const selectedEvent = ref(null);
const selectedDay = ref(null);
const loading = ref(false);

const eventTypes = [
    { value: 'eta', label: 'ETA', color: '#3b82f6' },
    { value: 'ata', label: 'ATA', color: '#22c55e' },
    { value: 'lfd', label: 'LFD', color: '#ef4444' },
    { value: 'cutoff', label: 'Cut-off', color: '#f97316' },
    { value: 'empty_return', label: 'Empty Return', color: '#8b5cf6' },
];

const currentMonthLabel = computed(() => currentDate.value.format('MMMM YYYY'));

const calendarDays = computed(() => {
    const startOfMonth = currentDate.value.startOf('month');
    const endOfMonth = currentDate.value.endOf('month');
    const startDay = startOfMonth.day(); // 0=Sun
    const days = [];

    // Pad from previous month
    for (let i = startDay - 1; i >= 0; i--) {
        const d = startOfMonth.subtract(i + 1, 'day');
        days.push({ fullDate: d, date: d.date(), isCurrentMonth: false, isToday: false, events: [] });
    }

    // Current month days
    for (let d = 0; d < endOfMonth.date(); d++) {
        const date = startOfMonth.add(d, 'day');
        const isToday = date.isSame(dayjs(), 'day');
        const dayEvents = events.value.filter(e => dayjs(e.date).isSame(date, 'day'));
        days.push({ fullDate: date, date: date.date(), isCurrentMonth: true, isToday, events: dayEvents });
    }

    // Pad to fill 6 rows
    const remaining = 42 - days.length;
    for (let i = 1; i <= remaining; i++) {
        const d = endOfMonth.add(i, 'day');
        days.push({ fullDate: d, date: d.date(), isCurrentMonth: false, isToday: false, events: [] });
    }

    return days;
});

function eventColor(type) {
    return eventTypes.find(t => t.value === type)?.color || '#6b7280';
}

function eventTypeLabel(type) {
    return eventTypes.find(t => t.value === type)?.label || type;
}

function formatDate(d) { return d ? dayjs(d).format('MMMM D, YYYY') : '—'; }

function prevMonth() { currentDate.value = currentDate.value.subtract(1, 'month'); loadEvents(); }
function nextMonth() { currentDate.value = currentDate.value.add(1, 'month'); loadEvents(); }
function goToday() { currentDate.value = dayjs(); loadEvents(); }

async function loadEvents() {
    loading.value = true;
    try {
        const response = await api.get('/calendar/events', {
            params: {
                month: currentDate.value.format('YYYY-MM'),
            },
        });
        events.value = response.data;
    } catch {
        // Use empty on error
    } finally {
        loading.value = false;
    }
}

onMounted(loadEvents);
</script>
