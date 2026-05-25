<template>
    <div v-if="po">
        <div class="flex items-center gap-4 mb-6">
            <button @click="$router.back()" class="text-gray-400 hover:text-gray-600"><i class="pi pi-arrow-left"></i></button>
            <div class="flex-1">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold font-mono text-gray-900">{{ po.po_number }}</h1>
                    <StatusBadge :status="po.status" />
                </div>
                <p class="text-sm text-gray-500 mt-1">{{ po.vendor_name }} &middot; {{ po.factory_name }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white border border-gray-200 rounded-xl p-4 text-sm">
                <p class="text-xs text-gray-500 mb-3 font-semibold uppercase tracking-wider">Order Details</p>
                <div class="space-y-2">
                    <div class="flex justify-between"><span class="text-gray-500">Order Date</span><span class="font-medium">{{ formatDate(po.order_date) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Ship By</span><span class="font-medium">{{ formatDate(po.ship_by_date) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Delivery By</span><span class="font-medium">{{ formatDate(po.delivery_date) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Incoterms</span><span class="font-medium">{{ po.incoterms || '—' }}</span></div>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 text-sm">
                <p class="text-xs text-gray-500 mb-3 font-semibold uppercase tracking-wider">Supplier</p>
                <div class="space-y-2">
                    <div class="flex justify-between"><span class="text-gray-500">Vendor</span><span class="font-medium">{{ po.vendor_name }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Factory</span><span class="font-medium">{{ po.factory_name || '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Country of Origin</span><span class="font-medium">{{ po.country_of_origin || '—' }}</span></div>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 text-sm">
                <p class="text-xs text-gray-500 mb-3 font-semibold uppercase tracking-wider">Summary</p>
                <div class="space-y-2">
                    <div class="flex justify-between"><span class="text-gray-500">Total Qty</span><span class="font-medium">{{ po.total_quantity?.toLocaleString() }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Total Value</span><span class="font-bold text-blue-700">${{ formatCurrency(po.total_value) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Containers</span><span class="font-semibold text-blue-600">{{ po.container_count || 0 }}</span></div>
                </div>
            </div>
        </div>

        <!-- Line items -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-gray-100 font-semibold text-gray-800">Line Items</div>
            <DataTable :value="po.items || []" size="small" class="text-sm">
                <Column field="sku_code" header="SKU">
                    <template #body="{ data }"><span class="font-mono text-xs font-semibold text-blue-600">{{ data.sku_code }}</span></template>
                </Column>
                <Column field="description" header="Description" />
                <Column field="quantity_ordered" header="Ordered" />
                <Column field="quantity_shipped" header="Shipped" />
                <Column field="unit_price" header="Unit Price"><template #body="{ data }">${{ formatCurrency(data.unit_price) }}</template></Column>
                <Column field="total_price" header="Total"><template #body="{ data }"><span class="font-semibold">${{ formatCurrency(data.total_price) }}</span></template></Column>
                <template #empty><div class="py-6 text-center text-gray-400 text-sm">No line items</div></template>
            </DataTable>
        </div>

        <!-- Linked containers -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 font-semibold text-gray-800">Linked Containers</div>
            <DataTable :value="po.containers || []" size="small" class="text-sm">
                <Column field="container_number" header="Container #">
                    <template #body="{ data }">
                        <router-link :to="{ name: 'container-detail', params: { uuid: data.uuid } }" class="font-mono font-semibold text-blue-600 hover:text-blue-800">{{ data.container_number }}</router-link>
                    </template>
                </Column>
                <Column field="status" header="Status"><template #body="{ data }"><StatusBadge :status="data.status" size="small" /></template></Column>
                <Column field="eta" header="ETA"><template #body="{ data }"><span class="text-xs">{{ formatDate(data.eta) }}</span></template></Column>
                <template #empty><div class="py-6 text-center text-gray-400 text-sm">No containers linked</div></template>
            </DataTable>
        </div>
    </div>
    <div v-else-if="loading" class="flex justify-center py-20"><ProgressSpinner /></div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import ProgressSpinner from 'primevue/progressspinner';
import dayjs from 'dayjs';
import StatusBadge from '@/components/StatusBadge.vue';
import api from '@/plugins/api';

const route = useRoute();
const po = ref(null);
const loading = ref(false);

function formatDate(d) { return d ? dayjs(d).format('MMM D, YYYY') : '—'; }
function formatCurrency(v) { return v ? Number(v).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '0.00'; }

onMounted(async () => {
    loading.value = true;
    try {
        const resp = await api.get(`/purchase-orders/${route.params.uuid}`);
        po.value = resp.data;
    } finally { loading.value = false; }
});
</script>
