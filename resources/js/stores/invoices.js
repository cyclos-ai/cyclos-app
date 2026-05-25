import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/plugins/api';

export const useInvoicesStore = defineStore('invoices', () => {
    const oceanInvoices = ref([]);
    const drayageInvoices = ref([]);
    const currentInvoice = ref(null);
    const loading = ref(false);
    const pagination = ref({
        current_page: 1,
        per_page: 25,
        total: 0,
        last_page: 1,
    });

    // Ocean invoices
    async function fetchOceanInvoices(params = {}) {
        loading.value = true;
        try {
            const response = await api.get('/ocean-invoices', { params });
            oceanInvoices.value = response.data.data || response.data;
            if (response.data.meta) pagination.value = response.data.meta;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function fetchOceanInvoice(uuid) {
        loading.value = true;
        try {
            const response = await api.get(`/ocean-invoices/${uuid}`);
            currentInvoice.value = response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function createOceanInvoice(data) {
        const response = await api.post('/ocean-invoices', data);
        oceanInvoices.value.unshift(response.data);
        return response.data;
    }

    async function updateOceanInvoice(uuid, data) {
        const response = await api.put(`/ocean-invoices/${uuid}`, data);
        _replaceInList(oceanInvoices, uuid, response.data);
        if (currentInvoice.value?.uuid === uuid) currentInvoice.value = response.data;
        return response.data;
    }

    async function deleteOceanInvoice(uuid) {
        await api.delete(`/ocean-invoices/${uuid}`);
        oceanInvoices.value = oceanInvoices.value.filter(i => i.uuid !== uuid);
    }

    async function updateOceanInvoiceStatus(uuid, status) {
        const response = await api.patch(`/ocean-invoices/${uuid}/status`, { status });
        _replaceInList(oceanInvoices, uuid, response.data);
        if (currentInvoice.value?.uuid === uuid) currentInvoice.value = response.data;
        return response.data;
    }

    // Drayage invoices
    async function fetchDrayageInvoices(params = {}) {
        loading.value = true;
        try {
            const response = await api.get('/drayage-invoices', { params });
            drayageInvoices.value = response.data.data || response.data;
            if (response.data.meta) pagination.value = response.data.meta;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function fetchDrayageInvoice(uuid) {
        loading.value = true;
        try {
            const response = await api.get(`/drayage-invoices/${uuid}`);
            currentInvoice.value = response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function createDrayageInvoice(data) {
        const response = await api.post('/drayage-invoices', data);
        drayageInvoices.value.unshift(response.data);
        return response.data;
    }

    async function updateDrayageInvoice(uuid, data) {
        const response = await api.put(`/drayage-invoices/${uuid}`, data);
        _replaceInList(drayageInvoices, uuid, response.data);
        if (currentInvoice.value?.uuid === uuid) currentInvoice.value = response.data;
        return response.data;
    }

    async function deleteDrayageInvoice(uuid) {
        await api.delete(`/drayage-invoices/${uuid}`);
        drayageInvoices.value = drayageInvoices.value.filter(i => i.uuid !== uuid);
    }

    function _replaceInList(list, uuid, newItem) {
        const idx = list.value.findIndex(i => i.uuid === uuid);
        if (idx !== -1) list.value[idx] = newItem;
    }

    return {
        oceanInvoices,
        drayageInvoices,
        currentInvoice,
        loading,
        pagination,
        fetchOceanInvoices,
        fetchOceanInvoice,
        createOceanInvoice,
        updateOceanInvoice,
        deleteOceanInvoice,
        updateOceanInvoiceStatus,
        fetchDrayageInvoices,
        fetchDrayageInvoice,
        createDrayageInvoice,
        updateDrayageInvoice,
        deleteDrayageInvoice,
    };
});
