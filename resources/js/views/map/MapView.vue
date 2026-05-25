<template>
    <div class="flex flex-col h-full -m-6">
        <!-- Filter panel -->
        <div class="bg-white border-b border-gray-200 px-6 py-3 flex items-center gap-4 flex-shrink-0 z-10">
            <h1 class="font-bold text-gray-900 text-lg">Global Map</h1>
            <div class="flex items-center gap-3 ml-4">
                <Select
                    v-model="mapFilters.status"
                    :options="statusOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="All Statuses"
                    size="small"
                    class="w-36"
                    show-clear
                    @change="applyFilters"
                />
                <Select
                    v-model="mapFilters.carrier"
                    :options="[]"
                    placeholder="All Carriers"
                    size="small"
                    class="w-36"
                    show-clear
                    @change="applyFilters"
                />
                <div class="flex items-center gap-4 ml-4 text-xs text-gray-500">
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-blue-500 inline-block"></span> Vessels ({{ vesselCount }})</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-orange-500 inline-block"></span> Terminals ({{ terminalCount }})</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-green-500 inline-block"></span> Ports ({{ portCount }})</span>
                </div>
            </div>
        </div>

        <!-- Map container -->
        <div class="flex-1 relative">
            <l-map
                ref="mapRef"
                :zoom="3"
                :center="[20, 0]"
                class="w-full h-full"
                :options="{ zoomControl: true }"
            >
                <l-tile-layer
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                    attribution="&copy; OpenStreetMap contributors"
                />

                <!-- Vessel markers -->
                <l-marker
                    v-for="vessel in displayVessels"
                    :key="vessel.uuid"
                    :lat-lng="[vessel.latitude, vessel.longitude]"
                    :icon="vesselIcon"
                >
                    <l-popup class="min-w-48">
                        <div class="p-1">
                            <p class="font-bold text-gray-900">{{ vessel.name }}</p>
                            <p class="text-xs text-gray-500">{{ vessel.carrier_name }}</p>
                            <div class="mt-2 space-y-1 text-xs">
                                <div class="flex justify-between"><span class="text-gray-500">Voyage</span><span>{{ vessel.voyage_number }}</span></div>
                                <div class="flex justify-between"><span class="text-gray-500">Destination</span><span>{{ vessel.arrival_port }}</span></div>
                                <div class="flex justify-between"><span class="text-gray-500">ETA</span><span>{{ formatDate(vessel.eta) }}</span></div>
                                <div class="flex justify-between"><span class="text-gray-500">Speed</span><span>{{ vessel.speed || '—' }} kn</span></div>
                                <div class="flex justify-between"><span class="text-gray-500">Containers</span><span class="font-semibold text-blue-600">{{ vessel.container_count }}</span></div>
                            </div>
                            <router-link
                                :to="{ name: 'vessel-detail', params: { uuid: vessel.uuid } }"
                                class="mt-2 block text-center text-xs bg-blue-600 text-white py-1 px-2 rounded hover:bg-blue-700"
                            >
                                View Vessel
                            </router-link>
                        </div>
                    </l-popup>
                </l-marker>
            </l-map>

            <!-- Loading overlay -->
            <div v-if="loading" class="absolute inset-0 bg-white/60 flex items-center justify-center z-20">
                <ProgressSpinner />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { LMap, LTileLayer, LMarker, LPopup } from '@vue-leaflet/vue-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import Select from 'primevue/select';
import ProgressSpinner from 'primevue/progressspinner';
import dayjs from 'dayjs';
import { useVesselsStore } from '@/stores/vessels';
import api from '@/plugins/api';

const vesselsStore = useVesselsStore();
const loading = ref(false);
const mapRef = ref(null);
const ports = ref([]);

const mapFilters = ref({ status: null, carrier: null });

const statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'In Transit', value: 'in_transit' },
    { label: 'At Terminal', value: 'at_terminal' },
];

const displayVessels = computed(() =>
    vesselsStore.vessels.filter(v => v.latitude && v.longitude),
);

const vesselCount = computed(() => displayVessels.value.length);
const terminalCount = computed(() => 0);
const portCount = computed(() => ports.value.length);

const vesselIcon = L.divIcon({
    html: '<div style="background:#3b82f6;width:12px;height:12px;border-radius:50%;border:2px solid white;box-shadow:0 1px 3px rgba(0,0,0,0.4)"></div>',
    iconSize: [12, 12],
    className: '',
});

function formatDate(d) { return d ? dayjs(d).format('MMM D') : '—'; }

async function applyFilters() {
    await load();
}

async function load() {
    loading.value = true;
    try {
        await vesselsStore.fetchVessels(mapFilters.value);
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>
