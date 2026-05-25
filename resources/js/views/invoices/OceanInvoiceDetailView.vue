<template>
    <div v-if="invoice">
        <div class="flex items-start justify-between mb-6">
            <div class="flex items-start gap-4">
                <button @click="$router.back()" class="mt-1 text-gray-400 hover:text-gray-600">
                    <i class="pi pi-arrow-left"></i>
                </button>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold font-mono text-gray-900">{{ invoice.invoice_number }}</h1>
                        <StatusBadge :status="invoice.status" />
                    </div>
                    <p class="text-sm text-gray-500 mt-1">{{ invoice.carrier_name }} &middot; Issued {{ formatDate(invoice.invoice_date) }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Button
                    v-if="invoice.status === 'pending'"
                    label="OK to Pay"
                    icon="pi pi-check"
                    size="small"
                    severity="success"
                    @click="updateStatus('ok_to_pay')"
                />
                <Button
                    v-if="invoice.status === 'ok_to_pay'"
                    label="Record Payment"
                    icon="pi pi-dollar"
                    size="small"
                    @click="updateStatus('paid')"
                />
                <Button
                    v-if="['pending', 'ok_to_pay'].includes(invoice.status)"
                    label="Dispute"
                    icon="pi pi-exclamation-triangle"
                    size="small"
                    severity="warn"
                    outlined
                    @click="updateStatus('disputed')"
                />
                <Button
                    v-if="invoice.status !== 'void'"
                    label="Void"
                    size="small"
                    severity="secondary"
                    outlined
                    @click="updateStatus('void')"
                />
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Invoice info -->
            <div class="lg:col-span-2 bg-white border border-gray-200 rounded-xl p-5">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-xs text-gray-500">Invoice Date</p>
                        <p class="font-medium mt-0.5">{{ formatDate(invoice.invoice_date) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Due Date</p>
                        <p class="font-medium mt-0.5" :class="isOverdue ? 'text-red-600' : ''">{{ formatDate(invoice.due_date) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Carrier</p>
                        <p class="font-medium mt-0.5">{{ invoice.carrier_name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Container</p>
                        <router-link
                            v-if="invoice.container_uuid"
                            :to="{ name: 'container-detail', params: { uuid: invoice.container_uuid } }"
                            class="font-mono font-medium text-blue-600 hover:underline mt-0.5 block"
                        >
                            {{ invoice.container_number }}
                        </router-link>
                        <p v-else class="font-medium mt-0.5 font-mono">{{ invoice.container_number || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">MBL</p>
                        <p class="font-medium mt-0.5 font-mono">{{ invoice.mbl_number || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Payment Terms</p>
                        <p class="font-medium mt-0.5">{{ invoice.payment_terms || '—' }}</p>
                    </div>
                </div>
            </div>

            <!-- Totals card -->
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Invoice Summary</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Subtotal</span>
                        <span>${{ formatCurrency(invoice.subtotal) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Taxes</span>
                        <span>${{ formatCurrency(invoice.tax_amount) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Adjustments</span>
                        <span>${{ formatCurrency(invoice.adjustment_amount) }}</span>
                    </div>
                    <div class="border-t border-gray-200 pt-2 flex justify-between font-bold text-base">
                        <span>Total</span>
                        <span class="text-blue-700">${{ formatCurrency(invoice.total_amount) }}</span>
                    </div>
                    <div v-if="invoice.paid_amount" class="flex justify-between text-green-600">
                        <span>Paid</span>
                        <span>${{ formatCurrency(invoice.paid_amount) }}</span>
                    </div>
                    <div v-if="invoice.balance_due" class="flex justify-between font-semibold">
                        <span>Balance Due</span>
                        <span :class="invoice.balance_due > 0 ? 'text-red-600' : 'text-green-600'">
                            ${{ formatCurrency(invoice.balance_due) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Line items -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Line Items</h3>
            </div>
            <DataTable :value="invoice.items || []" size="small" class="text-sm">
                <Column field="description" header="Description" />
                <Column field="quantity" header="Qty" style="width: 80px" />
                <Column field="unit_price" header="Unit Price">
                    <template #body="{ data }">
                        <span>${{ formatCurrency(data.unit_price) }}</span>
                    </template>
                </Column>
                <Column field="amount" header="Amount">
                    <template #body="{ data }">
                        <span class="font-semibold">${{ formatCurrency(data.amount) }}</span>
                    </template>
                </Column>
                <template #empty>
                    <div class="py-6 text-center text-gray-400 text-sm">No line items</div>
                </template>
            </DataTable>
        </div>
    </div>

    <div v-else-if="loading" class="flex justify-center py-20">
        <ProgressSpinner />
    </div>
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
const isOverdue = computed(() => invoice.value?.due_date && dayjs(invoice.value.due_date).isBefore(dayjs(), 'day') && invoice.value.status !== 'paid');

function formatDate(d) { return d ? dayjs(d).format('MMM D, YYYY') : '—'; }
function formatCurrency(v) { return v ? Number(v).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '0.00'; }

async function updateStatus(status) {
    await invoicesStore.updateOceanInvoiceStatus(invoice.value.uuid, status);
    toast.add({ severity: 'success', summary: 'Updated', detail: `Invoice status changed to ${status}`, life: 3000 });
}

onMounted(async () => {
    loading.value = true;
    try { await invoicesStore.fetchOceanInvoice(route.params.uuid); }
    finally { loading.value = false; }
});
</script>
