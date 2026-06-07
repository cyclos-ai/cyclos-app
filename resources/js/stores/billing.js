import { ref } from 'vue';
import { defineStore } from 'pinia';
import api from '@/plugins/api';

export const useBillingStore = defineStore('billing', () => {
    const plans = ref([]);
    const current = ref(null);
    const loading = ref(false);

    async function fetchPlans() {
        try {
            const { data } = await api.get('/billing/plans');
            plans.value = data.data || [];
        } catch {
            plans.value = [];
        }
    }

    async function fetchCurrent() {
        loading.value = true;
        try {
            const { data } = await api.get('/billing/current');
            current.value = data.data;
        } catch {
            current.value = null;
        } finally {
            loading.value = false;
        }
    }

    async function checkout(planId, billingCycle) {
        const { data } = await api.post('/billing/checkout', {
            plan_id: planId,
            billing_cycle: billingCycle,
        });
        window.location.href = data.data.checkout_url;
    }

    async function openPortal() {
        const { data } = await api.post('/billing/portal');
        window.location.href = data.data.portal_url;
    }

    return {
        plans,
        current,
        loading,
        fetchPlans,
        fetchCurrent,
        checkout,
        openPortal,
    };
});
