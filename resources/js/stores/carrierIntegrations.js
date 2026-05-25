import { ref } from 'vue';
import { defineStore } from 'pinia';
import api from '@/plugins/api';

export const useCarrierIntegrationsStore = defineStore('carrierIntegrations', () => {
    const carriers = ref([]);
    const loading = ref(false);

    async function fetchCarriers() {
        loading.value = true;
        try {
            const { data } = await api.get('/carrier-integrations');
            carriers.value = data.data || data;
        } finally {
            loading.value = false;
        }
    }

    async function saveCredentials(payload) {
        const { data } = await api.post('/carrier-integrations', payload);
        await fetchCarriers();
        return data;
    }

    async function testConnection(scac) {
        const { data } = await api.post(`/carrier-integrations/${scac}/test`);
        return data.data || data;
    }

    async function disconnect(scac) {
        await api.delete(`/carrier-integrations/${scac}`);
        await fetchCarriers();
    }

    async function toggleCarrier(scac) {
        const { data } = await api.post(`/carrier-integrations/${scac}/toggle`);
        await fetchCarriers();
        return data;
    }

    return { carriers, loading, fetchCarriers, saveCredentials, testConnection, disconnect, toggleCarrier };
});
