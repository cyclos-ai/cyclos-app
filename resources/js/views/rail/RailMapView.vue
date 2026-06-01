<template>
    <div class="flex flex-col h-full -m-6">
        <!-- Top bar -->
        <div class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between flex-shrink-0 z-10">
            <div class="flex items-center gap-4">
                <h1 class="font-bold text-gray-900 text-lg">Rail Shipments</h1>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 bg-blue-50 text-blue-700 text-xs font-medium px-2.5 py-1 rounded-full border border-blue-100">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500 inline-block"></span>
                        In Transit: {{ railStore.inTransitCount }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-green-50 text-green-700 text-xs font-medium px-2.5 py-1 rounded-full border border-green-100">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>
                        At Ramp: {{ railStore.atRampCount }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-700 text-xs font-medium px-2.5 py-1 rounded-full border border-amber-100">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 inline-block"></span>
                        Pending Pickup: {{ railStore.pendingPickupCount }}
                    </span>
                </div>
            </div>
            <Button
                label="New Rail Shipment"
                icon="pi pi-plus"
                size="small"
                @click="showCreateDialog = true"
            />
        </div>

        <!-- Body: sidebar + map -->
        <div class="flex flex-1 min-h-0 overflow-hidden">
            <!-- Sidebar -->
            <div class="w-[280px] flex-shrink-0 bg-white border-r border-gray-200 flex flex-col overflow-hidden">
                <!-- Sidebar filters -->
                <div class="px-3 pt-3 pb-2 flex flex-col gap-2 border-b border-gray-100">
                    <span class="p-input-icon-left w-full">
                        <i class="pi pi-search text-gray-400 text-xs"></i>
                        <InputText
                            v-model="sidebarSearch"
                            placeholder="Container, train ID..."
                            class="pl-7 w-full"
                            size="small"
                        />
                    </span>
                    <div class="flex gap-2">
                        <Select
                            v-model="sidebarCarrier"
                            :options="carrierOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Carrier"
                            size="small"
                            class="flex-1"
                            show-clear
                        />
                        <Select
                            v-model="sidebarStatus"
                            :options="statusOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Status"
                            size="small"
                            class="flex-1"
                            show-clear
                        />
                    </div>
                </div>

                <!-- Shipment cards list -->
                <div class="flex-1 overflow-y-auto">
                    <div v-if="railStore.loading" class="flex items-center justify-center py-10">
                        <i class="pi pi-spin pi-spinner text-2xl text-gray-300"></i>
                    </div>
                    <div v-else-if="filteredShipments.length === 0" class="py-10 text-center text-gray-400">
                        <i class="pi pi-train text-3xl mb-2 block"></i>
                        <p class="text-sm">No shipments found</p>
                    </div>
                    <div
                        v-for="shipment in filteredShipments"
                        :key="shipment.uuid"
                        class="px-3 py-2.5 border-b border-gray-100 cursor-pointer transition-colors hover:bg-gray-50"
                        :class="selectedShipmentUuid === shipment.uuid ? 'bg-blue-50 border-l-2 border-l-blue-500' : ''"
                        @click="focusShipment(shipment)"
                    >
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <span class="font-mono font-semibold text-gray-900 text-sm leading-tight">
                                {{ shipment.container_number }}
                            </span>
                            <StatusBadge :status="shipment.status" size="small" />
                        </div>
                        <div class="flex items-center gap-1.5 text-xs text-gray-500 mb-1">
                            <span class="font-semibold" :style="{ color: carrierColor(shipment.rail_carrier) }">
                                {{ shipment.rail_carrier || '—' }}
                            </span>
                            <span class="text-gray-300">·</span>
                            <span>{{ shipment.origin_ramp_code || '?' }} → {{ shipment.destination_ramp_code || '?' }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs text-gray-400">
                            <span v-if="shipment.train_id" class="font-mono">{{ shipment.train_id }}</span>
                            <span v-else class="italic">No train ID</span>
                            <span v-if="shipment.eta">ETA {{ formatDate(shipment.eta) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Map -->
            <div class="flex-1 relative">
                <l-map
                    ref="mapRef"
                    :zoom="mapZoom"
                    :center="mapCenter"
                    class="w-full h-full"
                    :options="{ zoomControl: true, attributionControl: false }"
                    @ready="onMapReady"
                >
                    <l-tile-layer
                        url="https://cartodb-basemaps-{s}.global.ssl.fastly.net/dark_all/{z}/{x}/{y}{r}.png"
                        attribution="&copy; <a href='https://www.openstreetmap.org/copyright'>OpenStreetMap</a> contributors &copy; <a href='https://carto.com/attributions'>CARTO</a>"
                        :subdomains="['a','b','c','d']"
                    />

                    <!-- Ramp markers -->
                    <l-circle-marker
                        v-for="ramp in rampsWithCoords"
                        :key="ramp.uuid || ramp.code"
                        :lat-lng="[ramp.latitude, ramp.longitude]"
                        :radius="6"
                        :color="carrierColor(ramp.carrier)"
                        :fill-color="carrierColor(ramp.carrier)"
                        :fill-opacity="0.85"
                        :weight="1.5"
                        :options="{ className: 'ramp-marker' }"
                    >
                        <l-popup class="min-w-44">
                            <div class="p-1">
                                <p class="font-semibold text-gray-900 text-sm">{{ ramp.name || ramp.code }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ ramp.city }}, {{ ramp.state }}</p>
                                <div class="mt-2 space-y-1 text-xs">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Carrier</span>
                                        <span class="font-semibold" :style="{ color: carrierColor(ramp.carrier) }">{{ ramp.carrier }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Code</span>
                                        <span class="font-mono">{{ ramp.code }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Containers</span>
                                        <span class="font-semibold text-blue-600">{{ rampContainerCount(ramp.code) }}</span>
                                    </div>
                                </div>
                            </div>
                        </l-popup>
                    </l-circle-marker>

                    <!-- Shipment route lines -->
                    <template v-for="shipment in activeShipmentRoutes" :key="shipment.uuid">
                        <!-- Route line -->
                        <l-polyline
                            :lat-lngs="shipment.latlngs"
                            :color="carrierColor(shipment.rail_carrier)"
                            :weight="2"
                            :opacity="0.75"
                            :dash-array="'8 6'"
                        >
                            <l-popup class="min-w-52">
                                <div class="p-1">
                                    <p class="font-mono font-bold text-gray-900 text-sm">{{ shipment.container_number }}</p>
                                    <div class="mt-2 space-y-1 text-xs">
                                        <div class="flex justify-between">
                                            <span class="text-gray-500">Carrier</span>
                                            <span class="font-semibold" :style="{ color: carrierColor(shipment.rail_carrier) }">{{ shipment.rail_carrier }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-500">Train ID</span>
                                            <span class="font-mono">{{ shipment.train_id || '—' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-500">Status</span>
                                            <span>{{ shipment.status }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-500">ETA</span>
                                            <span>{{ formatDate(shipment.eta) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </l-popup>
                        </l-polyline>

                        <!-- Container position marker on line -->
                        <l-circle-marker
                            v-if="shipment.currentPos"
                            :lat-lng="shipment.currentPos"
                            :radius="5"
                            :color="'#ffffff'"
                            :fill-color="carrierColor(shipment.rail_carrier)"
                            :fill-opacity="1"
                            :weight="2"
                        />
                    </template>
                </l-map>

                <!-- Loading overlay -->
                <div v-if="railStore.loading" class="absolute inset-0 bg-gray-900/30 flex items-center justify-center z-20 pointer-events-none">
                    <div class="bg-gray-900 text-white text-sm px-4 py-2 rounded-lg flex items-center gap-2">
                        <i class="pi pi-spin pi-spinner"></i>
                        Loading rail data...
                    </div>
                </div>

                <!-- Carrier legend -->
                <div class="absolute bottom-4 right-4 z-10 bg-gray-900/90 text-white rounded-xl p-3 text-xs backdrop-blur-sm border border-gray-700">
                    <p class="font-semibold text-gray-300 mb-2 uppercase tracking-wider text-[10px]">Rail Carriers</p>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                        <div v-for="(color, scac) in CARRIER_COLORS" :key="scac" class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="{ background: color }"></span>
                            <span class="text-gray-200">{{ scac }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create dialog -->
        <CreateRailShipmentDialog
            v-model:visible="showCreateDialog"
            @created="onShipmentCreated"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { LMap, LTileLayer, LMarker, LPopup, LPolyline, LCircleMarker } from '@vue-leaflet/vue-leaflet';
import 'leaflet/dist/leaflet.css';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import dayjs from 'dayjs';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import CreateRailShipmentDialog from '@/components/rail/CreateRailShipmentDialog.vue';
import { useRailStore } from '@/stores/rail';

const railStore = useRailStore();

const mapRef = ref(null);
const showCreateDialog = ref(false);
const selectedShipmentUuid = ref(null);
const sidebarSearch = ref('');
const sidebarCarrier = ref(null);
const sidebarStatus = ref(null);
const mapZoom = ref(4);
const mapCenter = ref([39.8, -98.5]);

const CARRIER_COLORS = {
    BNSF: '#f97316',
    UP:   '#eab308',
    CSX:  '#3b82f6',
    NS:   '#22c55e',
    CN:   '#ef4444',
    CP:   '#a855f7',
    KCS:  '#14b8a6',
};

const carrierOptions = Object.keys(CARRIER_COLORS).map(k => ({ label: k, value: k }));

const statusOptions = [
    { label: 'Pending',       value: 'pending' },
    { label: 'Loaded',        value: 'loaded' },
    { label: 'In Transit',    value: 'in_transit' },
    { label: 'Arrived',       value: 'arrived' },
    { label: 'Available',     value: 'available' },
    { label: 'Picked Up',     value: 'picked_up' },
];

function carrierColor(scac) {
    return CARRIER_COLORS[scac] || '#94a3b8';
}

function formatDate(d) {
    return d ? dayjs(d).format('MMM D, YYYY') : '—';
}

// Ramps that have lat/lng
const rampsWithCoords = computed(() =>
    railStore.ramps.filter(r => r.latitude && r.longitude),
);

// How many containers are at a given ramp code
function rampContainerCount(code) {
    return railStore.shipments.filter(
        s => (s.destination_ramp_code === code && s.status === 'arrived') ||
             (s.origin_ramp_code === code && s.status === 'pending'),
    ).length;
}

// Build route lines from shipments that have origin + destination coords
const activeShipmentRoutes = computed(() => {
    return railStore.shipments
        .filter(s => ['in_transit', 'loaded', 'arrived'].includes(s.status))
        .map(s => {
            const origin = findRampCoords(s.origin_ramp_code);
            const dest   = findRampCoords(s.destination_ramp_code);
            if (!origin || !dest) return null;

            // Estimate current position based on % of transit time elapsed
            let currentPos = null;
            if (s.departed_at && s.eta && s.status === 'in_transit') {
                const total   = dayjs(s.eta).diff(dayjs(s.departed_at), 'minute');
                const elapsed = dayjs().diff(dayjs(s.departed_at), 'minute');
                const pct     = Math.min(Math.max(elapsed / total, 0), 1);
                currentPos = [
                    origin[0] + (dest[0] - origin[0]) * pct,
                    origin[1] + (dest[1] - origin[1]) * pct,
                ];
            }

            return {
                ...s,
                latlngs: [origin, dest],
                currentPos,
            };
        })
        .filter(Boolean);
});

function findRampCoords(code) {
    const ramp = railStore.ramps.find(r => r.code === code);
    return ramp?.latitude && ramp?.longitude ? [ramp.latitude, ramp.longitude] : null;
}

// Sidebar filtered shipments
const filteredShipments = computed(() => {
    let list = railStore.shipments;
    if (sidebarCarrier.value) {
        list = list.filter(s => s.rail_carrier === sidebarCarrier.value);
    }
    if (sidebarStatus.value) {
        list = list.filter(s => s.status === sidebarStatus.value);
    }
    if (sidebarSearch.value) {
        const q = sidebarSearch.value.toLowerCase();
        list = list.filter(s =>
            s.container_number?.toLowerCase().includes(q) ||
            s.train_id?.toLowerCase().includes(q),
        );
    }
    return list;
});

function focusShipment(shipment) {
    selectedShipmentUuid.value = shipment.uuid;
    const origin = findRampCoords(shipment.origin_ramp_code);
    const dest   = findRampCoords(shipment.destination_ramp_code);
    if (!origin || !dest) return;

    const centerLat = (origin[0] + dest[0]) / 2;
    const centerLng = (origin[1] + dest[1]) / 2;
    mapCenter.value = [centerLat, centerLng];
    mapZoom.value = 6;
}

function onMapReady() {
    // Map is ready — markers will be placed reactively
}

async function onShipmentCreated() {
    showCreateDialog.value = false;
    await railStore.fetchShipments();
}

onMounted(async () => {
    await Promise.allSettled([
        railStore.fetchRamps(),
        railStore.fetchShipments(),
    ]);
});
</script>

<style>
.leaflet-popup-content-wrapper {
    border-radius: 10px !important;
    box-shadow: 0 4px 24px rgba(0,0,0,0.18) !important;
}
.leaflet-popup-tip {
    box-shadow: none !important;
}
</style>
