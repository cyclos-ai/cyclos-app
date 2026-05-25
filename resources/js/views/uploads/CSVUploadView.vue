<template>
    <div>
        <PageHeader
            title="Upload Containers"
            subtitle="Import container data from CSV files"
        />

        <!-- Upload zone + mapping card -->
        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
            <!-- Drop zone -->
            <div
                class="relative border-2 border-dashed rounded-xl transition-colors"
                :class="isDragging
                    ? 'border-blue-400 bg-blue-50'
                    : selectedFile
                        ? 'border-green-400 bg-green-50'
                        : 'border-gray-300 bg-gray-50 hover:border-gray-400'"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="onDrop"
            >
                <input
                    ref="fileInput"
                    type="file"
                    accept=".csv"
                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                    @change="onFileChange"
                />

                <div class="py-12 flex flex-col items-center gap-3 pointer-events-none">
                    <template v-if="!selectedFile">
                        <i class="pi pi-file-excel text-5xl text-gray-300"></i>
                        <p class="text-base font-medium text-gray-600">Drag and drop your CSV file here</p>
                        <div class="flex items-center gap-3 w-48">
                            <div class="flex-1 h-px bg-gray-300"></div>
                            <span class="text-xs text-gray-400">or</span>
                            <div class="flex-1 h-px bg-gray-300"></div>
                        </div>
                        <span class="pointer-events-auto px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 cursor-pointer transition-colors">
                            Browse Files
                        </span>
                        <p class="text-xs text-gray-400">Only .csv files are accepted</p>
                    </template>

                    <template v-else>
                        <i class="pi pi-check-circle text-5xl text-green-500"></i>
                        <p class="text-base font-semibold text-gray-800">{{ selectedFile.name }}</p>
                        <p class="text-sm text-gray-500">{{ formatFileSize(selectedFile.size) }} &middot; {{ parsedRows.length }} rows detected</p>
                        <button
                            class="pointer-events-auto text-xs text-red-500 hover:text-red-700 underline"
                            @click.stop="clearFile"
                        >
                            Remove file
                        </button>
                    </template>
                </div>
            </div>

            <!-- Column mapping -->
            <template v-if="selectedFile && csvHeaders.length">
                <div class="mt-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">Column Mapping</h3>
                    <p class="text-xs text-gray-500 mb-4">Map your CSV columns to system fields. Unmatched columns will be ignored.</p>

                    <div class="overflow-hidden border border-gray-200 rounded-lg">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200">
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-1/2">CSV Column</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-1/2">System Field</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="header in csvHeaders" :key="header" class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5">
                                        <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">{{ header }}</span>
                                        <span v-if="previewSample(header)" class="ml-2 text-xs text-gray-400 truncate max-w-xs inline-block align-middle">
                                            e.g. {{ previewSample(header) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <Select
                                            v-model="columnMapping[header]"
                                            :options="systemFieldOptions"
                                            option-label="label"
                                            option-value="value"
                                            placeholder="— Ignore —"
                                            size="small"
                                            class="w-full"
                                            show-clear
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Preview table -->
                <div class="mt-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Data Preview <span class="font-normal text-gray-400">(first 5 rows)</span></h3>
                    <DataTable
                        :value="previewRows"
                        class="text-xs"
                        striped-rows
                        size="small"
                        scroll-direction="horizontal"
                        scrollable
                    >
                        <Column
                            v-for="field in mappedSystemFields"
                            :key="field.value"
                            :field="field.value"
                            :header="field.label"
                        >
                            <template #body="{ data }">
                                <span class="text-gray-700">{{ data[field.value] || '—' }}</span>
                            </template>
                        </Column>
                        <template #empty>
                            <div class="py-4 text-center text-gray-400 text-xs">No mapped columns to preview</div>
                        </template>
                    </DataTable>
                </div>

                <!-- Upload button + progress -->
                <div class="mt-6">
                    <div v-if="uploading" class="mb-4">
                        <div class="flex justify-between text-xs text-gray-600 mb-1">
                            <span>Uploading...</span>
                            <span>{{ uploadProgress }}%</span>
                        </div>
                        <ProgressBar :value="uploadProgress" class="h-2" />
                    </div>

                    <div v-if="uploadResult" class="mb-4 p-4 rounded-lg border" :class="uploadResult.errors > 0 ? 'bg-yellow-50 border-yellow-200' : 'bg-green-50 border-green-200'">
                        <div class="flex items-center gap-2 mb-2">
                            <i :class="uploadResult.errors > 0 ? 'pi pi-exclamation-triangle text-yellow-600' : 'pi pi-check-circle text-green-600'"></i>
                            <span class="text-sm font-semibold" :class="uploadResult.errors > 0 ? 'text-yellow-800' : 'text-green-800'">
                                Upload Complete
                            </span>
                        </div>
                        <div class="flex gap-6 text-sm">
                            <span class="text-green-700"><strong>{{ uploadResult.created }}</strong> created</span>
                            <span class="text-blue-700"><strong>{{ uploadResult.updated }}</strong> updated</span>
                            <span v-if="uploadResult.errors" class="text-red-700"><strong>{{ uploadResult.errors }}</strong> errors</span>
                        </div>
                        <ul v-if="uploadResult.error_messages?.length" class="mt-2 space-y-1">
                            <li v-for="(msg, i) in uploadResult.error_messages" :key="i" class="text-xs text-red-600">
                                <i class="pi pi-times-circle mr-1"></i>{{ msg }}
                            </li>
                        </ul>
                    </div>

                    <Button
                        label="Upload File"
                        icon="pi pi-upload"
                        :loading="uploading"
                        :disabled="uploading || !hasMappedColumns"
                        @click="uploadFile"
                    />
                    <span v-if="!hasMappedColumns" class="ml-3 text-xs text-gray-400">Map at least one column to upload</span>
                </div>
            </template>
        </div>

        <!-- Upload history -->
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h2 class="text-base font-semibold text-gray-800 mb-4">Upload History</h2>

            <DataTable
                :value="batches"
                :loading="batchesLoading"
                data-key="uuid"
                paginator
                :rows="10"
                :rows-per-page-options="[10, 25, 50]"
                striped-rows
                size="small"
                class="text-sm"
            >
                <Column field="filename" header="Filename">
                    <template #body="{ data }">
                        <span class="font-mono text-xs">{{ data.filename }}</span>
                    </template>
                </Column>
                <Column field="uploaded_by" header="Uploaded By">
                    <template #body="{ data }">
                        <span>{{ data.uploaded_by || '—' }}</span>
                    </template>
                </Column>
                <Column field="created_at" header="Date" sortable>
                    <template #body="{ data }">
                        <span class="text-xs text-gray-600">{{ formatDateTime(data.created_at) }}</span>
                    </template>
                </Column>
                <Column field="total_rows" header="Total" style="width: 80px">
                    <template #body="{ data }">
                        <span class="font-medium">{{ data.total_rows ?? '—' }}</span>
                    </template>
                </Column>
                <Column field="created_count" header="Created" style="width: 80px">
                    <template #body="{ data }">
                        <span class="text-green-700 font-medium">{{ data.created_count ?? '—' }}</span>
                    </template>
                </Column>
                <Column field="updated_count" header="Updated" style="width: 80px">
                    <template #body="{ data }">
                        <span class="text-blue-700 font-medium">{{ data.updated_count ?? '—' }}</span>
                    </template>
                </Column>
                <Column field="error_count" header="Errors" style="width: 80px">
                    <template #body="{ data }">
                        <span :class="data.error_count > 0 ? 'text-red-600 font-medium' : 'text-gray-400'">
                            {{ data.error_count ?? '—' }}
                        </span>
                    </template>
                </Column>
                <Column field="status" header="Status">
                    <template #body="{ data }">
                        <StatusBadge :status="data.status" size="small" />
                    </template>
                </Column>

                <template #empty>
                    <div class="py-10 text-center text-gray-400">
                        <i class="pi pi-upload text-3xl mb-2 block"></i>
                        <p>No uploads yet</p>
                    </div>
                </template>
            </DataTable>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Select from 'primevue/select';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import ProgressBar from 'primevue/progressbar';
import dayjs from 'dayjs';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import api from '@/plugins/api';

const toast = useToast();

// --- File state ---
const fileInput = ref(null);
const selectedFile = ref(null);
const isDragging = ref(false);
const csvHeaders = ref([]);
const parsedRows = ref([]); // all parsed data rows
const columnMapping = ref({});

// --- Upload state ---
const uploading = ref(false);
const uploadProgress = ref(0);
const uploadResult = ref(null);

// --- History ---
const batches = ref([]);
const batchesLoading = ref(false);

// ----------------------------------------------------------------
// System field definitions + auto-detect aliases
// ----------------------------------------------------------------
const systemFieldOptions = [
    { label: 'Container Number',  value: 'container_number' },
    { label: 'MBL Number',        value: 'mbl_number' },
    { label: 'Carrier SCAC',      value: 'carrier_scac' },
    { label: 'Container Type',    value: 'container_type' },
    { label: 'Vessel Name',       value: 'vessel_name' },
    { label: 'Voyage',            value: 'voyage' },
    { label: 'ETA',               value: 'eta' },
    { label: 'POD',               value: 'pod' },
    { label: 'POL',               value: 'pol' },
    { label: 'Consignee',         value: 'consignee' },
    { label: 'Shipper',           value: 'shipper' },
    { label: 'Notes',             value: 'notes' },
    { label: 'Booking Number',    value: 'booking_number' },
    { label: 'Commodity',         value: 'commodity' },
    { label: 'Weight',            value: 'weight' },
    { label: 'Size (ft)',         value: 'size' },
];

// Alias map: normalized CSV header → system field value
const ALIAS_MAP = {
    'container no':        'container_number',
    'container number':    'container_number',
    'container #':         'container_number',
    'cntr no':             'container_number',
    'cntr':                'container_number',
    'container_number':    'container_number',

    'bill of lading':      'mbl_number',
    'b/l':                 'mbl_number',
    'bl number':           'mbl_number',
    'mbl':                 'mbl_number',
    'mbl number':          'mbl_number',
    'mbl_number':          'mbl_number',
    'master bl':           'mbl_number',

    'scac':                'carrier_scac',
    'carrier':             'carrier_scac',
    'carrier scac':        'carrier_scac',
    'carrier_scac':        'carrier_scac',
    'ocean carrier':       'carrier_scac',

    'type':                'container_type',
    'container type':      'container_type',
    'container_type':      'container_type',
    'cntr type':           'container_type',

    'vessel':              'vessel_name',
    'vessel name':         'vessel_name',
    'vessel_name':         'vessel_name',
    'ship name':           'vessel_name',

    'voyage':              'voyage',
    'voyage number':       'voyage',
    'voyage no':           'voyage',
    'voyage_number':       'voyage',

    'eta':                 'eta',
    'estimated arrival':   'eta',
    'arrival date':        'eta',

    'pod':                 'pod',
    'port of discharge':   'pod',
    'discharge port':      'pod',
    'destination port':    'pod',

    'pol':                 'pol',
    'port of loading':     'pol',
    'load port':           'pol',
    'origin port':         'pol',

    'consignee':           'consignee',
    'importer':            'consignee',
    'notify party':        'consignee',

    'shipper':             'shipper',
    'exporter':            'shipper',
    'supplier':            'shipper',

    'notes':               'notes',
    'remarks':             'notes',
    'comments':            'notes',

    'booking':             'booking_number',
    'booking number':      'booking_number',
    'booking_number':      'booking_number',
    'booking no':          'booking_number',

    'commodity':           'commodity',
    'cargo':               'commodity',
    'description':         'commodity',

    'weight':              'weight',
    'gross weight':        'weight',
    'cargo weight':        'weight',

    'size':                'size',
    'container size':      'size',
    'ft':                  'size',
};

function autoDetectMapping(header) {
    const key = header.trim().toLowerCase();
    return ALIAS_MAP[key] || null;
}

// ----------------------------------------------------------------
// Computed
// ----------------------------------------------------------------
const mappedSystemFields = computed(() =>
    systemFieldOptions.filter(opt =>
        Object.values(columnMapping.value).includes(opt.value),
    ),
);

const previewRows = computed(() => {
    if (!parsedRows.value.length || !csvHeaders.value.length) return [];
    return parsedRows.value.slice(0, 5).map(row => {
        const mapped = {};
        csvHeaders.value.forEach((header, i) => {
            const field = columnMapping.value[header];
            if (field) mapped[field] = row[i] ?? '';
        });
        return mapped;
    });
});

const hasMappedColumns = computed(() =>
    Object.values(columnMapping.value).some(v => v),
);

// ----------------------------------------------------------------
// File handling
// ----------------------------------------------------------------
function onDrop(e) {
    isDragging.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file) processFile(file);
}

function onFileChange(e) {
    const file = e.target.files?.[0];
    if (file) processFile(file);
}

function processFile(file) {
    if (!file.name.toLowerCase().endsWith('.csv')) {
        toast.add({ severity: 'error', summary: 'Invalid file type', detail: 'Please select a .csv file', life: 3000 });
        return;
    }
    selectedFile.value = file;
    uploadResult.value = null;
    parseCSV(file);
}

function clearFile() {
    selectedFile.value = null;
    csvHeaders.value = [];
    parsedRows.value = [];
    columnMapping.value = {};
    uploadResult.value = null;
    if (fileInput.value) fileInput.value.value = '';
}

function parseCSV(file) {
    const reader = new FileReader();
    reader.onload = (e) => {
        const text = e.target.result;
        const lines = text.split(/\r?\n/).filter(l => l.trim());
        if (!lines.length) return;

        const headers = splitCSVLine(lines[0]);
        csvHeaders.value = headers;

        // Auto-detect column mapping
        const mapping = {};
        headers.forEach(h => {
            mapping[h] = autoDetectMapping(h);
        });
        columnMapping.value = mapping;

        // Parse data rows (skip header)
        parsedRows.value = lines.slice(1).map(line => splitCSVLine(line));
    };
    reader.readAsText(file);
}

function splitCSVLine(line) {
    // Handles quoted fields with commas
    const result = [];
    let current = '';
    let inQuotes = false;
    for (let i = 0; i < line.length; i++) {
        const ch = line[i];
        if (ch === '"') {
            inQuotes = !inQuotes;
        } else if (ch === ',' && !inQuotes) {
            result.push(current.trim());
            current = '';
        } else {
            current += ch;
        }
    }
    result.push(current.trim());
    return result;
}

function previewSample(header) {
    const idx = csvHeaders.value.indexOf(header);
    if (idx < 0 || !parsedRows.value.length) return null;
    const sample = parsedRows.value[0]?.[idx];
    return sample && sample.length > 0 ? sample : null;
}

// ----------------------------------------------------------------
// Upload
// ----------------------------------------------------------------
async function uploadFile() {
    if (!selectedFile.value || !hasMappedColumns.value) return;

    uploading.value = true;
    uploadProgress.value = 0;
    uploadResult.value = null;

    // Build mapping object: system_field → csv_header
    const mappingPayload = {};
    Object.entries(columnMapping.value).forEach(([header, field]) => {
        if (field) mappingPayload[field] = header;
    });

    const formData = new FormData();
    formData.append('file', selectedFile.value);
    formData.append('column_mapping', JSON.stringify(mappingPayload));

    try {
        const response = await api.post('/uploads/csv', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (e) => {
                uploadProgress.value = Math.round((e.loaded / e.total) * 100);
            },
        });

        const data = response.data?.data || response.data;
        uploadResult.value = {
            created:        data.created ?? 0,
            updated:        data.updated ?? 0,
            errors:         data.errors ?? 0,
            error_messages: data.error_messages ?? [],
        };

        toast.add({
            severity: uploadResult.value.errors > 0 ? 'warn' : 'success',
            summary: 'Upload Complete',
            detail: `${uploadResult.value.created} created, ${uploadResult.value.updated} updated, ${uploadResult.value.errors} errors`,
            life: 5000,
        });

        await loadBatches();
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Upload Failed',
            detail: err.response?.data?.message || 'An error occurred during upload',
            life: 5000,
        });
    } finally {
        uploading.value = false;
    }
}

// ----------------------------------------------------------------
// Upload history
// ----------------------------------------------------------------
async function loadBatches() {
    batchesLoading.value = true;
    try {
        const resp = await api.get('/uploads/batches');
        batches.value = resp.data?.data || resp.data || [];
    } catch {
        batches.value = [];
    } finally {
        batchesLoading.value = false;
    }
}

// ----------------------------------------------------------------
// Formatting
// ----------------------------------------------------------------
function formatFileSize(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function formatDateTime(d) {
    return d ? dayjs(d).format('MMM D, YYYY h:mm A') : '—';
}

onMounted(() => {
    loadBatches();
});
</script>
