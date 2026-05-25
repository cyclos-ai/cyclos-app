<template>
    <div v-if="report">
        <PageHeader :title="report.name" :subtitle="report.description">
            <template #actions>
                <Button label="Edit" icon="pi pi-pencil" outlined size="small" @click="$router.push({ name: 'report-builder' })" />
                <Button label="Export CSV" icon="pi pi-download" outlined size="small" @click="exportReport('csv')" />
                <Button label="Run Report" icon="pi pi-play" size="small" :loading="running" @click="runReport" />
            </template>
        </PageHeader>

        <DataTable
            :value="reportData"
            :loading="running"
            data-key="uuid"
            striped-rows
            paginator
            :rows="50"
            class="text-sm"
        >
            <Column
                v-for="col in columns"
                :key="col.value"
                :field="col.value"
                :header="col.label"
                sortable
            />
            <template #empty>
                <div class="py-10 text-center text-gray-400">
                    <i class="pi pi-table text-3xl mb-2 block"></i>
                    <p>Click "Run Report" to load data</p>
                </div>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import PageHeader from '@/components/PageHeader.vue';
import { useReportsStore } from '@/stores/reports';

const route = useRoute();
const reportsStore = useReportsStore();
const running = ref(false);
const reportData = ref([]);

const report = computed(() => reportsStore.currentReport);
const columns = computed(() => (report.value?.column_definitions || []).map(c => ({ label: c.label || c, value: c.value || c })));

async function runReport() {
    running.value = true;
    try {
        const result = await reportsStore.generateReport(route.params.uuid);
        reportData.value = result.data || [];
    } finally {
        running.value = false;
    }
}

async function exportReport(format) {
    const blob = await reportsStore.exportReport(route.params.uuid, format);
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${report.value?.name || 'report'}.${format}`;
    a.click();
    URL.revokeObjectURL(url);
}

onMounted(() => reportsStore.fetchReport(route.params.uuid));
</script>
