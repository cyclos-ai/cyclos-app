<template>
    <div v-if="invoice">
        <div class="flex items-center gap-4 mb-6">
            <button @click="$router.back()" class="text-gray-400 hover:text-gray-600"><i class="pi pi-arrow-left"></i></button>
            <div class="flex-1">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold font-mono text-gray-900">{{ invoice.invoice_number }}</h1>
                    <StatusBadge :status="invoice.status" />
                </div>
                <p class="text-sm text-gray-500 mt-1">{{ invoice.carrier_name }} &middot; Drayage Invoice</p>
            </div>
            <div class="flex gap-2">
                <Button v-if="invoice.status === 'pending'" label="OK to Pay" icon="pi pi-check" size="small" severity="success" @click="updateStatus('ok_to_pay')" />
                <Button v-if="invoice.status === 'ok_to_pay'" label="Record Payment" icon="pi pi-dollar" size="small" @click="updateStatus('paid')" />
                <Button v-if="['pending','ok_to_pay'].includes(invoice.status)" label="Dispute" size="small" severity="warn" outlined @click="updateStatus('disputed')" />
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-2 bg-white border border-gray-200 rounded-xl p-5">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div><p class="text-xs text-gray-500">Invoice Date</p><p class="font-medium mt-0.5">{{ formatDate(invoice.invoice_date) }}</p></div>
                    <div><p class="text-xs text-gray-500">Due Date</p><p class="font-medium mt-0.5">{{ formatDate(invoice.due_date) }}</p></div>
                    <div><p class="text-xs text-gray-500">Container</p><p class="font-medium mt-0.5 font-mono">{{ invoice.container_number || '—' }}</p></div>
                    <div><p class="text-xs text-gray-500">Pickup</p><p class="font-medium mt-0.5">{{ invoice.pickup_location || '—' }}</p></div>
                    <div><p class="text-xs text-gray-500">Delivery</p><p class="font-medium mt-0.5">{{ invoice.delivery_location || '—' }}</p></div>
                    <div><p class="text-xs text-gray-500">Driver / Carrier</p><p class="font-medium mt-0.5">{{ invoice.carrier_name || '—' }}</p></div>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Summary</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span>${{ formatCurrency(invoice.subtotal) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Fuel Surcharge</span><span>${{ formatCurrency(invoice.fuel_surcharge) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Accessorials</span><span>${{ formatCurrency(invoice.accessorial_amount) }}</span></div>
                    <div class="border-t pt-2 flex justify-between font-bold text-base">
                        <span>Total</span><span class="text-blue-700">${{ formatCurrency(invoice.total_amount) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100"><h3 class="font-semibold text-gray-800">Line Items</h3></div>
            <DataTable :value="invoice.items || []" size="small" class="text-sm">
                <Column field="description" header="Description" />
                <Column field="quantity" header="Qty" style="width:80px" />
                <Column field="unit_price" header="Unit Price"><template #body="{ data }">${{ formatCurrency(data.unit_price) }}</template></Column>
                <Column field="amount" header="Amount"><template #body="{ data }"><span class="font-semibold">${{ formatCurrency(data.amount) }}</span></template></Column>
                <template #empty><div class="py-6 text-center text-gray-400 text-sm">No line items</div></template>
            </DataTable>
        </div>
    </div>
    <div v-else-if="loading" class="flex justify-center py-20"><ProgressSpinner /></div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import ProgressSpinner from 'primevue/progressspinner';
import { useToast } from 'primevue/usetoast';
import dayjs from 'dayjs';
import StatusBadge from '@/components/StatusBadge.vue';
import { useInvoicesStore } from '@/stores/invoices';

const route = useRoute();
const toast = useToast();
const invoicesStore = useInvoicesStore();
const loading = ref(false);
const invoice = computed(() => invoicesStore.currentInvoice);

function formatDate(d) { return d ? dayjs(d).format('MMM D, YYYY') : '—'; }
function formatCurrency(v) { return v ? Number(v).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '0.00'; }
async function updateStatus(status) {
    await invoicesStore.updateDrayageInvoice(invoice.value.uuid, { status });
    toast.add({ severity: 'success', summary: 'Updated', detail: `Status changed to ${status}`, life: 3000 });
}
onMounted(async () => {
    loading.value = true;
    try { await invoicesStore.fetchDrayageInvoice(route.params.uuid); }
    finally { loading.value = false; }
});
</script>
