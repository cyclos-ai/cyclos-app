import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/plugins/api';
import axios from 'axios';

export const useCarrierOnboardingStore = defineStore('carrierOnboarding', () => {
    // ── Shipper-side state ──
    const carriers = ref([]);
    const invites = ref([]);
    const loading = ref(false);
    const carrierPagination = ref({ total: 0, page_num: 0, page_size: 20, pages: 0 });
    const invitePagination = ref({ total: 0, page_num: 0, page_size: 20, pages: 0 });

    // ── Public onboarding state ──
    const inviteData = ref(null);
    const lookupResult = ref(null);
    const lookupLoading = ref(false);
    const onboardingComplete = ref(false);

    const activeCarriers = computed(() => carriers.value.filter(c => c.status === 'active'));
    const pendingInvites = computed(() => invites.value.filter(i => i.status === 'pending'));

    // ================================================================
    // Shipper-side API calls (authenticated, tenant context)
    // ================================================================

    async function fetchCarriers(params = {}) {
        loading.value = true;
        try {
            const response = await api.get('/carrier-onboarding/carriers', { params });
            carriers.value = response.data.data;
            carrierPagination.value = response.data.meta;
        } catch (error) {
            console.error('Failed to fetch carriers:', error);
            throw error;
        } finally {
            loading.value = false;
        }
    }

    async function fetchInvites(params = {}) {
        loading.value = true;
        try {
            const response = await api.get('/carrier-onboarding/invites', { params });
            invites.value = response.data.data;
            invitePagination.value = response.data.meta;
        } catch (error) {
            console.error('Failed to fetch invites:', error);
            throw error;
        } finally {
            loading.value = false;
        }
    }

    async function createInvite(data = {}) {
        const response = await api.post('/carrier-onboarding/invites', data);
        return response.data.data;
    }

    async function revokeInvite(uuid) {
        await api.delete(`/carrier-onboarding/invites/${uuid}`);
    }

    // ================================================================
    // Public API calls (no auth — used by onboarding page)
    // These use raw axios, not the api plugin, because the baseURL
    // and auth token should not apply.
    // ================================================================

    const publicApi = axios.create({
        baseURL: '/api/v1/carrier-onboard',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
        },
    });

    async function validateInviteToken(tenantSlug, token) {
        loading.value = true;
        try {
            const response = await publicApi.get(`/${tenantSlug}/${token}`);
            inviteData.value = response.data.data;
            return inviteData.value;
        } catch (error) {
            inviteData.value = null;
            throw error;
        } finally {
            loading.value = false;
        }
    }

    async function lookupScac(scac) {
        lookupLoading.value = true;
        try {
            const response = await publicApi.get(`/lookup-scac/${scac}`);
            lookupResult.value = response.data.data;
            return lookupResult.value;
        } catch (error) {
            lookupResult.value = null;
            throw error;
        } finally {
            lookupLoading.value = false;
        }
    }

    async function lookupUsdot(usdot) {
        lookupLoading.value = true;
        try {
            const response = await publicApi.get(`/lookup-usdot/${usdot}`);
            lookupResult.value = response.data.data;
            return lookupResult.value;
        } catch (error) {
            lookupResult.value = null;
            throw error;
        } finally {
            lookupLoading.value = false;
        }
    }

    async function completeOnboarding(tenantSlug, token, formData) {
        loading.value = true;
        try {
            const response = await publicApi.post(`/${tenantSlug}/${token}/complete`, formData);
            onboardingComplete.value = true;
            return response.data.data;
        } catch (error) {
            throw error;
        } finally {
            loading.value = false;
        }
    }

    function $reset() {
        carriers.value = [];
        invites.value = [];
        inviteData.value = null;
        lookupResult.value = null;
        lookupLoading.value = false;
        onboardingComplete.value = false;
        loading.value = false;
    }

    return {
        // State
        carriers,
        invites,
        loading,
        carrierPagination,
        invitePagination,
        inviteData,
        lookupResult,
        lookupLoading,
        onboardingComplete,
        // Computed
        activeCarriers,
        pendingInvites,
        // Shipper actions
        fetchCarriers,
        fetchInvites,
        createInvite,
        revokeInvite,
        // Public actions
        validateInviteToken,
        lookupScac,
        lookupUsdot,
        completeOnboarding,
        $reset,
    };
});
