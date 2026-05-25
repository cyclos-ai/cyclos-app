<template>
    <div v-if="booking">
        <div class="flex items-center gap-4 mb-6">
            <button @click="$router.back()" class="text-gray-400 hover:text-gray-600"><i class="pi pi-arrow-left"></i></button>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold font-mono text-gray-900">{{ booking.booking_number }}</h1>
                <StatusBadge :status="booking.status" />
            </div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-5 text-sm space-y-2 max-w-lg">
            <div class="flex justify-between"><span class="text-gray-500">Carrier</span><span class="font-medium">{{ booking.carrier_name }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">POL</span><span class="font-medium">{{ booking.pol_name || booking.pol }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">POD</span><span class="font-medium">{{ booking.pod_name || booking.pod }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Cut-off Date</span><span class="font-medium">{{ formatDate(booking.cut_off_date) }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">ETD</span><span class="font-medium">{{ formatDate(booking.etd) }}</span></div>
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
const booking = ref(null);
const loading = ref(false);
function formatDate(d) { return d ? dayjs(d).format('MMM D, YYYY') : '—'; }
onMounted(async () => {
    loading.value = true;
    try { const r = await api.get(`/bookings/${route.params.uuid}`); booking.value = r.data; }
    finally { loading.value = false; }
});
</script>
