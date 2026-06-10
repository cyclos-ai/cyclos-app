<template>
    <div class="max-w-4xl mx-auto px-4 py-6">
        <Toast position="bottom-right" />

        <!-- Page header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">New Order</h1>
            <p class="text-sm text-gray-500 mt-0.5">Import a delivery order PDF or enter manually to create an MBL with containers.</p>
        </div>

        <!-- Step indicator -->
        <div class="flex items-center gap-2 mb-8">
            <template v-for="(s, idx) in steps" :key="s.key">
                <div class="flex items-center gap-2">
                    <div
                        class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-colors"
                        :class="stepIndex >= idx
                            ? 'bg-teal-600 text-white'
                            : 'bg-gray-200 dark:bg-surface-700 text-gray-500 dark:text-surface-400'"
                    >
                        <i v-if="stepIndex > idx" class="pi pi-check text-xs"></i>
                        <span v-else>{{ idx + 1 }}</span>
                    </div>
                    <span
                        class="text-sm font-medium hidden sm:inline"
                        :class="stepIndex >= idx ? 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-surface-500'"
                    >{{ s.label }}</span>
                </div>
                <div v-if="idx < steps.length - 1" class="flex-1 h-px bg-gray-200 dark:bg-surface-700 max-w-12"></div>
            </template>
        </div>

        <!-- ─── STEP 1: Source ─────────────────────────────────────────── -->
        <div v-if="stepIndex === 0" class="space-y-5">
            <!-- Mode selector -->
            <div class="flex gap-2">
                <button
                    v-for="mode in sourceModes"
                    :key="mode.value"
                    class="flex items-center gap-2 px-5 py-3 rounded-xl border-2 text-sm font-medium transition-all"
                    :class="sourceMode === mode.value
                        ? 'border-teal-500 bg-teal-50 dark:bg-teal-950/30 text-teal-700 dark:text-teal-300'
                        : 'border-gray-200 dark:border-surface-700 text-gray-600 dark:text-surface-400 hover:border-gray-300'"
                    @click="sourceMode = mode.value"
                >
                    <i :class="`pi ${mode.icon}`"></i>
                    {{ mode.label }}
                </button>
            </div>

            <!-- OCR path -->
            <div v-if="sourceMode === 'ocr'" class="bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl p-5 space-y-4">
                <!-- No API key warning -->
                <Message v-if="!ocrConfigured && ocrStatusChecked" severity="warn" :closable="false">
                    AI extraction needs <code class="font-mono text-xs bg-surface-100 dark:bg-surface-700 px-1 rounded">ANTHROPIC_API_KEY</code> configured on the server.
                    You can still enter the order manually.
                </Message>

                <template v-if="ocrConfigured">
                    <!-- Drop zone -->
                    <div
                        class="relative flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed px-6 py-12 transition-colors cursor-pointer"
                        :class="[
                            dragging
                                ? 'border-teal-400 bg-teal-50 dark:bg-teal-950/20'
                                : 'border-gray-300 dark:border-surface-600 hover:border-teal-400 hover:bg-gray-50 dark:hover:bg-surface-700/50',
                            extracting ? 'pointer-events-none opacity-70' : '',
                        ]"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="onDrop"
                        @click="!extracting && fileInput?.click()"
                    >
                        <template v-if="extracting">
                            <ProgressSpinner style="width: 48px; height: 48px" stroke-width="4" />
                            <p class="text-sm text-gray-500">Extracting data from <span class="font-semibold text-gray-700 dark:text-surface-200">{{ pendingFileName }}</span>…</p>
                        </template>
                        <template v-else>
                            <i class="pi pi-cloud-upload text-4xl text-teal-500"></i>
                            <div class="text-center">
                                <p class="text-sm font-medium text-gray-700 dark:text-surface-200">Drop a Delivery Order PDF here</p>
                                <p class="text-xs text-gray-400 mt-1">or click to browse — PDF only, max 10 MB</p>
                            </div>
                        </template>
                        <input
                            ref="fileInput"
                            type="file"
                            accept="application/pdf,.pdf"
                            class="hidden"
                            @change="onFileSelected"
                        />
                    </div>

                    <Message v-if="extractError" severity="error" :closable="true" @close="extractError = null">
                        {{ extractError }}
                    </Message>
                </template>

                <!-- Manual fallback link -->
                <div class="text-center pt-1">
                    <button class="text-xs text-gray-400 hover:text-teal-600 underline underline-offset-2" @click="switchToManual">
                        Skip PDF — enter manually instead
                    </button>
                </div>
            </div>

            <!-- Manual path -->
            <div v-else class="bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl p-5">
                <p class="text-sm text-gray-600 dark:text-surface-300 mb-4">Fill in the MBL and container details in the next step.</p>
                <Button label="Continue to Form" icon="pi pi-arrow-right" icon-pos="right" @click="goToReview" />
            </div>
        </div>

        <!-- ─── STEP 2: Review & edit ──────────────────────────────────── -->
        <div v-if="stepIndex === 1" class="space-y-6">
            <!-- Confidence banner (when prefilled from OCR) -->
            <Message v-if="ocrConfidence" severity="warn" :closable="true" @close="ocrConfidence = null">
                Extracted with <strong>{{ ocrConfidence }}</strong> confidence — review all fields before saving.
            </Message>

            <!-- MBL section -->
            <div class="bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl p-5">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-surface-200 mb-4 flex items-center gap-2">
                    <i class="pi pi-file text-teal-500"></i>
                    Master Bill of Lading
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-surface-300 mb-1">
                            MBL Number <span class="text-red-500">*</span>
                        </label>
                        <InputText
                            v-model="form.mbl_number"
                            placeholder="MSKU1234567890"
                            class="w-full font-mono"
                            size="small"
                            :invalid="submitted && !form.mbl_number"
                        />
                        <small v-if="submitted && !form.mbl_number" class="text-red-500 text-xs">Required</small>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-surface-300 mb-1">Carrier SCAC</label>
                        <InputText v-model="form.carrier_scac" placeholder="MAEU" class="w-full font-mono" size="small" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-surface-300 mb-1">Vessel Name</label>
                        <InputText v-model="form.vessel_name" placeholder="MSC OSCAR" class="w-full" size="small" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-surface-300 mb-1">Voyage Number</label>
                        <InputText v-model="form.voyage_number" placeholder="037W" class="w-full font-mono" size="small" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-surface-300 mb-1">POL</label>
                        <InputText v-model="form.pol" placeholder="CNSHA" class="w-full font-mono" size="small" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-surface-300 mb-1">POD</label>
                        <InputText v-model="form.pod" placeholder="USLAX" class="w-full font-mono" size="small" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-surface-300 mb-1">ETA</label>
                        <DatePicker
                            v-model="form.eta"
                            placeholder="Select date"
                            class="w-full"
                            size="small"
                            date-format="mm/dd/yy"
                            show-button-bar
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-surface-300 mb-1">ETD</label>
                        <DatePicker
                            v-model="form.etd"
                            placeholder="Select date"
                            class="w-full"
                            size="small"
                            date-format="mm/dd/yy"
                            show-button-bar
                        />
                    </div>
                    <div class="sm:col-span-2 md:col-span-3 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-surface-300 mb-1">Shipper</label>
                            <InputText v-model="form.shipper_name" placeholder="Shipper name" class="w-full" size="small" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-surface-300 mb-1">Consignee</label>
                            <InputText v-model="form.consignee_name" placeholder="Consignee name" class="w-full" size="small" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-surface-300 mb-1">Notify Party</label>
                            <InputText v-model="form.notify_party" placeholder="Notify party" class="w-full" size="small" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Containers section -->
            <div class="bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-surface-200 flex items-center gap-2">
                        <i class="pi pi-box text-teal-500"></i>
                        Containers
                        <span class="text-xs font-normal text-gray-400">({{ containers.length }})</span>
                    </h2>
                    <Button
                        label="Add Row"
                        icon="pi pi-plus"
                        size="small"
                        severity="secondary"
                        @click="addContainerRow"
                    />
                </div>

                <!-- Container rows -->
                <div v-if="containers.length > 0" class="space-y-2">
                    <!-- Header row -->
                    <div class="grid grid-cols-12 gap-2 px-1 mb-1">
                        <span class="col-span-4 text-xs font-medium text-gray-400 uppercase tracking-wide">Container #</span>
                        <span class="col-span-2 text-xs font-medium text-gray-400 uppercase tracking-wide">Type</span>
                        <span class="col-span-2 text-xs font-medium text-gray-400 uppercase tracking-wide">Size</span>
                        <span class="col-span-3 text-xs font-medium text-gray-400 uppercase tracking-wide">Weight (kg)</span>
                        <span class="col-span-1"></span>
                    </div>
                    <div
                        v-for="(row, idx) in containers"
                        :key="row._id"
                        class="grid grid-cols-12 gap-2 items-center"
                    >
                        <div class="col-span-4">
                            <InputText
                                v-model="row.container_number"
                                placeholder="MSCU1234567"
                                class="w-full font-mono"
                                size="small"
                                :invalid="submitted && !row.container_number"
                            />
                        </div>
                        <div class="col-span-2">
                            <InputText v-model="row.type" placeholder="GP" class="w-full" size="small" />
                        </div>
                        <div class="col-span-2">
                            <InputText v-model="row.size" placeholder="40" class="w-full" size="small" />
                        </div>
                        <div class="col-span-3">
                            <InputNumber
                                v-model="row.weight_kg"
                                placeholder="24000"
                                class="w-full"
                                size="small"
                                :use-grouping="false"
                                :min="0"
                                fluid
                            />
                        </div>
                        <div class="col-span-1 flex justify-center">
                            <Button
                                icon="pi pi-trash"
                                severity="danger"
                                text
                                rounded
                                size="small"
                                @click="removeContainerRow(idx)"
                            />
                        </div>
                    </div>
                </div>

                <div v-else class="py-8 text-center text-gray-400 dark:text-surface-500">
                    <i class="pi pi-box text-2xl mb-2 block"></i>
                    <p class="text-sm">No containers yet — click "Add Row" or upload a PDF to prefill.</p>
                </div>
            </div>

            <!-- Step 2 navigation -->
            <div class="flex items-center justify-between pt-2">
                <Button label="Back" icon="pi pi-arrow-left" severity="secondary" text @click="stepIndex = 0" />
                <Button label="Review & Create" icon="pi pi-arrow-right" icon-pos="right" @click="submitOrder" :loading="submitting" />
            </div>
        </div>

        <!-- ─── STEP 3: Success summary (shown after successful submit) ── -->
        <div v-if="stepIndex === 2" class="bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl p-8 text-center space-y-4">
            <div class="w-14 h-14 bg-teal-50 dark:bg-teal-950/30 rounded-full flex items-center justify-center mx-auto">
                <i class="pi pi-check-circle text-3xl text-teal-600"></i>
            </div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Order Created</h2>
            <p class="text-sm text-gray-500">
                MBL <span class="font-mono font-semibold text-gray-800 dark:text-surface-100">{{ createdMbl }}</span>
                with {{ createdContainerCount }} container{{ createdContainerCount !== 1 ? 's' : '' }} has been created.
            </p>
            <div class="flex items-center justify-center gap-3 pt-2">
                <Button label="View MBL" icon="pi pi-external-link" @click="navigateToResult" />
                <Button label="New Order" icon="pi pi-plus" severity="secondary" @click="resetWizard" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import dayjs from 'dayjs';

import Toast from 'primevue/toast';
import Message from 'primevue/message';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import DatePicker from 'primevue/datepicker';
import ProgressSpinner from 'primevue/progressspinner';

import api from '@/plugins/api';

// ─── Router / toast ────────────────────────────────────────────────────────
const router = useRouter();
const toast = useToast();

// ─── Steps ─────────────────────────────────────────────────────────────────
const steps = [
    { key: 'source', label: 'Source' },
    { key: 'review', label: 'Review & Edit' },
    { key: 'done',   label: 'Done' },
];
const stepIndex = ref(0);

// ─── Step 1 state ──────────────────────────────────────────────────────────
const sourceModes = [
    { value: 'ocr',    label: 'Upload Delivery Order (OCR)', icon: 'pi-cloud-upload' },
    { value: 'manual', label: 'Enter Manually',              icon: 'pi-pencil' },
];
const sourceMode     = ref('ocr');
const ocrConfigured  = ref(false);
const ocrStatusChecked = ref(false);
const dragging       = ref(false);
const extracting     = ref(false);
const extractError   = ref(null);
const pendingFileName = ref('');
const fileInput      = ref(null);

// ─── Step 2 form state ─────────────────────────────────────────────────────
const ocrConfidence = ref(null);   // 'high' | 'medium' | 'low' | null
const submitted     = ref(false);
const submitting    = ref(false);

const form = ref({
    mbl_number:    '',
    carrier_scac:  '',
    vessel_name:   '',
    voyage_number: '',
    pol:           '',
    pod:           '',
    eta:           null,
    etd:           null,
    shipper_name:  '',
    consignee_name: '',
    notify_party:  '',
});

let _rowId = 0;
const containers = ref([]);

// ─── Step 3 result state ───────────────────────────────────────────────────
const createdUuid           = ref(null);
const createdMbl            = ref('');
const createdContainerCount = ref(0);

// ─── Lifecycle ─────────────────────────────────────────────────────────────
onMounted(async () => {
    try {
        const res = await api.get('/documents/extract/status');
        ocrConfigured.value = res.data?.data?.configured ?? false;
    } catch {
        ocrConfigured.value = false;
    } finally {
        ocrStatusChecked.value = true;
    }
});

// ─── Helpers ───────────────────────────────────────────────────────────────
function addContainerRow(overrides = {}) {
    containers.value.push({
        _id:              ++_rowId,
        container_number: overrides.container_number ?? '',
        type:             overrides.type ?? '',
        size:             overrides.size ?? '',
        weight_kg:        overrides.weight_kg ?? null,
    });
}

function removeContainerRow(idx) {
    containers.value.splice(idx, 1);
}

function prefillFromOcr(data) {
    form.value.mbl_number     = data.mbl_number    ?? '';
    form.value.carrier_scac   = data.carrier_scac  ?? '';
    form.value.vessel_name    = data.vessel_name   ?? '';
    form.value.voyage_number  = data.voyage_number ?? '';
    form.value.pol            = data.pol           ?? '';
    form.value.pod            = data.pod           ?? '';
    form.value.eta            = data.eta  ? new Date(data.eta)  : null;
    form.value.etd            = data.etd  ? new Date(data.etd)  : null;
    form.value.shipper_name   = data.shipper       ?? '';
    form.value.consignee_name = data.consignee     ?? '';
    form.value.notify_party   = data.notify_party  ?? '';

    containers.value = [];
    const nums = data.container_numbers ?? [];
    if (nums.length > 0) {
        nums.forEach(cn => {
            addContainerRow({
                container_number: cn,
                type:      data.container_type ?? '',
                weight_kg: data.weight ? Number(data.weight) : null,
            });
        });
    } else {
        // Add one blank row so the user has something to fill in
        addContainerRow();
    }

    ocrConfidence.value = data.confidence ?? null;
}

function goToReview() {
    stepIndex.value = 1;
}

function switchToManual() {
    sourceMode.value = 'manual';
    goToReview();
}

// ─── OCR file handling ─────────────────────────────────────────────────────
async function processFile(file) {
    if (!file) return;
    if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
        extractError.value = 'Only PDF files are supported.';
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        extractError.value = 'File exceeds the 10 MB limit.';
        return;
    }

    pendingFileName.value = file.name;
    extractError.value    = null;
    extracting.value      = true;

    try {
        const formData = new FormData();
        formData.append('file', file);
        const res = await api.post('/documents/extract', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        const data = res.data?.data ?? res.data;
        prefillFromOcr(data);
        goToReview();
    } catch (err) {
        extractError.value = err.response?.data?.message ?? 'Failed to extract document data.';
    } finally {
        extracting.value = false;
        if (fileInput.value) fileInput.value.value = '';
    }
}

function onDrop(event) {
    dragging.value = false;
    const file = event.dataTransfer?.files?.[0];
    if (file) processFile(file);
}

function onFileSelected(event) {
    const file = event.target?.files?.[0];
    if (file) processFile(file);
    if (fileInput.value) fileInput.value.value = '';
}

// ─── Submit ────────────────────────────────────────────────────────────────
function buildPayload() {
    const mbl = {
        mbl_number:    form.value.mbl_number.trim(),
        carrier_scac:  form.value.carrier_scac.trim() || undefined,
        pol:           form.value.pol.trim() || undefined,
        pod:           form.value.pod.trim() || undefined,
        eta:           form.value.eta ? dayjs(form.value.eta).format('YYYY-MM-DD') : undefined,
        etd:           form.value.etd ? dayjs(form.value.etd).format('YYYY-MM-DD') : undefined,
        shipper_name:  form.value.shipper_name.trim()   || undefined,
        consignee_name: form.value.consignee_name.trim() || undefined,
        notify_party:  form.value.notify_party.trim()   || undefined,
    };

    const vesselName   = form.value.vessel_name.trim();
    const voyageNumber = form.value.voyage_number.trim();
    const vessel = (vesselName || voyageNumber)
        ? { vessel_name: vesselName || undefined, voyage_number: voyageNumber || undefined }
        : undefined;

    const containerList = containers.value.map(row => ({
        container_number: row.container_number.trim(),
        type:      row.type?.trim()    || undefined,
        size:      row.size?.trim()    || undefined,
        weight_kg: row.weight_kg ?? undefined,
    }));

    const payload = { mbl };
    if (vessel) payload.vessel = vessel;
    payload.containers = containerList;
    return payload;
}

async function submitOrder() {
    submitted.value = true;

    if (!form.value.mbl_number.trim()) {
        toast.add({ severity: 'warn', summary: 'Validation', detail: 'MBL number is required.', life: 4000 });
        return;
    }

    submitting.value = true;

    try {
        const res = await api.post('/orders/import', buildPayload());
        const result = res.data?.data ?? res.data;

        createdUuid.value           = result.uuid;
        createdMbl.value            = result.mbl_number ?? form.value.mbl_number;
        createdContainerCount.value = result.containers?.length ?? containers.value.length;

        toast.add({
            severity: 'success',
            summary: 'Order created',
            detail: `MBL ${createdMbl.value} with ${createdContainerCount.value} container${createdContainerCount.value !== 1 ? 's' : ''}`,
            life: 5000,
        });

        stepIndex.value = 2;

        // Navigate immediately if we have a UUID
        if (createdUuid.value) {
            router.push({ name: 'mbl-detail', params: { uuid: createdUuid.value } });
        }
    } catch (err) {
        const detail = err.response?.data?.message ?? 'Failed to create order. Please try again.';
        toast.add({ severity: 'error', summary: 'Error', detail, life: 6000 });
    } finally {
        submitting.value = false;
    }
}

// ─── Post-success navigation ───────────────────────────────────────────────
function navigateToResult() {
    if (createdUuid.value) {
        router.push({ name: 'mbl-detail', params: { uuid: createdUuid.value } });
    } else {
        router.push({ name: 'mbls' });
    }
}

function resetWizard() {
    stepIndex.value   = 0;
    sourceMode.value  = 'ocr';
    submitted.value   = false;
    ocrConfidence.value = null;
    extractError.value  = null;
    createdUuid.value   = null;
    createdMbl.value    = '';
    createdContainerCount.value = 0;
    form.value = {
        mbl_number: '', carrier_scac: '', vessel_name: '', voyage_number: '',
        pol: '', pod: '', eta: null, etd: null,
        shipper_name: '', consignee_name: '', notify_party: '',
    };
    containers.value = [];
}
</script>
