<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Drayage Execution</h1>
            <p class="text-sm text-gray-500 mt-1">Containers requiring step updates</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <DataTable :value="assignments" :loading="loading" paginator :rows="20" size="small">
                <template #empty>
                    <div class="text-center py-8 text-gray-400">No containers requiring updates</div>
                </template>
                <Column field="container_number" header="Container #" sortable />
                <Column field="current_step" header="Current Step">
                    <template #body="{ data }">
                        <Tag :value="formatStep(data.current_step)" :severity="stepSeverity(data.current_step)" />
                    </template>
                </Column>
                <Column field="updated_at" header="Last Updated" sortable />
                <Column header="Actions">
                    <template #body="{ data }">
                        <router-link :to="{ name: 'carrier-drayage-detail', params: { uuid: data.uuid } }">
                            <Button label="Update Step" size="small" icon="pi pi-arrow-right" icon-pos="right" />
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
const assignments = ref([]);

function formatStep(step) {
    return step ? step.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : 'Pending';
}

function stepSeverity(step) {
    const map = {
        pending: 'secondary',
        picked_up: 'warn',
        in_transit: 'info',
        delivered: 'success',
        empty_returned: 'contrast',
    };
    return map[step] || 'secondary';
}

onMounted(async () => {
    loading.value = true;
    try {
        assignments.value = await carrierStore.fetchAssignments({ exclude_status: 'delivered' });
    } finally {
        loading.value = false;
    }
});
</script>
