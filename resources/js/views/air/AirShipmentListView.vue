<template>
    <div>
        <PageHeader title="Air Shipments">
            <template #actions>
                <DataExport @export="() => {}" />
            </template>
        </PageHeader>
        <DataTable :value="items" :loading="loading" data-key="uuid" striped-rows paginator :rows="25" class="text-sm">
            <Column field="awb_number" header="AWB Number" sortable>
                <template #body="{ data }">
                    <router-link :to="{ name: 'air-shipment-detail', params: { uuid: data.uuid } }" class="font-mono font-semibold text-blue-600 hover:text-blue-800">{{ data.awb_number }}</router-link>
                </template>
            </Column>
            <Column field="airline_name" header="Airline" sortable />
            <Column field="status" header="Status" sortable>
                <template #body="{ data }"><StatusBadge :status="data.status" size="small" /></template>
            </Column>
            <Column field="origin" header="Origin" />
            <Column field="destination" header="Destination" />
            <Column field="etd" header="ETD" sortable>
                <template #body="{ data }"><span class="text-xs">{{ formatDate(data.etd) }}</span></template>
            </Column>
            <Column field="eta" header="ETA" sortable>
                <template #body="{ data }"><span class="text-xs">{{ formatDate(data.eta) }}</span></template>
            </Column>
            <Column field="weight_kg" header="Weight (kg)">
                <template #body="{ data }"><span class="text-sm">{{ data.weight_kg?.toLocaleString() || '—' }}</span></template>
            </Column>
            <template #empty>
                <div class="py-10 text-center text-gray-400">
                    <i class="pi pi-send text-3xl mb-2 block"></i>
                    <p>No air shipments found</p>
                </div>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import dayjs from 'dayjs';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import DataExport from '@/components/DataExport.vue';
import api from '@/plugins/api';

const items = ref([]);
const loading = ref(false);
function formatDate(d) { return d ? dayjs(d).format('MMM D, YYYY') : '—'; }
onMounted(async () => {
    loading.value = true;
    try { const r = await api.get('/air-shipments'); items.value = r.data.data || r.data; }
    catch { items.value = []; }
    finally { loading.value = false; }
});
</script>
