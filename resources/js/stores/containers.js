import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/plugins/api';

export const useContainersStore = defineStore('containers', () => {
    const containers = ref([]);
    const currentContainer = ref(null);
    const loading = ref(false);
    const filters = ref({
        status: null,
        carrier: null,
        search: null,
        pol: null,
        pod: null,
        eta_from: null,
        eta_to: null,
    });
    const pagination = ref({
        current_page: 1,
        per_page: 25,
        total: 0,
        last_page: 1,
    });

    const containersByStatus = computed(() => {
        return containers.value.reduce((acc, c) => {
            const status = c.status || 'unknown';
            if (!acc[status]) acc[status] = [];
            acc[status].push(c);
            return acc;
        }, {});
    });

    const totalContainers = computed(() => pagination.value.total);

    async function fetchContainers(params = {}) {
        loading.value = true;
        try {
            const activeFilters = Object.fromEntries(
                Object.entries(filters.value).filter(([, v]) => v !== null && v !== ''),
            );
            const response = await api.get('/containers', {
                params: { ...activeFilters, ...params },
            });
            containers.value = response.data.data || response.data;
            if (response.data.meta) {
                pagination.value = response.data.meta;
            }
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function fetchContainer(uuid) {
        loading.value = true;
        try {
            const response = await api.get(`/containers/${uuid}`);
            currentContainer.value = response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function createContainer(data) {
        const response = await api.post('/containers', data);
        containers.value.unshift(response.data);
        return response.data;
    }

    async function updateContainer(uuid, data) {
        const response = await api.put(`/containers/${uuid}`, data);
        const idx = containers.value.findIndex(c => c.uuid === uuid);
        if (idx !== -1) containers.value[idx] = response.data;
        if (currentContainer.value?.uuid === uuid) currentContainer.value = response.data;
        return response.data;
    }

    async function deleteContainer(uuid) {
        await api.delete(`/containers/${uuid}`);
        containers.value = containers.value.filter(c => c.uuid !== uuid);
        if (currentContainer.value?.uuid === uuid) currentContainer.value = null;
    }

    function filterContainers(newFilters) {
        filters.value = { ...filters.value, ...newFilters };
    }

    async function fetchActiveContainers() {
        filterContainers({ status: 'active' });
        return fetchContainers();
    }

    async function fetchNotTrackingContainers() {
        filterContainers({ status: 'not_tracking' });
        return fetchContainers();
    }

    function resetFilters() {
        filters.value = {
            status: null,
            carrier: null,
            search: null,
            pol: null,
            pod: null,
            eta_from: null,
            eta_to: null,
        };
    }

    return {
        containers,
        currentContainer,
        loading,
        filters,
        pagination,
        containersByStatus,
        totalContainers,
        fetchContainers,
        fetchContainer,
        createContainer,
        updateContainer,
        deleteContainer,
        filterContainers,
        fetchActiveContainers,
        fetchNotTrackingContainers,
        resetFilters,
    };
});
