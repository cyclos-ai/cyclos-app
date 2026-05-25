<template>
    <div>
        <PageHeader title="Reports">
            <template #actions>
                <Button label="Build Report" icon="pi pi-plus" size="small" @click="$router.push({ name: 'report-builder' })" />
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="report in reportsStore.reports"
                :key="report.uuid"
                class="bg-white border border-gray-200 rounded-xl p-5 hover:shadow-md transition-shadow cursor-pointer group"
                @click="$router.push({ name: 'report-detail', params: { uuid: report.uuid } })"
            >
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                        <i class="pi pi-chart-bar text-blue-600"></i>
                    </div>
                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <Button icon="pi pi-play" text size="small" rounded title="Run" @click.stop="runReport(report)" />
                        <Button icon="pi pi-download" text size="small" rounded title="Export" @click.stop="exportReport(report)" />
                        <Button icon="pi pi-trash" text size="small" rounded severity="danger" title="Delete" @click.stop="deleteReport(report)" />
                    </div>
                </div>
                <h3 class="font-semibold text-gray-900 mb-1">{{ report.name }}</h3>
                <p class="text-xs text-gray-500 mb-3 line-clamp-2">{{ report.description || 'No description' }}</p>
                <div class="flex items-center justify-between text-xs text-gray-400">
                    <span class="capitalize">{{ report.type }}</span>
                    <span>Last run {{ formatDate(report.last_run_at) }}</span>
                </div>
            </div>

            <!-- New report card -->
            <div
                class="border-2 border-dashed border-gray-200 rounded-xl p-5 flex flex-col items-center justify-center text-gray-400 hover:border-blue-400 hover:text-blue-500 transition-colors cursor-pointer min-h-[160px]"
                @click="$router.push({ name: 'report-builder' })"
            >
                <i class="pi pi-plus text-2xl mb-2"></i>
                <p class="text-sm font-medium">Build New Report</p>
            </div>
        </div>

        <div v-if="reportsStore.loading" class="flex justify-center py-12">
            <ProgressSpinner />
        </div>

        <div v-if="!reportsStore.loading && !reportsStore.reports.length" class="text-center py-16 text-gray-400">
            <i class="pi pi-chart-bar text-4xl mb-3 block"></i>
            <p class="font-medium text-gray-600">No reports yet</p>
            <Button label="Build First Report" class="mt-4" @click="$router.push({ name: 'report-builder' })" />
        </div>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import Button from 'primevue/button';
import ProgressSpinner from 'primevue/progressspinner';
import { useConfirm } from 'primevue/useconfirm';
import dayjs from 'dayjs';
import PageHeader from '@/components/PageHeader.vue';
import { useReportsStore } from '@/stores/reports';

const reportsStore = useReportsStore();
const confirm = useConfirm();

function formatDate(d) { return d ? dayjs(d).fromNow() : 'Never'; }

async function runReport(report) {
    await reportsStore.generateReport(report.uuid);
}

async function exportReport(report) {
    const blob = await reportsStore.exportReport(report.uuid, 'csv');
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${report.name}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

function deleteReport(report) {
    confirm.require({
        message: `Delete report "${report.name}"?`,
        header: 'Confirm Delete',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => reportsStore.deleteReport(report.uuid),
    });
}

onMounted(() => reportsStore.fetchReports());
</script>
