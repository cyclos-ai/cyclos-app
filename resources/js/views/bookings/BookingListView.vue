<template>
    <div>
        <PageHeader title="Bookings">
            <template #actions>
                <DataExport @export="() => {}" />
            </template>
        </PageHeader>
        <DataTable :value="items" :loading="loading" data-key="uuid" striped-rows paginator :rows="25" class="text-sm">
            <Column field="booking_number" header="Booking #" sortable>
                <template #body="{ data }">
                    <router-link :to="{ name: 'booking-detail', params: { uuid: data.uuid } }" class="font-mono font-semibold text-blue-600 hover:text-blue-800">{{ data.booking_number }}</router-link>
                </template>
            </Column>
            <Column field="carrier_name" header="Carrier" sortable />
            <Column field="status" header="Status" sortable>
                <template #body="{ data }"><StatusBadge :status="data.status" size="small" /></template>
            </Column>
            <Column field="pol_name" header="POL" />
            <Column field="pod_name" header="POD" />
            <Column field="cut_off_date" header="Cut-off" sortable>
                <template #body="{ data }"><span class="text-xs">{{ formatDate(data.cut_off_date) }}</span></template>
            </Column>
            <Column field="etd" header="ETD" sortable>
                <template #body="{ data }"><span class="text-xs">{{ formatDate(data.etd) }}</span></template>
            </Column>
            <Column field="container_count" header="Containers">
                <template #body="{ data }"><span class="font-semibold text-blue-600">{{ data.container_count || 0 }}</span></template>
            </Column>
            <template #empty>
                <div class="py-10 text-center text-gray-400">
                    <i class="pi pi-bookmark text-3xl mb-2 block"></i>
                    <p>No bookings found</p>
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
    try { const r = await api.get('/bookings'); items.value = r.data.data || r.data; }
    catch { items.value = []; }
    finally { loading.value = false; }
});
</script>
