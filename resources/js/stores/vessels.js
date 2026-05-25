import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/plugins/api';

export const useVesselsStore = defineStore('vessels', () => {
    const vessels = ref([]);
    const currentVessel = ref(null);
    const schedule = ref([]);
    const loading = ref(false);

    async function fetchVessels(params = {}) {
        loading.value = true;
        try {
            const response = await api.get('/vessels', { params });
            vessels.value = response.data.data || response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function fetchVessel(uuid) {
        loading.value = true;
        try {
            const response = await api.get(`/vessels/${uuid}`);
            currentVessel.value = response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function updateVessel(uuid, data) {
        const response = await api.put(`/vessels/${uuid}`, data);
        const idx = vessels.value.findIndex(v => v.uuid === uuid);
        if (idx !== -1) vessels.value[idx] = response.data;
        if (currentVessel.value?.uuid === uuid) currentVessel.value = response.data;
        return response.data;
    }

    async function fetchSchedule(uuid) {
        loading.value = true;
        try {
            const response = await api.get(`/vessels/${uuid}/schedule`);
            schedule.value = response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    return {
        vessels,
        currentVessel,
        schedule,
        loading,
        fetchVessels,
        fetchVessel,
        updateVessel,
        fetchSchedule,
    };
});
