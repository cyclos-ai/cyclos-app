import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/plugins/api';

export const useTrackingStore = defineStore('tracking', () => {
    const trackingRequests = ref([]);
    const loading = ref(false);
    const pagination = ref({
        current_page: 1,
        per_page: 25,
        total: 0,
        last_page: 1,
    });

    async function fetchTrackingRequests(params = {}) {
        loading.value = true;
        try {
            const response = await api.get('/tracking-requests', { params });
            trackingRequests.value = response.data.data || response.data;
            if (response.data.meta) {
                pagination.value = response.data.meta;
            }
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function createTrackingRequest(data) {
        loading.value = true;
        try {
            const response = await api.post('/tracking-requests', data);
            trackingRequests.value.unshift(response.data);
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function deleteTrackingRequest(uuid) {
        await api.delete(`/tracking-requests/${uuid}`);
        trackingRequests.value = trackingRequests.value.filter(r => r.uuid !== uuid);
    }

    async function retryTrackingRequest(uuid) {
        const response = await api.post(`/tracking-requests/${uuid}/retry`);
        const idx = trackingRequests.value.findIndex(r => r.uuid === uuid);
        if (idx !== -1) trackingRequests.value[idx] = response.data;
        return response.data;
    }

    return {
        trackingRequests,
        loading,
        pagination,
        fetchTrackingRequests,
        createTrackingRequest,
        deleteTrackingRequest,
        retryTrackingRequest,
    };
});
