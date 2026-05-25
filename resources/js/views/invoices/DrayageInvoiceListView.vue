<template>
    <div>
        <PageHeader title="Drayage Invoices">
            <template #actions>
                <DataExport @export="() => {}" />
                <Button label="New Invoice" icon="pi pi-plus" size="small" />
            </template>
        </PageHeader>

        <div class="flex gap-1 mb-4 bg-gray-100 p-1 rounded-lg w-fit">
            <button
                v-for="tab in statusTabs"
                :key="tab.value"
                class="px-4 py-1.5 rounded-md text-sm font-medium transition-colors"
                :class="activeTab === tab.value ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                @click="setTab(tab.value)"
            >{{ tab.label }}</button>
        </div>

        <DataTable
            :value="invoicesStore.drayageInvoices"
            :loading="invoicesStore.loading"
            data-key="uuid"
            striped-rows
            paginator
            :rows="25"
            class="text-sm"
        >
            <Column field="invoice_number" header="Invoice #" sortable>
                <template #body="{ data }">
                    <router-link :to="{ name: 'drayage-invoice-detail', params: { uuid: data.uuid } }" class="font-mono font-semibold text-blue-600 hover:text-blue-800">
                        {{ data.invoice_number }}
                    </router-link>
                </template>
            </Column>
            <Column field="carrier_name" header="Carrier" sortable />
            <Column field="container_number" header="Container" sortable>
                <template #body="{ data }"><span class="font-mono text-xs">{{ data.container_number || '—' }}</span></template>
            </Column>
            <Column field="pickup_location" header="Pickup" />
            <Column field="delivery_location" header="Delivery" />
            <Column field="invoice_date" header="Date" sortable>
                <template #body="{ data }"><span class="text-xs">{{ formatDate(data.invoice_date) }}</span></template>
            </Column>
            <Column field="total_amount" header="Amount" sortable>
                <template #body="{ data }"><span class="font-semibold">${{ formatCurrency(data.total_amount) }}</span></template>
            </Column>
            <Column field="status" header="Status" sortable>
                <template #body="{ data }"><StatusBadge :status="data.status" size="small" /></template>
            </Column>
            <template #empty>
                <div class="py-10 text-center text-gray-400">
                    <i class="pi pi-truck text-3xl mb-2 block"></i>
                    <p>No drayage invoices found</p>
                </div>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import dayjs from 'dayjs';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import DataExport from '@/components/DataExport.vue';
import { useInvoicesStore } from '@/stores/invoices';

const invoicesStore = useInvoicesStore();
const activeTab = ref('all');
const statusTabs = [
    { label: 'All', value: 'all' },
    { label: 'Pending', value: 'pending' },
    { label: 'OK to Pay', value: 'ok_to_pay' },
    { label: 'Paid', value: 'paid' },
    { label: 'Disputed', value: 'disputed' },
];
function setTab(tab) { activeTab.value = tab; load(); }
function formatDate(d) { return d ? dayjs(d).format('MMM D, YYYY') : '—'; }
function formatCurrency(v) { return v ? Number(v).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '0.00'; }
async function load() {
    const params = activeTab.value !== 'all' ? { status: activeTab.value } : {};
    await invoicesStore.fetchDrayageInvoices(params);
}
onMounted(load);
</script>
