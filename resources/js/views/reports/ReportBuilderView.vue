<template>
    <div>
        <PageHeader title="Report Builder">
            <template #actions>
                <Button label="Cancel" text @click="$router.push({ name: 'reports' })" />
                <Button label="Save Report" icon="pi pi-save" :loading="saving" @click="saveReport" />
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Config panel -->
            <div class="space-y-5">
                <!-- Report details -->
                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Report Details</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Report Name</label>
                            <InputText v-model="report.name" placeholder="My Report" class="w-full" size="small" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                            <Textarea v-model="report.description" placeholder="Optional description" class="w-full" rows="2" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Report Type</label>
                            <Select
                                v-model="report.type"
                                :options="reportTypes"
                                option-label="label"
                                option-value="value"
                                class="w-full"
                                size="small"
                                @change="onTypeChange"
                            />
                        </div>
                    </div>
                </div>

                <!-- Columns -->
                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Columns</h3>
                    <div class="space-y-1.5">
                        <label
                            v-for="col in availableColumns"
                            :key="col.value"
                            class="flex items-center gap-2 cursor-pointer hover:bg-gray-50 rounded px-1 py-0.5"
                        >
                            <Checkbox v-model="report.columns" :value="col.value" />
                            <span class="text-sm text-gray-700">{{ col.label }}</span>
                        </label>
                    </div>
                </div>

                <!-- Schedule -->
                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-700">Schedule</h3>
                        <ToggleSwitch v-model="report.scheduled" />
                    </div>
                    <div v-if="report.scheduled" class="space-y-3">
                        <Select
                            v-model="report.schedule_frequency"
                            :options="['daily', 'weekly', 'monthly']"
                            class="w-full"
                            size="small"
                        />
                        <InputText v-model="report.schedule_email" placeholder="Email recipients" class="w-full" size="small" />
                    </div>
                </div>
            </div>

            <!-- Filters + Preview -->
            <div class="lg:col-span-2 space-y-5">
                <!-- Filter builder -->
                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Filters</h3>
                    <FilterBuilder :fields="filterFields" @apply="onFiltersApply" @clear="report.filters = []" />
                </div>

                <!-- Preview -->
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800">Preview</h3>
                        <Button label="Run Preview" icon="pi pi-play" size="small" outlined :loading="previewing" @click="runPreview" />
                    </div>
                    <div class="p-4">
                        <DataTable
                            v-if="previewData.length"
                            :value="previewData"
                            size="small"
                            class="text-xs"
                            :rows="10"
                        >
                            <Column
                                v-for="col in selectedColumnDefs"
                                :key="col.value"
                                :field="col.value"
                                :header="col.label"
                            />
                        </DataTable>
                        <div v-else class="text-center py-10 text-gray-400">
                            <i class="pi pi-table text-3xl mb-2 block"></i>
                            <p class="text-sm">Click "Run Preview" to see data</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { useRouter } from 'vue-router';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import Checkbox from 'primevue/checkbox';
import ToggleSwitch from 'primevue/toggleswitch';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import { useToast } from 'primevue/usetoast';
import PageHeader from '@/components/PageHeader.vue';
import FilterBuilder from '@/components/FilterBuilder.vue';
import { useReportsStore } from '@/stores/reports';

const router = useRouter();
const toast = useToast();
const reportsStore = useReportsStore();

const saving = ref(false);
const previewing = ref(false);
const previewData = ref([]);

const report = reactive({
    name: '',
    description: '',
    type: 'containers',
    columns: ['container_number', 'status', 'carrier_name', 'eta', 'last_free_day'],
    filters: [],
    scheduled: false,
    schedule_frequency: 'weekly',
    schedule_email: '',
});

const reportTypes = [
    { label: 'Containers', value: 'containers' },
    { label: 'Demurrage', value: 'demurrage' },
    { label: 'Detention', value: 'detention' },
    { label: 'Invoices', value: 'invoices' },
    { label: 'Tracking', value: 'tracking' },
    { label: 'Vessels', value: 'vessels' },
];

const columnsByType = {
    containers: [
        { label: 'Container #', value: 'container_number' },
        { label: 'MBL Number', value: 'mbl_number' },
        { label: 'Status', value: 'status' },
        { label: 'Carrier', value: 'carrier_name' },
        { label: 'POL', value: 'pol' },
        { label: 'POD', value: 'pod' },
        { label: 'ETA', value: 'eta' },
        { label: 'ATA', value: 'ata' },
        { label: 'Last Free Day', value: 'last_free_day' },
        { label: 'Vessel Name', value: 'vessel_name' },
        { label: 'Terminal', value: 'terminal_name' },
        { label: 'Weight', value: 'weight' },
        { label: 'Container Type', value: 'container_type' },
    ],
    demurrage: [
        { label: 'Container #', value: 'container_number' },
        { label: 'Carrier', value: 'carrier_name' },
        { label: 'LFD', value: 'last_free_day' },
        { label: 'Days Accruing', value: 'days_accruing' },
        { label: 'Daily Rate', value: 'daily_rate' },
        { label: 'Total Charges', value: 'total_charges' },
    ],
};

const availableColumns = computed(() => columnsByType[report.type] || columnsByType.containers);
const selectedColumnDefs = computed(() =>
    availableColumns.value.filter(c => report.columns.includes(c.value)),
);

const filterFields = computed(() => availableColumns.value.map(c => ({
    label: c.label,
    value: c.value,
    type: c.value.includes('date') || c.value.includes('eta') || c.value.includes('ata') || c.value.includes('lfd') ? 'date' : 'text',
})));

function onTypeChange() {
    report.columns = availableColumns.value.slice(0, 5).map(c => c.value);
    previewData.value = [];
}

function onFiltersApply(filters) {
    report.filters = filters.rules || [];
}

async function runPreview() {
    previewing.value = true;
    try {
        const resp = await reportsStore.generateReport('preview', { ...report });
        previewData.value = resp.data || [];
    } catch {
        previewData.value = [];
    } finally {
        previewing.value = false;
    }
}

async function saveReport() {
    if (!report.name.trim()) {
        toast.add({ severity: 'warn', summary: 'Name Required', detail: 'Please enter a report name', life: 3000 });
        return;
    }
    saving.value = true;
    try {
        await reportsStore.createReport({ ...report });
        toast.add({ severity: 'success', summary: 'Saved', detail: 'Report saved successfully', life: 3000 });
        router.push({ name: 'reports' });
    } finally {
        saving.value = false;
    }
}
</script>
