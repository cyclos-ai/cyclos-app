<template>
    <div class="space-y-0">
        <div
            v-for="(event, index) in events"
            :key="event.uuid || index"
            class="flex gap-4 relative timeline-item pb-6"
        >
            <!-- Icon column -->
            <div class="flex flex-col items-center flex-shrink-0">
                <div
                    class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 z-10"
                    :class="iconBg(event.event_type)"
                >
                    <i :class="`pi ${eventIcon(event.event_type)} text-sm`" :style="{ color: iconColor(event.event_type) }"></i>
                </div>
                <div v-if="index < events.length - 1" class="w-0.5 flex-1 bg-gray-200 mt-1"></div>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0 pb-2">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ formatEventType(event.event_type) }}</p>
                        <p v-if="event.location" class="text-sm text-gray-600 mt-0.5">
                            <i class="pi pi-map-marker text-xs mr-1 text-gray-400"></i>
                            {{ event.location }}
                        </p>
                        <p v-if="event.description" class="text-sm text-gray-500 mt-1">{{ event.description }}</p>
                        <p v-if="event.vessel_name" class="text-xs text-blue-600 mt-0.5">
                            <i class="pi pi-send text-xs mr-1"></i>
                            {{ event.vessel_name }} {{ event.voyage_number ? `(${event.voyage_number})` : '' }}
                        </p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-xs font-medium text-gray-700">{{ formatDate(event.event_date) }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ formatTime(event.event_date) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!events?.length" class="text-center py-8 text-gray-400">
            <i class="pi pi-history text-3xl mb-2 block"></i>
            <p class="text-sm">No events recorded yet</p>
        </div>
    </div>
</template>

<script setup>
import dayjs from 'dayjs';

defineProps({
    events: {
        type: Array,
        default: () => [],
    },
});

const eventIconMap = {
    gate_in:          { icon: 'pi-sign-in',      bg: 'bg-blue-50',   color: '#3b82f6' },
    gate_out:         { icon: 'pi-sign-out',     bg: 'bg-indigo-50', color: '#6366f1' },
    vessel_departure: { icon: 'pi-send',         bg: 'bg-purple-50', color: '#8b5cf6' },
    vessel_arrival:   { icon: 'pi-map-marker',   bg: 'bg-teal-50',   color: '#14b8a6' },
    discharge:        { icon: 'pi-download',     bg: 'bg-cyan-50',   color: '#06b6d4' },
    customs_release:  { icon: 'pi-check-circle', bg: 'bg-green-50',  color: '#22c55e' },
    customs_hold:     { icon: 'pi-exclamation-triangle', bg: 'bg-red-50', color: '#ef4444' },
    delivery:         { icon: 'pi-home',         bg: 'pi-home',      color: '#22c55e' },
    empty_return:     { icon: 'pi-refresh',      bg: 'bg-gray-50',   color: '#6b7280' },
    rail_departure:   { icon: 'pi-arrow-right',  bg: 'bg-orange-50', color: '#f97316' },
    rail_arrival:     { icon: 'pi-arrow-left',   bg: 'bg-orange-50', color: '#f97316' },
    transshipment:    { icon: 'pi-arrows-h',     bg: 'bg-yellow-50', color: '#eab308' },
};

function eventIcon(type) {
    return eventIconMap[type]?.icon || 'pi-circle';
}

function iconBg(type) {
    return eventIconMap[type]?.bg || 'bg-gray-50';
}

function iconColor(type) {
    return eventIconMap[type]?.color || '#6b7280';
}

function formatEventType(type) {
    return type
        ? type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
        : 'Event';
}

function formatDate(date) {
    return date ? dayjs(date).format('MMM D, YYYY') : '—';
}

function formatTime(date) {
    return date ? dayjs(date).format('h:mm A') : '';
}
</script>
