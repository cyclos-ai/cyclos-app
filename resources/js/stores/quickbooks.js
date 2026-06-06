import { ref, computed } from 'vue';
import { defineStore } from 'pinia';
import api from '@/plugins/api';

export const useQuickBooksStore = defineStore('quickbooks', () => {
    const status = ref(null);
    const loading = ref(false);
    const connecting = ref(false);

    const isConnected = computed(() => status.value?.is_connected === true);
    const isConfigured = computed(() => status.value?.is_configured === true);

    async function fetchStatus() {
        loading.value = true;
        try {
            const { data } = await api.get('/integrations/quickbooks');
            status.value = data.data;
        } catch {
            status.value = null;
        } finally {
            loading.value = false;
        }
    }

    async function connect() {
        connecting.value = true;
        try {
            const { data } = await api.get('/integrations/quickbooks/connect');
            window.location.href = data.data.authorization_url;
        } finally {
            connecting.value = false;
        }
    }

    async function disconnect() {
        await api.post('/integrations/quickbooks/disconnect');
        status.value = { is_configured: status.value?.is_configured ?? false, is_connected: false };
    }

    async function pushInvoice(type, uuid) {
        const { data } = await api.post(`/integrations/quickbooks/invoices/${type}/${uuid}/push`);
        return data.data;
    }

    async function syncInvoiceStatus(type, uuid) {
        const { data } = await api.post(`/integrations/quickbooks/invoices/${type}/${uuid}/sync-status`);
        return data.data;
    }

    return {
        status,
        loading,
        connecting,
        isConnected,
        isConfigured,
        fetchStatus,
        connect,
        disconnect,
        pushInvoice,
        syncInvoiceStatus,
    };
});
