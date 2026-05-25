<template>
    <div v-if="vessel">
        <div class="flex items-center gap-4 mb-6">
            <button @click="$router.back()" class="text-gray-400 hover:text-gray-600">
                <i class="pi pi-arrow-left"></i>
            </button>
            <div class="flex-1">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900">{{ vessel.name }}</h1>
                    <StatusBadge :status="vessel.status" />
                </div>
                <p class="text-sm text-gray-500 mt-1">
                    IMO: {{ vessel.imo_number || '—' }} &middot; {{ vessel.carrier_name || '—' }} &middot; Voyage {{ vessel.voyage_number || '—' }}
                </p>
            </div>
            <Button label="Edit" icon="pi pi-pencil" outlined size="small" />
        </div>

        <TabView v-model:active-index="activeTabIndex">
            <!-- Overview + map -->
            <TabPanel header="Overview">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 pt-4">
                    <!-- Vessel info -->
                    <div class="space-y-3">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Vessel Details</h3>
                        <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-2">
                            <DetailRow label="IMO Number" :value="vessel.imo_number" />
                            <DetailRow label="MMSI" :value="vessel.mmsi" />
                            <DetailRow label="Flag" :value="vessel.flag" />
                            <DetailRow label="Vessel Type" :value="vessel.vessel_type" />
                            <DetailRow label="Gross Tonnage" :value="vessel.gross_tonnage" />
                            <DetailRow label="Carrier" :value="vessel.carrier_name" />
                            <DetailRow label="Voyage Number" :value="vessel.voyage_number" />
                            <DetailRow label="Departure Port" :value="vessel.departure_port" />
                            <DetailRow label="Arrival Port" :value="vessel.arrival_port" />
                            <DetailRow label="ETD" :value="formatDate(vessel.etd)" />
                            <DetailRow label="ETA" :value="formatDate(vessel.eta)" />
                            <DetailRow label="ATA" :value="formatDate(vessel.ata)" />
                        </div>
                    </div>

                    <!-- Map -->
                    <div class="lg:col-span-2">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Current Position</h3>
                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden" style="height: 300px;">
                            <div v-if="vessel.latitude && vessel.longitude" class="w-full h-full">
                                <l-map
                                    :zoom="4"
                                    :center="[vessel.latitude, vessel.longitude]"
                                    class="w-full h-full"
                                >
                                    <l-tile-layer
                                        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                                        attribution="&copy; OpenStreetMap contributors"
                                    />
                                    <l-marker :lat-lng="[vessel.latitude, vessel.longitude]">
                                        <l-popup>
                                            <strong>{{ vessel.name }}</strong><br />
                                            Speed: {{ vessel.speed || '—' }} knots<br />
                                            Last update: {{ formatDate(vessel.position_updated_at) }}
                                        </l-popup>
                                    </l-marker>
                                </l-map>
                            </div>
                            <div v-else class="w-full h-full flex items-center justify-center text-gray-400">
                                <div class="text-center">
                                    <i class="pi pi-map text-3xl mb-2 block"></i>
                                    <p class="text-sm">Position not available</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </TabPanel>

            <!-- Containers -->
            <TabPanel :header="`Containers (${vessel.container_count || 0})`">
                <div class="pt-4">
                    <DataTable
                        :value="vessel.containers || []"
                        :loading="loading"
                        data-key="uuid"
                        striped-rows
                        size="small"
                        class="text-sm"
                    >
                        <Column field="container_number" header="Container #">
                            <template #body="{ data }">
                                <router-link
                                    :to="{ name: 'container-detail', params: { uuid: data.uuid } }"
                                    class="font-mono font-semibold text-blue-600 hover:text-blue-800"
                                >
                                    {{ data.container_number }}
                                </router-link>
                            </template>
                        </Column>
                        <Column field="status" header="Status">
                            <template #body="{ data }"><StatusBadge :status="data.status" size="small" /></template>
                        </Column>
                        <Column field="pod_name" header="Destination" />
                        <Column field="last_free_day" header="LFD">
                            <template #body="{ data }">
                                <span class="text-xs">{{ formatDate(data.last_free_day) }}</span>
                            </template>
                        </Column>
                        <template #empty>
                            <div class="py-8 text-center text-gray-400 text-sm">No containers on this vessel</div>
                        </template>
                    </DataTable>
                </div>
            </TabPanel>

            <!-- Schedule -->
            <TabPanel header="Schedule">
                <div class="pt-4">
                    <div v-if="schedule.length" class="space-y-3">
                        <div
                            v-for="stop in schedule"
                            :key="stop.uuid"
                            class="flex items-center gap-4 p-3 bg-white border border-gray-200 rounded-lg"
                        >
                            <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center flex-shrink-0">
                                <i class="pi pi-map-marker text-blue-600 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-gray-900">{{ stop.port_name }}</p>
                                <p class="text-xs text-gray-500">{{ stop.port_code }}</p>
                            </div>
                            <div class="text-right text-sm">
                                <p class="text-gray-600">ETD: {{ formatDate(stop.etd) }}</p>
                                <p class="text-gray-600">ETA: {{ formatDate(stop.eta) }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-10 text-gray-400">
                        <i class="pi pi-calendar text-3xl mb-2 block"></i>
                        <p class="text-sm">Schedule not available</p>
                    </div>
                </div>
            </TabPanel>
        </TabView>
    </div>

    <div v-else-if="loading" class="flex justify-center py-20">
        <ProgressSpinner />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import Button from 'primevue/button';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import ProgressSpinner from 'primevue/progressspinner';
import { LMap, LTileLayer, LMarker, LPopup } from '@vue-leaflet/vue-leaflet';
import 'leaflet/dist/leaflet.css';
import dayjs from 'dayjs';
import StatusBadge from '@/components/StatusBadge.vue';
import { useVesselsStore } from '@/stores/vessels';

const DetailRow = {
    props: ['label', 'value'],
    template: `<div class="flex justify-between py-1 border-b border-gray-50 last:border-0">
        <span class="text-xs text-gray-500">{{ label }}</span>
        <span class="text-sm font-medium text-gray-800">{{ value || '—' }}</span>
    </div>`,
};

const route = useRoute();
const vesselsStore = useVesselsStore();
const loading = ref(false);
const activeTabIndex = ref(0);

const vessel = computed(() => vesselsStore.currentVessel);
const schedule = computed(() => vesselsStore.schedule);

const props = defineProps({ defaultTab: { type: String, default: null } });

function formatDate(d) { return d ? dayjs(d).format('MMM D, YYYY') : '—'; }

onMounted(async () => {
    loading.value = true;
    try {
        await Promise.all([
            vesselsStore.fetchVessel(route.params.uuid),
            vesselsStore.fetchSchedule(route.params.uuid),
        ]);
        if (props.defaultTab === 'schedule') activeTabIndex.value = 2;
    } finally {
        loading.value = false;
    }
});
</script>
