<template>
    <div>
        <PageHeader title="Master Bills of Lading">
            <template #actions>
                <DataExport @export="() => {}" />
            </template>
        </PageHeader>
        <DataTable :value="items" :loading="loading" data-key="uuid" striped-rows paginator :rows="25" class="text-sm">
            <Column field="mbl_number" header="MBL Number" sortable>
                <template #body="{ data }">
                    <router-link :to="{ name: 'mbl-detail', params: { uuid: data.uuid } }" class="font-mono font-semibold text-blue-600 hover:text-blue-800">{{ data.mbl_number }}</router-link>
                </template>
            </Column>
            <Column field="carrier_name" header="Carrier" sortable />
            <Column field="vessel_name" header="Vessel" sortable />
            <Column field="voyage_number" header="Voyage" />
            <Column field="pol_name" header="POL" sortable />
            <Column field="pod_name" header="POD" sortable />
            <Column field="etd" header="ETD" sortable>
                <template #body="{ data }"><span class="text-xs">{{ formatDate(data.etd) }}</span></template>
            </Column>
            <Column field="eta" header="ETA" sortable>
                <template #body="{ data }"><span class="text-xs">{{ formatDate(data.eta) }}</span></template>
            </Column>
            <Column field="container_count" header="Containers">
                <template #body="{ data }"><span class="font-semibold text-blue-600">{{ data.container_count || 0 }}</span></template>
            </Column>
            <template #empty>
                <div class="py-10 text-center text-gray-400">
                    <i class="pi pi-file text-3xl mb-2 block"></i>
                    <p>No MBLs found</p>
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
import DataExport from '@/components/DataExport.vue';
import api from '@/plugins/api';

const items = ref([]);
const loading = ref(false);
function formatDate(d) { return d ? dayjs(d).format('MMM D, YYYY') : '—'; }
onMounted(async () => {
    loading.value = true;
    try { const r = await api.get('/mbls'); items.value = r.data.data || r.data; }
    catch { items.value = []; }
    finally { loading.value = false; }
});
</script>
