<template>
    <div v-if="mbl">
        <div class="flex items-center gap-4 mb-6">
            <button @click="$router.back()" class="text-gray-400 hover:text-gray-600"><i class="pi pi-arrow-left"></i></button>
            <h1 class="text-2xl font-bold font-mono text-gray-900">{{ mbl.mbl_number }}</h1>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-white border border-gray-200 rounded-xl p-5 text-sm space-y-2">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Shipment Details</p>
                <div class="flex justify-between"><span class="text-gray-500">Carrier</span><span class="font-medium">{{ mbl.carrier_name }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Vessel</span><span class="font-medium">{{ mbl.vessel_name }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Voyage</span><span class="font-medium">{{ mbl.voyage_number }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">POL</span><span class="font-medium">{{ mbl.pol_name || mbl.pol }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">POD</span><span class="font-medium">{{ mbl.pod_name || mbl.pod }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">ETD</span><span class="font-medium">{{ formatDate(mbl.etd) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">ETA</span><span class="font-medium">{{ formatDate(mbl.eta) }}</span></div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-5 text-sm space-y-2">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Cargo & Parties</p>
                <div class="flex justify-between"><span class="text-gray-500">Shipper</span><span class="font-medium">{{ mbl.shipper || '—' }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Consignee</span><span class="font-medium">{{ mbl.consignee || '—' }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Notify Party</span><span class="font-medium">{{ mbl.notify_party || '—' }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Containers</span><span class="font-semibold text-blue-600">{{ mbl.container_count || 0 }}</span></div>
            </div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 font-semibold text-gray-800">Containers on this MBL</div>
            <DataTable :value="mbl.containers || []" size="small" class="text-sm">
                <Column field="container_number" header="Container #">
                    <template #body="{ data }">
                        <router-link :to="{ name: 'container-detail', params: { uuid: data.uuid } }" class="font-mono font-semibold text-blue-600 hover:text-blue-800">{{ data.container_number }}</router-link>
                    </template>
                </Column>
                <Column field="status" header="Status"><template #body="{ data }"><StatusBadge :status="data.status" size="small" /></template></Column>
                <Column field="eta" header="ETA"><template #body="{ data }"><span class="text-xs">{{ formatDate(data.eta) }}</span></template></Column>
                <Column field="last_free_day" header="LFD"><template #body="{ data }"><span class="text-xs">{{ formatDate(data.last_free_day) }}</span></template></Column>
                <template #empty><div class="py-6 text-center text-gray-400 text-sm">No containers</div></template>
            </DataTable>
        </div>
    </div>
    <div v-else-if="loading" class="flex justify-center py-20"><ProgressSpinner /></div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import ProgressSpinner from 'primevue/progressspinner';
import dayjs from 'dayjs';
import StatusBadge from '@/components/StatusBadge.vue';
import api from '@/plugins/api';

const route = useRoute();
const mbl = ref(null);
const loading = ref(false);
function formatDate(d) { return d ? dayjs(d).format('MMM D, YYYY') : '—'; }
onMounted(async () => {
    loading.value = true;
    try { const r = await api.get(`/mbls/${route.params.uuid}`); mbl.value = r.data; }
    finally { loading.value = false; }
});
</script>
