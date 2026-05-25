import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/plugins/api';

export const useReportsStore = defineStore('reports', () => {
    const reports = ref([]);
    const currentReport = ref(null);
    const loading = ref(false);

    async function fetchReports(params = {}) {
        loading.value = true;
        try {
            const response = await api.get('/reports', { params });
            reports.value = response.data.data || response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function fetchReport(uuid) {
        loading.value = true;
        try {
            const response = await api.get(`/reports/${uuid}`);
            currentReport.value = response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function createReport(data) {
        const response = await api.post('/reports', data);
        reports.value.unshift(response.data);
        return response.data;
    }

    async function updateReport(uuid, data) {
        const response = await api.put(`/reports/${uuid}`, data);
        const idx = reports.value.findIndex(r => r.uuid === uuid);
        if (idx !== -1) reports.value[idx] = response.data;
        if (currentReport.value?.uuid === uuid) currentReport.value = response.data;
        return response.data;
    }

    async function deleteReport(uuid) {
        await api.delete(`/reports/${uuid}`);
        reports.value = reports.value.filter(r => r.uuid !== uuid);
        if (currentReport.value?.uuid === uuid) currentReport.value = null;
    }

    async function generateReport(uuid, params = {}) {
        loading.value = true;
        try {
            const response = await api.post(`/reports/${uuid}/generate`, params);
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function scheduleReport(uuid, scheduleData) {
        const response = await api.post(`/reports/${uuid}/schedule`, scheduleData);
        return response.data;
    }

    async function exportReport(uuid, format = 'csv') {
        const response = await api.get(`/reports/${uuid}/export`, {
            params: { format },
            responseType: 'blob',
        });
        return response.data;
    }

    return {
        reports,
        currentReport,
        loading,
        fetchReports,
        fetchReport,
        createReport,
        updateReport,
        deleteReport,
        generateReport,
        scheduleReport,
        exportReport,
    };
});
