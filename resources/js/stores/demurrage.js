import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/plugins/api';

export const useDemurrageStore = defineStore('demurrage', () => {
    const demurrageCharges = ref([]);
    const detentionCharges = ref([]);
    const alarms = ref([]);
    const loading = ref(false);
    const pagination = ref({
        current_page: 1,
        per_page: 25,
        total: 0,
        last_page: 1,
    });

    async function fetchDemurrage(params = {}) {
        loading.value = true;
        try {
            const response = await api.get('/demurrage', { params });
            demurrageCharges.value = response.data.data || response.data;
            if (response.data.meta) pagination.value = response.data.meta;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function fetchDetention(params = {}) {
        loading.value = true;
        try {
            const response = await api.get('/detention', { params });
            detentionCharges.value = response.data.data || response.data;
            if (response.data.meta) pagination.value = response.data.meta;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function calculateDemurrage(data) {
        const response = await api.post('/demurrage/calculate', data);
        return response.data;
    }

    async function fetchAlarms(params = {}) {
        loading.value = true;
        try {
            const response = await api.get('/demurrage/alarms', { params });
            alarms.value = response.data.data || response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function acknowledgeAlarm(uuid) {
        const response = await api.post(`/demurrage/alarms/${uuid}/acknowledge`);
        const idx = alarms.value.findIndex(a => a.uuid === uuid);
        if (idx !== -1) alarms.value[idx] = response.data;
        return response.data;
    }

    return {
        demurrageCharges,
        detentionCharges,
        alarms,
        loading,
        pagination,
        fetchDemurrage,
        fetchDetention,
        calculateDemurrage,
        fetchAlarms,
        acknowledgeAlarm,
    };
});
