import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/plugins/api';

export const useRailStore = defineStore('rail', () => {
    const ramps = ref([]);
    const shipments = ref([]);
    const loading = ref(false);
    const error = ref(null);
    const mapCenter = ref([39.8, -98.5]);
    const selectedRamp = ref(null);
    const selectedShipment = ref(null);

    const pagination = ref({
        current_page: 1,
        per_page: 25,
        total: 0,
        last_page: 1,
    });

    // Computed counts
    const inTransitCount = computed(
        () => shipments.value.filter(s => s.status === 'in_transit').length,
    );

    const atRampCount = computed(
        () => shipments.value.filter(s => s.status === 'arrived').length,
    );

    const pendingPickupCount = computed(
        () => shipments.value.filter(s => s.status === 'pending' || s.status === 'available').length,
    );

    // Group shipments by carrier SCAC
    const shipmentsByCarrier = computed(() => {
        return shipments.value.reduce((acc, s) => {
            const key = s.rail_carrier || 'UNKNOWN';
            if (!acc[key]) acc[key] = [];
            acc[key].push(s);
            return acc;
        }, {});
    });

    async function fetchRamps(filters = {}) {
        try {
            const response = await api.get('/rail/ramps', { params: filters });
            ramps.value = response.data.data || response.data;
            return response.data;
        } catch (e) {
            error.value = e?.response?.data?.message || 'Failed to load ramps';
            ramps.value = [];
        }
    }

    async function fetchShipments(filters = {}) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.get('/rail/shipments', { params: filters });
            shipments.value = response.data.data || response.data;
            if (response.data.meta) {
                pagination.value = response.data.meta;
            }
            return response.data;
        } catch (e) {
            error.value = e?.response?.data?.message || 'Failed to load shipments';
            shipments.value = [];
        } finally {
            loading.value = false;
        }
    }

    async function createShipment(data) {
        const response = await api.post('/rail/shipments', data);
        shipments.value.unshift(response.data);
        return response.data;
    }

    async function updateShipmentStatus(uuid, status) {
        const response = await api.patch(`/rail/shipments/${uuid}/status`, { status });
        const idx = shipments.value.findIndex(s => s.uuid === uuid);
        if (idx !== -1) shipments.value[idx] = response.data;
        if (selectedShipment.value?.uuid === uuid) selectedShipment.value = response.data;
        return response.data;
    }

    async function fetchShipmentDetail(uuid) {
        loading.value = true;
        try {
            const response = await api.get(`/rail/shipments/${uuid}`);
            selectedShipment.value = response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    return {
        ramps,
        shipments,
        loading,
        error,
        mapCenter,
        selectedRamp,
        selectedShipment,
        pagination,
        inTransitCount,
        atRampCount,
        pendingPickupCount,
        shipmentsByCarrier,
        fetchRamps,
        fetchShipments,
        createShipment,
        updateShipmentStatus,
        fetchShipmentDetail,
    };
});
