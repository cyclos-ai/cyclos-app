<template>
    <div v-if="shipment">
        <div class="flex items-center gap-4 mb-6">
            <button @click="$router.back()" class="text-gray-400 hover:text-gray-600"><i class="pi pi-arrow-left"></i></button>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold font-mono text-gray-900">{{ shipment.awb_number }}</h1>
                <StatusBadge :status="shipment.status" />
            </div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-5 text-sm space-y-2 max-w-lg">
            <div class="flex justify-between"><span class="text-gray-500">Airline</span><span class="font-medium">{{ shipment.airline_name }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Flight Number</span><span class="font-medium">{{ shipment.flight_number || '—' }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Origin</span><span class="font-medium">{{ shipment.origin }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Destination</span><span class="font-medium">{{ shipment.destination }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">ETD</span><span class="font-medium">{{ formatDate(shipment.etd) }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">ETA</span><span class="font-medium">{{ formatDate(shipment.eta) }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Weight</span><span class="font-medium">{{ shipment.weight_kg?.toLocaleString() }} kg</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Pieces</span><span class="font-medium">{{ shipment.pieces }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Commodity</span><span class="font-medium">{{ shipment.commodity || '—' }}</span></div>
        </div>
    </div>
    <div v-else-if="loading" class="flex justify-center py-20"><ProgressSpinner /></div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import ProgressSpinner from 'primevue/progressspinner';
import dayjs from 'dayjs';
import StatusBadge from '@/components/StatusBadge.vue';
import api from '@/plugins/api';

const route = useRoute();
const shipment = ref(null);
const loading = ref(false);
function formatDate(d) { return d ? dayjs(d).format('MMM D, YYYY') : '—'; }
onMounted(async () => {
    loading.value = true;
    try { const r = await api.get(`/air-shipments/${route.params.uuid}`); shipment.value = r.data; }
    finally { loading.value = false; }
});
</script>
