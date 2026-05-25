import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/plugins/api';

export const useAdminStore = defineStore('admin', () => {
    const pendingRegistrations = ref([]);
    const loading = ref(false);

    const pendingCount = computed(() => pendingRegistrations.value.length);

    async function fetchPendingRegistrations() {
        loading.value = true;
        try {
            const response = await api.get('/admin/registrations', { params: { status: 'pending' } });
            pendingRegistrations.value = response.data.data || response.data;
        } catch (err) {
            console.error('Failed to fetch registrations', err);
        } finally {
            loading.value = false;
        }
    }

    async function approveRegistration(uuid) {
        const response = await api.post(`/admin/registrations/${uuid}/approve`);
        pendingRegistrations.value = pendingRegistrations.value.filter(r => r.uuid !== uuid);
        return response.data;
    }

    async function rejectRegistration(uuid, reason = '') {
        const response = await api.post(`/admin/registrations/${uuid}/reject`, { rejection_reason: reason });
        pendingRegistrations.value = pendingRegistrations.value.filter(r => r.uuid !== uuid);
        return response.data;
    }

    async function inviteUser(data) {
        const response = await api.post('/admin/invite', data);
        return response.data;
    }

    return { pendingRegistrations, loading, pendingCount, fetchPendingRegistrations, approveRegistration, rejectRegistration, inviteUser };
});
