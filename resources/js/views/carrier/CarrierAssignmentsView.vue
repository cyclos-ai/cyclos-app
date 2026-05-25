<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">My Assignments</h1>
            <p class="text-sm text-gray-500 mt-1">All containers assigned to you</p>
        </div>

        <!-- Status filter -->
        <div class="mb-4">
            <SelectButton
                v-model="selectedStatus"
                :options="statusOptions"
                option-label="label"
                option-value="value"
                @change="filterAssignments"
            />
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <DataTable
                :value="filteredAssignments"
                :loading="loading"
                paginator
                :rows="15"
                size="small"
            >
                <template #empty>
                    <div class="text-center py-8 text-gray-400">No assignments found</div>
                </template>
                <Column field="container_number" header="Container #" sortable />
                <Column field="mbl_number" header="MBL" sortable />
                <Column field="status" header="Status">
                    <template #body="{ data }">
                        <Tag :value="formatStatus(data.status)" :severity="statusSeverity(data.status)" />
                    </template>
                </Column>
                <Column field="pickup_location" header="Pickup Location" />
                <Column field="delivery_location" header="Delivery Location" />
                <Column field="appointment_date" header="Appointment Date" sortable />
                <Column header="Actions">
                    <template #body="{ data }">
                        <router-link :to="{ name: 'carrier-drayage-detail', params: { uuid: data.uuid } }">
                            <Button label="Update" size="small" outlined />
                        </router-link>
                    </template>
                </Column>
            </DataTable>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import SelectButton from 'primevue/selectbutton';
import { useCarrierStore } from '@/stores/carrier';

const carrierStore = useCarrierStore();
const loading = ref(false);
const selectedStatus = ref('all');

const statusOptions = [
    { label: 'All', value: 'all' },
    { label: 'Pending', value: 'pending' },
    { label: 'Picked Up', value: 'picked_up' },
    { label: 'In Transit', value: 'in_transit' },
    { label: 'Delivered', value: 'delivered' },
];

const filteredAssignments = computed(() => {
    if (selectedStatus.value === 'all') return carrierStore.assignments;
    return carrierStore.assignments.filter(a => a.status === selectedStatus.value);
});

function formatStatus(status) {
    return status ? status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : '';
}

function statusSeverity(status) {
    const map = {
        pending: 'warn',
        picked_up: 'info',
        in_transit: 'info',
        delivered: 'success',
        empty_returned: 'secondary',
    };
    return map[status] || 'secondary';
}

async function filterAssignments() {
    loading.value = true;
    try {
        const params = selectedStatus.value !== 'all' ? { status: selectedStatus.value } : {};
        await carrierStore.fetchAssignments(params);
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    loading.value = true;
    try {
        await carrierStore.fetchAssignments();
    } finally {
        loading.value = false;
    }
});
</script>
