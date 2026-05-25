import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/plugins/api';

export const useOrganizationStore = defineStore('organization', () => {
    const organization = ref(null);
    const members = ref([]);
    const loading = ref(false);

    async function fetchOrganization() {
        loading.value = true;
        try {
            const response = await api.get('/organization');
            organization.value = response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function updateOrganization(data) {
        loading.value = true;
        try {
            const response = await api.put('/organization', data);
            organization.value = response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function uploadLogo(file) {
        const formData = new FormData();
        formData.append('logo', file);
        const response = await api.post('/organization/logo', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        organization.value = response.data;
        return response.data;
    }

    async function fetchMembers() {
        loading.value = true;
        try {
            const response = await api.get('/organization/members');
            members.value = response.data.data || response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function inviteMember(data) {
        const response = await api.post('/organization/members/invite', data);
        return response.data;
    }

    async function updateMemberRole(userId, role) {
        const response = await api.put(`/organization/members/${userId}/role`, { role });
        const idx = members.value.findIndex(m => m.id === userId);
        if (idx !== -1) members.value[idx] = response.data;
        return response.data;
    }

    async function removeMember(userId) {
        await api.delete(`/organization/members/${userId}`);
        members.value = members.value.filter(m => m.id !== userId);
    }

    async function updateSSOSettings(data) {
        const response = await api.put('/organization/sso', data);
        organization.value = { ...organization.value, sso: response.data };
        return response.data;
    }

    return {
        organization,
        members,
        loading,
        fetchOrganization,
        updateOrganization,
        uploadLogo,
        fetchMembers,
        inviteMember,
        updateMemberRole,
        removeMember,
        updateSSOSettings,
    };
});
