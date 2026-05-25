import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/plugins/api';

export const useSettingsStore = defineStore('settings', () => {
    const customColumns = ref([]);
    const webhooks = ref([]);
    const carrierContracts = ref([]);
    const loading = ref(false);

    // Custom Columns
    async function fetchCustomColumns() {
        loading.value = true;
        try {
            const response = await api.get('/settings/custom-columns');
            customColumns.value = response.data.data || response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function createCustomColumn(data) {
        const response = await api.post('/settings/custom-columns', data);
        customColumns.value.push(response.data);
        return response.data;
    }

    async function updateCustomColumn(uuid, data) {
        const response = await api.put(`/settings/custom-columns/${uuid}`, data);
        const idx = customColumns.value.findIndex(c => c.uuid === uuid);
        if (idx !== -1) customColumns.value[idx] = response.data;
        return response.data;
    }

    async function deleteCustomColumn(uuid) {
        await api.delete(`/settings/custom-columns/${uuid}`);
        customColumns.value = customColumns.value.filter(c => c.uuid !== uuid);
    }

    // Webhooks
    async function fetchWebhooks() {
        loading.value = true;
        try {
            const response = await api.get('/settings/webhooks');
            webhooks.value = response.data.data || response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function createWebhook(data) {
        const response = await api.post('/settings/webhooks', data);
        webhooks.value.push(response.data);
        return response.data;
    }

    async function updateWebhook(uuid, data) {
        const response = await api.put(`/settings/webhooks/${uuid}`, data);
        const idx = webhooks.value.findIndex(w => w.uuid === uuid);
        if (idx !== -1) webhooks.value[idx] = response.data;
        return response.data;
    }

    async function deleteWebhook(uuid) {
        await api.delete(`/settings/webhooks/${uuid}`);
        webhooks.value = webhooks.value.filter(w => w.uuid !== uuid);
    }

    async function testWebhook(uuid) {
        const response = await api.post(`/settings/webhooks/${uuid}/test`);
        return response.data;
    }

    async function fetchWebhookLogs(uuid) {
        const response = await api.get(`/settings/webhooks/${uuid}/logs`);
        return response.data;
    }

    // Carrier Contracts
    async function fetchCarrierContracts() {
        loading.value = true;
        try {
            const response = await api.get('/settings/carrier-contracts');
            carrierContracts.value = response.data.data || response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function createCarrierContract(data) {
        const response = await api.post('/settings/carrier-contracts', data);
        carrierContracts.value.push(response.data);
        return response.data;
    }

    async function updateCarrierContract(uuid, data) {
        const response = await api.put(`/settings/carrier-contracts/${uuid}`, data);
        const idx = carrierContracts.value.findIndex(c => c.uuid === uuid);
        if (idx !== -1) carrierContracts.value[idx] = response.data;
        return response.data;
    }

    async function deleteCarrierContract(uuid) {
        await api.delete(`/settings/carrier-contracts/${uuid}`);
        carrierContracts.value = carrierContracts.value.filter(c => c.uuid !== uuid);
    }

    return {
        customColumns,
        webhooks,
        carrierContracts,
        loading,
        fetchCustomColumns,
        createCustomColumn,
        updateCustomColumn,
        deleteCustomColumn,
        fetchWebhooks,
        createWebhook,
        updateWebhook,
        deleteWebhook,
        testWebhook,
        fetchWebhookLogs,
        fetchCarrierContracts,
        createCarrierContract,
        updateCarrierContract,
        deleteCarrierContract,
    };
});
