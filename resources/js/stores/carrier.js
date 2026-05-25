import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/plugins/api';

export const useCarrierStore = defineStore('carrier', () => {
    const assignments = ref([]);
    const dashboardStats = ref({ pending_pickup: 0, in_transit: 0, delivered_today: 0, total_assigned: 0 });
    const invoices = ref([]);
    const loading = ref(false);

    async function fetchAssignments(params = {}) {
        loading.value = true;
        try {
            const response = await api.get('/carrier/assignments', { params });
            assignments.value = response.data.data || response.data;
            return assignments.value;
        } catch (err) {
            console.error('Failed to fetch assignments', err);
            return [];
        } finally {
            loading.value = false;
        }
    }

    async function fetchDashboardStats() {
        try {
            const response = await api.get('/carrier/dashboard/stats');
            dashboardStats.value = response.data;
        } catch (err) {
            console.error('Failed to fetch dashboard stats', err);
        }
    }

    async function fetchInvoices(params = {}) {
        try {
            const response = await api.get('/carrier/invoices', { params });
            invoices.value = response.data.data || response.data;
        } catch (err) {
            console.error('Failed to fetch invoices', err);
        }
    }

    async function submitInvoice(data) {
        const response = await api.post('/carrier/invoices', data);
        return response.data;
    }

    return { assignments, dashboardStats, invoices, loading, fetchAssignments, fetchDashboardStats, fetchInvoices, submitInvoice };
});
