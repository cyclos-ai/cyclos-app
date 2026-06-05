<template>
    <div>
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="text-2xl font-bold text-gray-900">Scheduled Drops</h1>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Compile a container drop list, assign a drayage carrier, preview and send as Excel.
                    </p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <Button
                        v-if="batchId"
                        label="Export Excel"
                        icon="pi pi-file-excel"
                        severity="secondary"
                        size="small"
                        :loading="exporting"
                        @click="exportExcel"
                    />
                    <Button
                        v-if="batchId"
                        label="Send to Carrier"
                        icon="pi pi-send"
                        size="small"
                        :loading="sending"
                        @click="confirmSend"
                    />
                </div>
            </div>
        </div>

        <Toast position="bottom-right" />
        <ConfirmDialog />

        <!-- ─── STEP 1 + 2: Build the list ─────────────────────────────── -->
        <div v-if="!batchId" class="space-y-6">
            <!-- Mode toggle -->
            <div class="flex gap-1 bg-gray-100 p-1 rounded-lg w-fit">
                <button
                    v-for="mode in addModes"
                    :key="mode.value"
                    class="px-4 py-1.5 rounded-md text-sm font-medium transition-colors"
                    :class="addMode === mode.value
                        ? 'bg-white text-gray-900 shadow-sm'
                        : 'text-gray-500 hover:text-gray-700'"
                    @click="addMode = mode.value"
                >
                    <i :class="`pi ${mode.icon} mr-1.5 text-xs`"></i>
                    {{ mode.label }}
                </button>
            </div>

            <!-- ── Mode A: Select containers ── -->
            <div v-if="addMode === 'select'" class="bg-white border border-gray-200 rounded-xl p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">Select Containers</h2>
                <div class="flex items-center gap-3 mb-4">
                    <span class="relative flex-1 max-w-xs">
                        <i class="pi pi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                        <InputText
                            v-model="containerSearch"
                            placeholder="Search containers..."
                            size="small"
                            class="pl-8 w-full"
                            @input="debouncedContainerSearch"
                        />
                    </span>
                    <span class="text-xs text-gray-400">{{ selectedContainers.length }} selected</span>
                </div>
                <DataTable
                    v-model:selection="selectedContainers"
                    :value="availableContainers"
                    :loading="containersLoading"
                    data-key="uuid"
                    paginator
                    :rows="10"
                    :rows-per-page-options="[10, 25, 50]"
                    size="small"
                    striped-rows
                    class="text-sm"
                >
                    <Column selection-mode="multiple" header-style="width: 3rem" />
                    <Column field="container_number" header="Container #" sortable>
                        <template #body="{ data }">
                            <span class="font-mono font-semibold text-gray-900">{{ data.container_number }}</span>
                        </template>
                    </Column>
                    <Column field="vessel_name" header="Vessel" sortable>
                        <template #body="{ data }">
                            <span class="text-xs text-gray-600">{{ data.vessel_name || '—' }}</span>
                        </template>
                    </Column>
                    <Column field="eta" header="ETA" sortable>
                        <template #body="{ data }">
                            <span class="text-xs text-gray-600">{{ formatDate(data.eta) }}</span>
                        </template>
                    </Column>
                    <Column field="type" header="Type" sortable>
                        <template #body="{ data }">
                            <span class="text-xs text-gray-600">{{ data.type || '—' }}</span>
                        </template>
                    </Column>
                    <Column field="carrier_scac" header="SCAC" sortable>
                        <template #body="{ data }">
                            <span class="text-xs font-mono text-gray-600">{{ data.carrier_scac || '—' }}</span>
                        </template>
                    </Column>
                    <template #empty>
                        <div class="py-10 text-center text-gray-400">
                            <i class="pi pi-box text-3xl mb-2 block"></i>
                            <p class="text-sm">No containers found</p>
                        </div>
                    </template>
                </DataTable>
            </div>

            <!-- ── Mode B: Manual add ── -->
            <div v-else class="bg-white border border-gray-200 rounded-xl p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">Add Container Manually</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Container Number <span class="text-red-500">*</span></label>
                        <InputText v-model="manualForm.container_number" placeholder="MSCU1234567" class="w-full" size="small" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Ocean SCAC</label>
                        <InputText v-model="manualForm.ocean_scac" placeholder="MAEU" class="w-full" size="small" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Container Type</label>
                        <InputText v-model="manualForm.container_type" placeholder="40HC" class="w-full" size="small" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Vessel ETA</label>
                        <DatePicker v-model="manualForm.vessel_eta" placeholder="Select date" class="w-full" size="small" date-format="mm/dd/yy" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Mother Vessel</label>
                        <InputText v-model="manualForm.mother_vessel" placeholder="Vessel name" class="w-full" size="small" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Dem LFD</label>
                        <DatePicker v-model="manualForm.dem_lfd" placeholder="Select date" class="w-full" size="small" date-format="mm/dd/yy" />
                    </div>
                </div>
                <Message v-if="manualError" severity="error" :closable="false" class="mb-3">{{ manualError }}</Message>
                <Button label="Add Row" icon="pi pi-plus" size="small" :loading="addingManual" @click="addManualRow" />

                <!-- Manual rows preview -->
                <div v-if="manualRows.length" class="mt-4">
                    <DataTable :value="manualRows" size="small" striped-rows class="text-sm">
                        <Column field="container_number" header="Container #">
                            <template #body="{ data }">
                                <span class="font-mono font-semibold text-gray-900">{{ data.container_number }}</span>
                            </template>
                        </Column>
                        <Column field="ocean_scac" header="Ocean SCAC" />
                        <Column field="container_type" header="Type" />
                        <Column header="">
                            <template #body="{ data }">
                                <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="removeManualRow(data)" />
                            </template>
                        </Column>
                        <template #empty>
                            <span class="text-gray-400 text-sm">No rows added yet</span>
                        </template>
                    </DataTable>
                </div>
            </div>

            <!-- ── Shared drop details ── -->
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">Drop Details</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <!-- Drayage carrier -->
                    <div class="col-span-2 md:col-span-1">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Drayage Carrier <span class="text-red-500">*</span></label>
                        <Select
                            v-if="drayageCarriers.length"
                            v-model="dropForm.drayage_carrier_id"
                            :options="drayageCarriers"
                            option-label="name"
                            option-value="id"
                            placeholder="Select carrier"
                            class="w-full"
                            size="small"
                            show-clear
                            @change="onCarrierChange"
                        />
                        <InputText
                            v-else
                            v-model="dropForm.drayage_carrier_name"
                            placeholder="Carrier name"
                            class="w-full"
                            size="small"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Carrier Email</label>
                        <InputText v-model="dropForm.carrier_email" placeholder="dispatch@carrier.com" class="w-full" size="small" type="email" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">DC Code</label>
                        <InputText v-model="dropForm.dc_code" placeholder="DC001" class="w-full" size="small" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">DC Name</label>
                        <InputText v-model="dropForm.dc_name" placeholder="Distribution Center" class="w-full" size="small" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Estimated Drop Date</label>
                        <DatePicker v-model="dropForm.estimated_drop_date" placeholder="Select date" class="w-full" size="small" date-format="mm/dd/yy" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Terminal Pick Up</label>
                        <InputText v-model="dropForm.terminal_pickup" placeholder="LBCT / APM..." class="w-full" size="small" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Requested Stack</label>
                        <InputText v-model="dropForm.requested_stack" placeholder="A / B / C..." class="w-full" size="small" />
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Dray Notes</label>
                        <Textarea v-model="dropForm.dray_notes" placeholder="Any notes for the carrier..." rows="2" class="w-full" auto-resize size="small" />
                    </div>
                </div>

                <Message v-if="buildError" severity="error" :closable="false" class="mt-4">{{ buildError }}</Message>

                <div class="flex items-center gap-3 mt-5 pt-4 border-t border-gray-100">
                    <Button
                        label="Build Drop List"
                        icon="pi pi-list"
                        :loading="building"
                        :disabled="addMode === 'select' ? selectedContainers.length === 0 : manualRows.length === 0"
                        @click="buildDropList"
                    />
                    <span v-if="addMode === 'select' && selectedContainers.length === 0" class="text-xs text-gray-400">
                        Select at least one container above
                    </span>
                    <span v-else-if="addMode === 'manual' && manualRows.length === 0" class="text-xs text-gray-400">
                        Add at least one container row above
                    </span>
                </div>
            </div>
        </div>

        <!-- ─── STEP 3: Preview & edit ─────────────────────────────────── -->
        <div v-else>
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <h2 class="text-base font-semibold text-gray-800">
                        Draft — {{ dropRows.length }} row{{ dropRows.length !== 1 ? 's' : '' }}
                    </h2>
                    <span
                        v-if="batchSentAt"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700"
                    >
                        <i class="pi pi-check text-xs"></i> Sent {{ formatDate(batchSentAt) }}
                    </span>
                </div>
                <Button
                    label="Start Over"
                    icon="pi pi-refresh"
                    severity="secondary"
                    outlined
                    size="small"
                    @click="resetToBuilder"
                />
            </div>

            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <DataTable
                    :value="dropRows"
                    :loading="dropsLoading"
                    data-key="id"
                    scrollable
                    scroll-height="calc(100vh - 340px)"
                    size="small"
                    striped-rows
                    row-hover
                    class="text-xs scheduled-drops-table"
                >
                    <!-- Drayage_Carrier -->
                    <Column field="drayage_carrier_name" header="Drayage_Carrier" style="min-width:140px">
                        <template #body="{ data }">
                            <span class="text-xs font-medium text-gray-800">{{ data.drayage_carrier_name || '—' }}</span>
                        </template>
                    </Column>

                    <!-- DC Code -->
                    <Column field="dc_code" header="DC Code" style="min-width:90px">
                        <template #body="{ data }">
                            <span class="text-xs font-mono text-gray-700">{{ data.dc_code || '—' }}</span>
                        </template>
                    </Column>

                    <!-- DC Name -->
                    <Column field="dc_name" header="DC Name" style="min-width:140px">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-700">{{ data.dc_name || '—' }}</span>
                        </template>
                    </Column>

                    <!-- Vessel Eta (auto-filled, read-only) -->
                    <Column field="vessel_eta" header="Vessel Eta" style="min-width:120px">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-600">{{ formatDate(data.vessel_eta) }}</span>
                        </template>
                    </Column>

                    <!-- Mother Vessel (auto-filled, read-only) -->
                    <Column field="mother_vessel" header="Mother Vessel" style="min-width:140px">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-600">{{ data.mother_vessel || '—' }}</span>
                        </template>
                    </Column>

                    <!-- Estimated Drop Date (editable) -->
                    <Column field="estimated_drop_date" header="Estimated Drop Date" style="min-width:160px">
                        <template #body="{ data }">
                            <EditableCell
                                :row-id="data.id"
                                field="estimated_drop_date"
                                :value="data.estimated_drop_date"
                                type="date"
                                cell-class="bg-green-50 text-green-800"
                                @save="onCellSave"
                            />
                        </template>
                    </Column>

                    <!-- Container_Number -->
                    <Column field="container_number" header="Container_Number" style="min-width:140px">
                        <template #body="{ data }">
                            <span class="font-mono font-semibold text-gray-900">{{ data.container_number || '—' }}</span>
                        </template>
                    </Column>

                    <!-- Terminal Pick Up (editable) -->
                    <Column field="terminal_pickup" header="Terminal Pick Up" style="min-width:150px">
                        <template #body="{ data }">
                            <EditableCell
                                :row-id="data.id"
                                field="terminal_pickup"
                                :value="data.terminal_pickup"
                                type="text"
                                placeholder="Set terminal..."
                                @save="onCellSave"
                            />
                        </template>
                    </Column>

                    <!-- Ocean SCAC (auto-filled, read-only) -->
                    <Column field="ocean_scac" header="Ocean SCAC" style="min-width:110px">
                        <template #body="{ data }">
                            <span class="text-xs font-mono text-gray-600">{{ data.ocean_scac || '—' }}</span>
                        </template>
                    </Column>

                    <!-- Dem LFD (auto-filled, read-only) -->
                    <Column field="dem_lfd" header="Dem LFD" style="min-width:110px">
                        <template #body="{ data }">
                            <span
                                class="text-xs font-medium"
                                :class="lfdUrgencyClass(data.dem_lfd)"
                            >
                                {{ formatDate(data.dem_lfd) }}
                            </span>
                        </template>
                    </Column>

                    <!-- Container Type (auto-filled, read-only) -->
                    <Column field="container_type" header="Container Type" style="min-width:120px">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-600">{{ data.container_type || '—' }}</span>
                        </template>
                    </Column>

                    <!-- Requested Stack (editable) -->
                    <Column field="requested_stack" header="Requested Stack" style="min-width:140px">
                        <template #body="{ data }">
                            <EditableCell
                                :row-id="data.id"
                                field="requested_stack"
                                :value="data.requested_stack"
                                type="text"
                                placeholder="A / B..."
                                @save="onCellSave"
                            />
                        </template>
                    </Column>

                    <!-- Dray Notes (editable) -->
                    <Column field="dray_notes" header="Dray Notes" style="min-width:160px">
                        <template #body="{ data }">
                            <EditableCell
                                :row-id="data.id"
                                field="dray_notes"
                                :value="data.dray_notes"
                                type="text"
                                placeholder="Add note..."
                                @save="onCellSave"
                            />
                        </template>
                    </Column>

                    <!-- Delete row -->
                    <Column header="" style="width:3rem">
                        <template #body="{ data }">
                            <Button
                                icon="pi pi-trash"
                                text
                                rounded
                                size="small"
                                severity="danger"
                                :loading="deletingId === data.id"
                                @click="deleteRow(data)"
                            />
                        </template>
                    </Column>

                    <template #empty>
                        <div class="py-16 text-center text-gray-400">
                            <i class="pi pi-list text-4xl mb-3 block"></i>
                            <p class="text-sm font-medium">No drop rows</p>
                        </div>
                    </template>
                </DataTable>
            </div>

            <!-- Send to Carrier section -->
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-5">
                <h3 class="text-sm font-semibold text-blue-800 mb-3">Send to Carrier</h3>
                <div class="flex items-center gap-3 flex-wrap">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-blue-700">Carrier Email</label>
                        <InputText
                            v-model="carrierEmail"
                            placeholder="dispatch@carrier.com"
                            size="small"
                            type="email"
                            class="w-64"
                        />
                    </div>
                    <div class="flex items-center gap-2 mt-4">
                        <Button
                            label="Export Excel"
                            icon="pi pi-file-excel"
                            severity="secondary"
                            size="small"
                            outlined
                            :loading="exporting"
                            @click="exportExcel"
                        />
                        <Button
                            label="Send to Carrier"
                            icon="pi pi-send"
                            size="small"
                            :loading="sending"
                            :disabled="!carrierEmail"
                            @click="confirmSend"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, defineComponent } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import Message from 'primevue/message';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { debounce } from 'lodash-es';
import dayjs from 'dayjs';
import api from '@/plugins/api';

// ─── Inline-editable cell sub-component ─────────────────────────────────────
const EditableCell = defineComponent({
    name: 'EditableCell',
    components: { InputText, DatePicker },
    props: {
        rowId:     { type: [String, Number], required: true },
        field:     { type: String, required: true },
        value:     { type: [String, Number, null], default: null },
        type:      { type: String, default: 'text' }, // text | date
        placeholder: { type: String, default: '—' },
        cellClass: { type: String, default: '' },
    },
    emits: ['save'],
    setup(props, { emit }) {
        const editing  = ref(false);
        const localVal = ref(props.value);

        function startEdit() {
            localVal.value = props.value;
            editing.value  = true;
        }

        function commit() {
            if (localVal.value !== props.value) {
                emit('save', { id: props.rowId, field: props.field, value: localVal.value });
            }
            editing.value = false;
        }

        function onKeydown(e) {
            if (e.key === 'Enter') commit();
            if (e.key === 'Escape') { editing.value = false; }
        }

        const displayValue = computed(() => {
            if (!props.value) return null;
            if (props.type === 'date') return dayjs(props.value).format('MM/DD/YY');
            return props.value;
        });

        return { editing, localVal, startEdit, commit, onKeydown, displayValue };
    },
    template: `
        <div :class="cellClass" class="min-h-[22px] rounded px-1 py-0.5 -mx-1">
            <template v-if="editing">
                <InputText
                    v-if="type === 'text'"
                    v-model="localVal"
                    size="small"
                    class="w-full text-xs h-6"
                    autofocus
                    @blur="commit"
                    @keydown="onKeydown"
                />
                <DatePicker
                    v-else
                    v-model="localVal"
                    size="small"
                    class="w-full text-xs"
                    date-format="mm/dd/yy"
                    autofocus
                    @blur="commit"
                    @keydown="onKeydown"
                    @date-select="commit"
                />
            </template>
            <span
                v-else
                class="cursor-pointer text-xs hover:underline hover:text-blue-700"
                :class="displayValue ? 'text-gray-800' : 'text-gray-400 italic'"
                @click="startEdit"
            >
                {{ displayValue || placeholder }}
            </span>
        </div>
    `,
});

// ─── Main component logic ────────────────────────────────────────────────────
const toast   = useToast();
const confirm = useConfirm();

// Step state
const addMode         = ref('select');  // 'select' | 'manual'
const batchId         = ref(null);
const batchSentAt     = ref(null);

const addModes = [
    { label: 'Select Containers', value: 'select', icon: 'pi-table' },
    { label: 'Manual Entry',      value: 'manual', icon: 'pi-pencil' },
];

// ── Step 1 — Select containers ──
const availableContainers = ref([]);
const containersLoading   = ref(false);
const selectedContainers  = ref([]);
const containerSearch     = ref('');

async function loadContainers(search = '') {
    containersLoading.value = true;
    try {
        const resp = await api.get('/containers', { params: { q: search || undefined, page_size: 100 } });
        availableContainers.value = resp.data?.data ?? resp.data ?? [];
    } catch {
        availableContainers.value = [];
    } finally {
        containersLoading.value = false;
    }
}

const debouncedContainerSearch = debounce(() => loadContainers(containerSearch.value), 350);

// ── Step 1 — Manual add ──
const manualForm = reactive({
    container_number: '',
    ocean_scac:       '',
    container_type:   '',
    vessel_eta:       null,
    mother_vessel:    '',
    dem_lfd:          null,
});
const manualRows    = ref([]);
const manualError   = ref('');
const addingManual  = ref(false);
const manualBatchId = ref(null);   // shared batch for manually-added rows (set from first row's server-generated batch_id)

async function addManualRow() {
    if (!manualForm.container_number.trim()) {
        manualError.value = 'Container number is required.';
        return;
    }
    addingManual.value = true;
    manualError.value  = '';
    try {
        const payload = {
            container_number: manualForm.container_number.trim(),
            ocean_scac:       manualForm.ocean_scac || null,
            container_type:   manualForm.container_type || null,
            vessel_eta:       manualForm.vessel_eta   ? dayjs(manualForm.vessel_eta).format('YYYY-MM-DD') : null,
            mother_vessel:    manualForm.mother_vessel || null,
            dem_lfd:          manualForm.dem_lfd       ? dayjs(manualForm.dem_lfd).format('YYYY-MM-DD') : null,
            // keep all manual rows in one batch so Export/Send work
            batch_id:         manualBatchId.value || undefined,
        };
        const resp = await api.post('/scheduled-drops', payload);
        const row  = resp.data?.data ?? resp.data;
        if (!manualBatchId.value) manualBatchId.value = row?.batch_id ?? null;
        manualRows.value.push(row);
        // Reset form
        Object.assign(manualForm, {
            container_number: '', ocean_scac: '', container_type: '',
            vessel_eta: null, mother_vessel: '', dem_lfd: null,
        });
    } catch (err) {
        manualError.value = err.response?.data?.message || 'Failed to add row.';
    } finally {
        addingManual.value = false;
    }
}

async function removeManualRow(row) {
    try {
        await api.delete(`/scheduled-drops/${row.id}`);
        manualRows.value = manualRows.value.filter(r => r.id !== row.id);
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not remove row', life: 3000 });
    }
}

// ── Step 2 — Drayage carriers ──
const drayageCarriers = ref([]);

async function loadDrayageCarriers() {
    try {
        const resp = await api.get('/drayage-carriers');
        drayageCarriers.value = resp.data?.data ?? resp.data ?? [];
    } catch {
        // If endpoint doesn't exist, fall back to free-text input (empty array keeps v-else branch)
        drayageCarriers.value = [];
    }
}

// ── Step 2 — Drop details form ──
const dropForm = reactive({
    drayage_carrier_id:   null,
    drayage_carrier_name: '',
    carrier_email:        '',
    dc_code:              '',
    dc_name:              '',
    estimated_drop_date:  null,
    terminal_pickup:      '',
    requested_stack:      '',
    dray_notes:           '',
});
const carrierEmail = ref('');

function onCarrierChange(event) {
    const carrier = drayageCarriers.value.find(c => c.id === event.value);
    if (carrier) {
        dropForm.drayage_carrier_name = carrier.name;
        if (carrier.email) carrierEmail.value = carrier.email;
    }
}

// ── Build drop list ──
const building  = ref(false);
const buildError = ref('');

async function buildDropList() {
    const carrierName = dropForm.drayage_carrier_id
        ? (drayageCarriers.value.find(c => c.id === dropForm.drayage_carrier_id)?.name ?? '')
        : dropForm.drayage_carrier_name;

    if (!carrierName.trim()) {
        buildError.value = 'Drayage carrier is required.';
        return;
    }

    building.value  = true;
    buildError.value = '';
    try {
        if (addMode.value === 'select') {
            const payload = {
                container_ids:       selectedContainers.value.map(c => c.uuid),
                drayage_carrier_id:  dropForm.drayage_carrier_id ?? undefined,
                dc_code:             dropForm.dc_code || null,
                dc_name:             dropForm.dc_name || null,
                estimated_drop_date: dropForm.estimated_drop_date
                    ? dayjs(dropForm.estimated_drop_date).format('YYYY-MM-DD')
                    : null,
                terminal_pickup:     dropForm.terminal_pickup || null,
                requested_stack:     dropForm.requested_stack || null,
                dray_notes:          dropForm.dray_notes || null,
            };
            const resp      = await api.post('/scheduled-drops/from-containers', payload);
            const result    = resp.data?.data ?? resp.data;
            batchId.value   = result.batch_id;
            dropRows.value  = result.drops ?? [];
        } else {
            // Manual rows were already created individually; collect their batch
            if (manualRows.value.length === 0) {
                buildError.value = 'Add at least one container row first.';
                return;
            }
            // Use the batch_id from the first manual row if set, otherwise fetch
            const firstBatch = manualRows.value[0].batch_id;
            if (firstBatch) {
                batchId.value  = firstBatch;
                dropRows.value = manualRows.value;
            } else {
                // Fallback: just show manual rows as preview without a batch
                batchId.value  = 'manual';
                dropRows.value = manualRows.value;
            }
        }
        if (dropForm.carrier_email) carrierEmail.value = dropForm.carrier_email;
    } catch (err) {
        buildError.value = err.response?.data?.message || 'Failed to build drop list.';
    } finally {
        building.value = false;
    }
}

// ── Step 3 — Preview table ──
const dropRows    = ref([]);
const dropsLoading = ref(false);
const deletingId  = ref(null);

async function onCellSave({ id, field, value }) {
    try {
        const payload = {
            [field]: value instanceof Date
                ? dayjs(value).format('YYYY-MM-DD')
                : value,
        };
        const resp = await api.put(`/scheduled-drops/${id}`, payload);
        const updated = resp.data?.data ?? resp.data;
        const idx = dropRows.value.findIndex(r => r.id === id);
        if (idx !== -1) {
            dropRows.value[idx] = { ...dropRows.value[idx], ...updated, [field]: payload[field] };
        }
        toast.add({ severity: 'success', summary: 'Saved', detail: `${field.replace(/_/g, ' ')} updated`, life: 1800 });
    } catch {
        toast.add({ severity: 'error', summary: 'Save Failed', detail: 'Could not save changes', life: 3000 });
    }
}

async function deleteRow(row) {
    deletingId.value = row.id;
    try {
        await api.delete(`/scheduled-drops/${row.id}`);
        dropRows.value = dropRows.value.filter(r => r.id !== row.id);
        toast.add({ severity: 'success', summary: 'Removed', detail: 'Row removed from list', life: 2000 });
        if (dropRows.value.length === 0) {
            resetToBuilder();
        }
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not delete row', life: 3000 });
    } finally {
        deletingId.value = null;
    }
}

function resetToBuilder() {
    batchId.value          = null;
    batchSentAt.value      = null;
    dropRows.value         = [];
    selectedContainers.value = [];
    manualRows.value       = [];
    manualBatchId.value    = null;
    buildError.value       = '';
    Object.assign(dropForm, {
        drayage_carrier_id: null, drayage_carrier_name: '', carrier_email: '',
        dc_code: '', dc_name: '', estimated_drop_date: null,
        terminal_pickup: '', requested_stack: '', dray_notes: '',
    });
}

// ── Step 4 — Export & send ──
const exporting = ref(false);
const sending   = ref(false);

async function exportExcel() {
    if (!batchId.value) return;
    exporting.value = true;
    try {
        const resp = await api.get('/scheduled-drops/export', {
            params: { batch: batchId.value },
            responseType: 'blob',
        });
        const url = URL.createObjectURL(resp.data);
        const a   = document.createElement('a');
        a.href     = url;
        a.download = 'Scheduled_Drops.xlsx';
        a.click();
        URL.revokeObjectURL(url);
    } catch {
        toast.add({ severity: 'error', summary: 'Export Failed', detail: 'Could not generate Excel file', life: 4000 });
    } finally {
        exporting.value = false;
    }
}

function confirmSend() {
    if (!carrierEmail.value) {
        toast.add({ severity: 'warn', summary: 'Email required', detail: 'Enter a carrier email address before sending', life: 3000 });
        return;
    }
    confirm.require({
        message: `Send ${dropRows.value.length} drop row${dropRows.value.length !== 1 ? 's' : ''} to ${carrierEmail.value}?`,
        header:  'Send to Carrier',
        icon:    'pi pi-send',
        acceptLabel: 'Send',
        rejectLabel: 'Cancel',
        accept: sendToCarrier,
    });
}

async function sendToCarrier() {
    sending.value = true;
    try {
        const resp   = await api.post('/scheduled-drops/send', {
            batch_id:     batchId.value,
            carrier_email: carrierEmail.value,
        });
        const result = resp.data?.data ?? resp.data;
        batchSentAt.value = new Date().toISOString();
        const carrierName = dropForm.drayage_carrier_name
            || drayageCarriers.value.find(c => c.id === dropForm.drayage_carrier_id)?.name
            || carrierEmail.value;
        toast.add({
            severity: 'success',
            summary:  'Scheduled drops sent',
            detail:   `Scheduled drops sent to ${carrierName} (${result.count ?? dropRows.value.length} rows)`,
            life:     5000,
        });
    } catch (err) {
        toast.add({
            severity: 'error',
            summary:  'Send Failed',
            detail:   err.response?.data?.message || 'Could not email the carrier',
            life:     4000,
        });
    } finally {
        sending.value = false;
    }
}

// ── Formatting helpers ──
function formatDate(d) {
    return d ? dayjs(d).format('MM/DD/YY') : '—';
}

function lfdUrgencyClass(lfd) {
    if (!lfd) return 'text-gray-400';
    const days = dayjs(lfd).diff(dayjs(), 'day');
    if (days < 0)  return 'text-red-600';
    if (days <= 2) return 'text-orange-600';
    if (days <= 5) return 'text-yellow-600';
    return 'text-gray-700';
}

onMounted(async () => {
    await Promise.allSettled([loadContainers(), loadDrayageCarriers()]);
});
</script>

<style scoped>
:deep(.scheduled-drops-table .p-datatable-thead th) {
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
    vertical-align: middle;
}

:deep(.scheduled-drops-table .p-datatable-tbody td) {
    padding-top: 0.375rem;
    padding-bottom: 0.375rem;
}

.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.2s ease;
}
.slide-down-enter-from,
.slide-down-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
