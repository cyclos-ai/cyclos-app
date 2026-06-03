import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/plugins/api';

export const useDocumentExtractionStore = defineStore('documentExtraction', () => {
    const configured = ref(false);
    const loading = ref(false);
    const error = ref(null);
    const lastResult = ref(null);

    async function checkStatus() {
        try {
            const response = await api.get('/documents/extract/status');
            configured.value = response.data?.data?.configured ?? false;
            return configured.value;
        } catch (err) {
            console.error('DocumentExtraction status check failed:', err);
            configured.value = false;
            return false;
        }
    }

    async function extractFromFile(file) {
        loading.value = true;
        error.value = null;

        try {
            const formData = new FormData();
            formData.append('file', file);

            const response = await api.post('/documents/extract', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            const data = response.data?.data ?? response.data;
            lastResult.value = data;
            return data;
        } catch (err) {
            const message = err.response?.data?.message || 'Failed to extract document data.';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    return {
        configured,
        loading,
        error,
        lastResult,
        checkStatus,
        extractFromFile,
    };
});
