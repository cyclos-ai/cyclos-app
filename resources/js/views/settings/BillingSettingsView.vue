<template>
    <div>
        <PageHeader title="Billing" subtitle="Manage your subscription and billing details" />
        <div class="max-w-2xl space-y-6">
            <!-- Current plan -->
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Current Plan</h3>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xl font-bold text-gray-900">{{ planName }}</p>
                        <p class="text-sm text-gray-500 mt-1">{{ planDescription }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold text-blue-600">${{ planPrice }}<span class="text-sm font-normal text-gray-500">/mo</span></p>
                        <p class="text-xs text-gray-400 mt-1">Renews {{ renewDate }}</p>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-3 gap-4 text-center text-sm">
                    <div>
                        <p class="font-bold text-gray-900">{{ usage.containers }}</p>
                        <p class="text-gray-500 text-xs">Containers</p>
                    </div>
                    <div>
                        <p class="font-bold text-gray-900">{{ usage.users }}</p>
                        <p class="text-gray-500 text-xs">Users</p>
                    </div>
                    <div>
                        <p class="font-bold text-gray-900">{{ usage.api_calls }}</p>
                        <p class="text-gray-500 text-xs">API Calls (mo)</p>
                    </div>
                </div>
            </div>

            <!-- Payment method placeholder -->
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Payment Method</h3>
                <p class="text-sm text-gray-500">Billing is managed through our secure payment portal.</p>
                <Button label="Manage Billing" icon="pi pi-external-link" outlined size="small" class="mt-4" @click="openBillingPortal" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import Button from 'primevue/button';
import dayjs from 'dayjs';
import PageHeader from '@/components/PageHeader.vue';

const planName = ref('Professional');
const planDescription = ref('Full CLM platform with demurrage tracking and API access');
const planPrice = ref('499');
const renewDate = ref(dayjs().add(1, 'month').format('MMM D, YYYY'));
const usage = ref({ containers: '1,240', users: '12', api_calls: '48,302' });

function openBillingPortal() {
    window.open('/billing/portal', '_blank');
}
</script>
