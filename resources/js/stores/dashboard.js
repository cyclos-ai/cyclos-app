import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/plugins/api';

export const useDashboardStore = defineStore('dashboard', () => {
    const dashboards = ref([]);
    const currentDashboard = ref(null);
    const widgets = ref([]);
    const loading = ref(false);

    async function fetchDashboards() {
        loading.value = true;
        try {
            const response = await api.get('/dashboards');
            dashboards.value = response.data.data || response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function fetchDashboard(uuid) {
        loading.value = true;
        try {
            const response = await api.get(`/dashboards/${uuid}`);
            currentDashboard.value = response.data;
            widgets.value = response.data.widgets || [];
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function fetchDefaultDashboard() {
        loading.value = true;
        try {
            const response = await api.get('/dashboards/default');
            currentDashboard.value = response.data;
            widgets.value = response.data.widgets || [];
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function createDashboard(data) {
        const response = await api.post('/dashboards', data);
        dashboards.value.push(response.data);
        return response.data;
    }

    async function updateDashboard(uuid, data) {
        const response = await api.put(`/dashboards/${uuid}`, data);
        const idx = dashboards.value.findIndex(d => d.uuid === uuid);
        if (idx !== -1) dashboards.value[idx] = response.data;
        if (currentDashboard.value?.uuid === uuid) currentDashboard.value = response.data;
        return response.data;
    }

    async function deleteDashboard(uuid) {
        await api.delete(`/dashboards/${uuid}`);
        dashboards.value = dashboards.value.filter(d => d.uuid !== uuid);
        if (currentDashboard.value?.uuid === uuid) currentDashboard.value = null;
    }

    async function addWidget(dashboardUuid, widgetData) {
        const response = await api.post(`/dashboards/${dashboardUuid}/widgets`, widgetData);
        widgets.value.push(response.data);
        return response.data;
    }

    async function updateWidget(dashboardUuid, widgetUuid, data) {
        const response = await api.put(`/dashboards/${dashboardUuid}/widgets/${widgetUuid}`, data);
        const idx = widgets.value.findIndex(w => w.uuid === widgetUuid);
        if (idx !== -1) widgets.value[idx] = response.data;
        return response.data;
    }

    async function deleteWidget(dashboardUuid, widgetUuid) {
        await api.delete(`/dashboards/${dashboardUuid}/widgets/${widgetUuid}`);
        widgets.value = widgets.value.filter(w => w.uuid !== widgetUuid);
    }

    async function reorderWidgets(dashboardUuid, order) {
        const response = await api.put(`/dashboards/${dashboardUuid}/widgets/reorder`, { order });
        widgets.value = response.data;
        return response.data;
    }

    async function fetchDashboardStats() {
        const response = await api.get('/dashboard/stats');
        return response.data;
    }

    return {
        dashboards,
        currentDashboard,
        widgets,
        loading,
        fetchDashboards,
        fetchDashboard,
        fetchDefaultDashboard,
        createDashboard,
        updateDashboard,
        deleteDashboard,
        addWidget,
        updateWidget,
        deleteWidget,
        reorderWidgets,
        fetchDashboardStats,
    };
});
