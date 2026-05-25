<template>
    <div>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Invoices</h1>
                <p class="text-sm text-gray-500 mt-1">Submitted carrier invoices</p>
            </div>
            <router-link :to="{ name: 'carrier-invoice-new' }">
                <Button label="Submit Invoice" icon="pi pi-plus" />
            </router-link>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <DataTable :value="invoices" :loading="loading" paginator :rows="20" size="small">
                <template #empty>
                    <div class="text-center py-8 text-gray-400">No invoices submitted yet</div>
                </template>
                <Column field="invoice_number" header="Invoice #" sortable />
                <Column field="container_number" header="Container" sortable />
                <Column field="amount" header="Amount">
                    <template #body="{ data }">
                        {{ data.amount != null ? `$${Number(data.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}` : '—' }}
                    </template>
                </Column>
                <Column field="status" header="Status">
                    <template #body="{ data }">
                        <Tag :value="formatStatus(data.status)" :severity="invoiceStatusSeverity(data.status)" />
                    </template>
                </Column>
                <Column field="created_at" header="Date" sortable />
            </DataTable>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { useCarrierStore } from '@/stores/carrier';

const carrierStore = useCarrierStore();
const loading = ref(false);
const invoices = ref([]);

function formatStatus(status) {
    return status ? status.charAt(0).toUpperCase() + status.slice(1) : '';
}

function invoiceStatusSeverity(status) {
    const map = {
        draft: 'secondary',
        submitted: 'info',
        approved: 'success',
        paid: 'success',
        rejected: 'danger',
    };
    return map[status] || 'secondary';
}

onMounted(async () => {
    loading.value = true;
    try {
        await carrierStore.fetchInvoices();
        invoices.value = carrierStore.invoices;
    } finally {
        loading.value = false;
    }
});
</script>
