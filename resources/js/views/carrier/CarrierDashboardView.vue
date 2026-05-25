<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Carrier Dashboard</h1>
            <p class="text-sm text-gray-500 mt-1">Overview of your assignments and activity</p>
        </div>

        <!-- Stat cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-500">Pending Pickup</span>
                    <span class="w-8 h-8 rounded-lg bg-yellow-50 flex items-center justify-center">
                        <i class="pi pi-clock text-yellow-500 text-sm"></i>
                    </span>
                </div>
                <p class="text-3xl font-bold text-gray-900">{{ stats.pending_pickup }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-500">In Transit</span>
                    <span class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                        <i class="pi pi-send text-blue-500 text-sm"></i>
                    </span>
                </div>
                <p class="text-3xl font-bold text-gray-900">{{ stats.in_transit }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-500">Delivered Today</span>
                    <span class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center">
                        <i class="pi pi-check-circle text-green-500 text-sm"></i>
                    </span>
                </div>
                <p class="text-3xl font-bold text-gray-900">{{ stats.delivered_today }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-500">Total Assigned</span>
                    <span class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center">
                        <i class="pi pi-list text-purple-500 text-sm"></i>
                    </span>
                </div>
                <p class="text-3xl font-bold text-gray-900">{{ stats.total_assigned }}</p>
            </div>
        </div>

        <!-- Recent assignments -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-semibold text-gray-800">Recent Assignments</h2>
                <router-link :to="{ name: 'carrier-assignments' }" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                    View all
                </router-link>
            </div>
            <DataTable :value="recentAssignments" :loading="loading" size="small" class="text-sm">
                <template #empty>
                    <div class="text-center py-8 text-gray-400">No assignments yet</div>
                </template>
                <Column field="container_number" header="Container #" />
                <Column field="mbl_number" header="MBL" />
                <Column field="status" header="Status">
                    <template #body="{ data }">
                        <Tag :value="data.status" :severity="statusSeverity(data.status)" />
                    </template>
                </Column>
                <Column field="pickup_location" header="Pickup Location" />
                <Column field="appointment_date" header="Appointment" />
                <Column header="Actions">
                    <template #body="{ data }">
                        <router-link :to="{ name: 'carrier-drayage-detail', params: { uuid: data.uuid } }">
                            <Button label="View" size="small" text />
                        </router-link>
                    </template>
                </Column>
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
const recentAssignments = ref([]);

const stats = ref({
    pending_pickup: 0,
    in_transit: 0,
    delivered_today: 0,
    total_assigned: 0,
});

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

onMounted(async () => {
    loading.value = true;
    try {
        await carrierStore.fetchDashboardStats();
        stats.value = { ...carrierStore.dashboardStats };
        const assignments = await carrierStore.fetchAssignments({ per_page: 5 });
        recentAssignments.value = assignments;
    } finally {
        loading.value = false;
    }
});
</script>
