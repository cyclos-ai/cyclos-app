<template>
    <div>
        <PageHeader title="Purchase Orders">
            <template #actions>
                <DataExport @export="() => {}" />
                <Button label="New PO" icon="pi pi-plus" size="small" />
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

        <DataTable :value="items" :loading="loading" data-key="uuid" striped-rows paginator :rows="25" class="text-sm">
            <Column field="po_number" header="PO Number" sortable>
                <template #body="{ data }">
                    <router-link :to="{ name: 'purchase-order-detail', params: { uuid: data.uuid } }" class="font-mono font-semibold text-blue-600 hover:text-blue-800">{{ data.po_number }}</router-link>
                </template>
            </Column>
            <Column field="vendor_name" header="Vendor" sortable />
            <Column field="factory_name" header="Factory" />
            <Column field="status" header="Status" sortable>
                <template #body="{ data }"><StatusBadge :status="data.status" size="small" /></template>
            </Column>
            <Column field="order_date" header="Order Date" sortable>
                <template #body="{ data }"><span class="text-xs">{{ formatDate(data.order_date) }}</span></template>
            </Column>
            <Column field="ship_by_date" header="Ship By" sortable>
                <template #body="{ data }"><span class="text-xs">{{ formatDate(data.ship_by_date) }}</span></template>
            </Column>
            <Column field="total_quantity" header="Qty" sortable>
                <template #body="{ data }"><span class="font-medium">{{ data.total_quantity?.toLocaleString() || '—' }}</span></template>
            </Column>
            <Column field="total_value" header="Value" sortable>
                <template #body="{ data }"><span class="font-semibold">${{ formatCurrency(data.total_value) }}</span></template>
            </Column>
            <Column field="container_count" header="Containers">
                <template #body="{ data }"><span class="text-blue-600 font-semibold">{{ data.container_count || 0 }}</span></template>
            </Column>
            <template #empty>
                <div class="py-10 text-center text-gray-400">
                    <i class="pi pi-shopping-cart text-3xl mb-2 block"></i>
                    <p>No purchase orders found</p>
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
import api from '@/plugins/api';

const items = ref([]);
const loading = ref(false);
const activeTab = ref('all');

const statusTabs = [
    { label: 'All', value: 'all' },
    { label: 'Open', value: 'open' },
    { label: 'In Transit', value: 'in_transit' },
    { label: 'Delivered', value: 'delivered' },
    { label: 'Closed', value: 'closed' },
];

function setTab(tab) { activeTab.value = tab; load(); }
function formatDate(d) { return d ? dayjs(d).format('MMM D, YYYY') : '—'; }
function formatCurrency(v) { return v ? Number(v).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '0.00'; }

async function load() {
    loading.value = true;
    try {
        const params = activeTab.value !== 'all' ? { status: activeTab.value } : {};
        const resp = await api.get('/purchase-orders', { params });
        items.value = resp.data.data || resp.data;
    } catch { items.value = []; }
    finally { loading.value = false; }
}

onMounted(load);
</script>
