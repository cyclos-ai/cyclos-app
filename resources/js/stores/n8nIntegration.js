import { ref, computed } from 'vue';
import { defineStore } from 'pinia';
import api from '@/plugins/api';

export const useN8nIntegrationStore = defineStore('n8nIntegration', () => {
    const status = ref(null);
    const workflows = ref([]);
    const templates = ref([]);
    const executions = ref([]);
    const loading = ref(false);

    const isConnected = computed(() => status.value?.connected === true);
    const isConfigured = computed(() => status.value?.configured === true);

    async function fetchStatus() {
        loading.value = true;
        try {
            const { data } = await api.get('/n8n');
            status.value = data.data;
        } catch {
            status.value = null;
        } finally {
            loading.value = false;
        }
    }

    async function connect(payload) {
        const { data } = await api.post('/n8n/connect', payload);
        await fetchStatus();
        return data;
    }

    async function disconnect() {
        await api.post('/n8n/disconnect');
        status.value = { connected: false, configured: false };
        workflows.value = [];
    }

    async function healthCheck() {
        const { data } = await api.post('/n8n/health');
        if (status.value) {
            status.value.last_health_status = data.data.status;
        }
        return data.data;
    }

    async function fetchWorkflows() {
        const { data } = await api.get('/n8n/workflows');
        workflows.value = data.data || [];
        return workflows.value;
    }

    async function syncWorkflows() {
        const { data } = await api.post('/n8n/workflows/sync');
        return data;
    }

    async function fetchTemplates() {
        const { data } = await api.get('/n8n/templates');
        templates.value = data.data || [];
        return templates.value;
    }

    async function deployTemplate(key) {
        const { data } = await api.post(`/n8n/templates/${key}/deploy`);
        await fetchTemplates();
        return data;
    }

    async function updateMapping(id, payload) {
        const { data } = await api.put(`/n8n/workflow-mappings/${id}`, payload);
        return data;
    }

    async function deleteMapping(id, deleteFromN8n = false) {
        await api.delete(`/n8n/workflow-mappings/${id}`, {
            params: { delete_from_n8n: deleteFromN8n },
        });
    }

    async function fetchExecutions(workflowId = null, limit = 20) {
        const params = { limit };
        if (workflowId) params.workflow_id = workflowId;
        const { data } = await api.get('/n8n/executions', { params });
        executions.value = data.data || [];
        return executions.value;
    }

    return {
        status,
        workflows,
        templates,
        executions,
        loading,
        isConnected,
        isConfigured,
        fetchStatus,
        connect,
        disconnect,
        healthCheck,
        fetchWorkflows,
        syncWorkflows,
        fetchTemplates,
        deployTemplate,
        updateMapping,
        deleteMapping,
        fetchExecutions,
    };
});
