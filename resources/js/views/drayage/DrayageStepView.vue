<template>
    <div v-if="container">
        <!-- Header -->
        <div class="flex items-start gap-3 mb-6">
            <button class="mt-1 text-gray-400 hover:text-gray-600 transition-colors" @click="$router.back()">
                <i class="pi pi-arrow-left"></i>
            </button>
            <PageHeader
                :title="`Container: ${container.container_number}`"
                subtitle="Drayage workflow management"
                class="flex-1 mb-0"
            />
        </div>

        <!-- Container info panel -->
        <div class="bg-white border border-gray-200 rounded-xl p-5 mb-6">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-4">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Container #</p>
                    <p class="font-mono font-semibold text-gray-900">{{ container.container_number }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">MBL</p>
                    <p class="font-mono text-sm text-gray-700">{{ container.mbl_number || '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">SCAC</p>
                    <p class="text-sm font-medium text-gray-700">{{ container.carrier_scac || container.scac || '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Type</p>
                    <p class="text-sm text-gray-700">{{ container.container_type || '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Vessel</p>
                    <p class="text-sm text-gray-700 truncate">{{ container.vessel_name || '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">ETA</p>
                    <p class="text-sm text-gray-700">{{ formatDate(container.eta) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Status</p>
                    <StatusBadge :status="drayage?.current_step || container.status" />
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left col: stepper + action button -->
            <div class="lg:col-span-1">
                <!-- Drayage stepper -->
                <div class="bg-white border border-gray-200 rounded-xl p-5 mb-5">
                    <h2 class="text-sm font-semibold text-gray-700 mb-5">Drayage Progress</h2>
                    <div class="relative">
                        <div
                            v-for="(step, index) in DRAYAGE_STEPS"
                            :key="step.value"
                            class="flex gap-3 relative"
                        >
                            <!-- Connector line -->
                            <div class="flex flex-col items-center">
                                <!-- Circle -->
                                <div
                                    class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 z-10 transition-all"
                                    :class="stepCircleClass(step.value, index)"
                                >
                                    <i v-if="isCompleted(index)" class="pi pi-check text-xs text-white"></i>
                                    <div
                                        v-else-if="isCurrent(index)"
                                        class="w-3 h-3 rounded-full bg-white animate-pulse"
                                    ></div>
                                </div>
                                <!-- Line below (not on last) -->
                                <div
                                    v-if="index < DRAYAGE_STEPS.length - 1"
                                    class="w-0.5 flex-1 min-h-[28px]"
                                    :class="isCompleted(index) ? 'bg-green-400' : 'border-l-2 border-dashed border-gray-200'"
                                ></div>
                            </div>

                            <!-- Step content -->
                            <div class="pb-5 flex-1 min-w-0">
                                <p
                                    class="text-sm leading-tight"
                                    :class="isCurrent(index)
                                        ? 'font-bold text-blue-700'
                                        : isCompleted(index)
                                            ? 'font-medium text-gray-700'
                                            : 'text-gray-400'"
                                >
                                    {{ step.label }}
                                </p>
                                <template v-if="stepEvent(step.value)">
                                    <p class="text-xs text-gray-500 mt-0.5">{{ formatDateTime(stepEvent(step.value).timestamp) }}</p>
                                    <p v-if="stepEvent(step.value).updated_by" class="text-xs text-gray-400">by {{ stepEvent(step.value).updated_by }}</p>
                                </template>
                                <p v-else-if="isCurrent(index)" class="text-xs text-blue-500 mt-0.5">Current step</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action button -->
                <div v-if="nextStep" class="bg-white border border-gray-200 rounded-xl p-5">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Next Action</h2>
                    <Button
                        :label="`Mark ${nextStep.label}`"
                        icon="pi pi-arrow-right"
                        icon-pos="right"
                        class="w-full"
                        @click="showStepDialog = true"
                    />
                </div>

                <div v-else-if="isFullyComplete" class="bg-green-50 border border-green-200 rounded-xl p-5 text-center">
                    <i class="pi pi-check-circle text-3xl text-green-500 mb-2 block"></i>
                    <p class="text-sm font-semibold text-green-800">Drayage Complete</p>
                    <p class="text-xs text-green-600 mt-1">Empty has been returned</p>
                </div>
            </div>

            <!-- Right col: info fields + event log -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Editable info fields -->
                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold text-gray-700">Drayage Details</h2>
                        <div class="flex gap-2">
                            <Button
                                v-if="!editingDetails"
                                label="Edit"
                                icon="pi pi-pencil"
                                size="small"
                                outlined
                                @click="startEdit"
                            />
                            <template v-else>
                                <Button label="Save" icon="pi pi-check" size="small" :loading="savingDetails" @click="saveDetails" />
                                <Button label="Cancel" size="small" outlined severity="secondary" @click="cancelEdit" />
                            </template>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs text-gray-500 font-medium block mb-1">Dray Carrier</label>
                            <InputText v-if="editingDetails" v-model="editForm.dray_carrier" size="small" class="w-full" />
                            <p v-else class="text-sm text-gray-800">{{ drayage?.dray_carrier || '—' }}</p>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 font-medium block mb-1">Driver Name</label>
                            <InputText v-if="editingDetails" v-model="editForm.driver_name" size="small" class="w-full" />
                            <p v-else class="text-sm text-gray-800">{{ drayage?.driver_name || '—' }}</p>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 font-medium block mb-1">Driver Phone</label>
                            <InputText v-if="editingDetails" v-model="editForm.driver_phone" size="small" class="w-full" />
                            <p v-else class="text-sm text-gray-800">{{ drayage?.driver_phone || '—' }}</p>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 font-medium block mb-1">Chassis #</label>
                            <InputText v-if="editingDetails" v-model="editForm.chassis_number" size="small" class="w-full" />
                            <p v-else class="text-sm font-mono text-gray-800">{{ drayage?.chassis_number || '—' }}</p>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 font-medium block mb-1">Seal #</label>
                            <InputText v-if="editingDetails" v-model="editForm.seal_number" size="small" class="w-full" />
                            <p v-else class="text-sm font-mono text-gray-800">{{ drayage?.seal_number || '—' }}</p>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 font-medium block mb-1">Appointment Date/Time</label>
                            <DatePicker
                                v-if="editingDetails"
                                v-model="editForm.appointment_datetime"
                                show-time
                                hour-format="12"
                                size="small"
                                class="w-full"
                            />
                            <p v-else class="text-sm text-gray-800">{{ formatDateTime(drayage?.appointment_datetime) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Event log -->
                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <h2 class="text-sm font-semibold text-gray-700 mb-4">Event Log</h2>
                    <DataTable
                        :value="events"
                        :loading="eventsLoading"
                        data-key="uuid"
                        paginator
                        :rows="10"
                        striped-rows
                        size="small"
                        class="text-sm"
                    >
                        <Column field="step" header="Step">
                            <template #body="{ data }">
                                <span class="text-xs font-medium">{{ stepLabel(data.step) }}</span>
                            </template>
                        </Column>
                        <Column field="updated_by" header="Updated By">
                            <template #body="{ data }">
                                <span class="text-sm">{{ data.updated_by || '—' }}</span>
                            </template>
                        </Column>
                        <Column field="timestamp" header="Timestamp" sortable>
                            <template #body="{ data }">
                                <span class="text-xs text-gray-600">{{ formatDateTime(data.timestamp) }}</span>
                            </template>
                        </Column>
                        <Column field="location" header="Location">
                            <template #body="{ data }">
                                <span class="text-xs text-gray-600">{{ data.location || '—' }}</span>
                            </template>
                        </Column>
                        <Column field="notes" header="Notes">
                            <template #body="{ data }">
                                <span class="text-xs text-gray-500 italic">{{ data.notes || '—' }}</span>
                            </template>
                        </Column>

                        <template #empty>
                            <div class="py-8 text-center text-gray-400">
                                <i class="pi pi-list text-2xl mb-2 block"></i>
                                <p class="text-sm">No events recorded</p>
                            </div>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- Step confirmation dialog -->
        <Dialog
            v-model:visible="showStepDialog"
            :header="`Mark ${nextStep?.label}`"
            modal
            :style="{ width: '440px' }"
            :draggable="false"
        >
            <div class="space-y-4 pt-1">
                <p class="text-sm text-gray-600">
                    You are about to advance the drayage status to
                    <strong class="text-gray-900">{{ nextStep?.label }}</strong>.
                </p>

                <div>
                    <label class="text-xs text-gray-500 font-medium block mb-1">Timestamp</label>
                    <DatePicker
                        v-model="stepForm.timestamp"
                        show-time
                        hour-format="12"
                        class="w-full"
                        size="small"
                    />
                </div>

                <div>
                    <label class="text-xs text-gray-500 font-medium block mb-1">Notes <span class="font-normal text-gray-400">(optional)</span></label>
                    <Textarea
                        v-model="stepForm.notes"
                        rows="3"
                        class="w-full"
                        placeholder="Add any relevant notes..."
                        auto-resize
                    />
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" outlined severity="secondary" size="small" @click="showStepDialog = false" />
                    <Button
                        :label="`Confirm: ${nextStep?.label}`"
                        icon="pi pi-check"
                        size="small"
                        :loading="submittingStep"
                        @click="submitStep"
                    />
                </div>
            </template>
        </Dialog>
    </div>

    <!-- Loading state -->
    <div v-else-if="loading" class="flex items-center justify-center py-24">
        <ProgressSpinner />
    </div>

    <!-- Not found state -->
    <div v-else class="text-center py-24 text-gray-400">
        <i class="pi pi-box text-4xl mb-3 block"></i>
        <p class="text-base">Drayage record not found</p>
        <Button label="Go Back" outlined class="mt-4" size="small" @click="$router.back()" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import DatePicker from 'primevue/datepicker';
import ProgressSpinner from 'primevue/progressspinner';
import dayjs from 'dayjs';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import api from '@/plugins/api';

const route = useRoute();
const toast = useToast();

// ----------------------------------------------------------------
// Drayage step definitions
// ----------------------------------------------------------------
const DRAYAGE_STEPS = [
    { value: 'pending',               label: 'Pending' },
    { value: 'tendered',              label: 'Tendered' },
    { value: 'confirmed',             label: 'Confirmed' },
    { value: 'dispatched',            label: 'Dispatched' },
    { value: 'at_terminal',           label: 'At Terminal' },
    { value: 'picked_up',             label: 'Picked Up' },
    { value: 'in_transit_delivery',   label: 'In Transit to Delivery' },
    { value: 'arrived_at_delivery',   label: 'Arrived at Delivery' },
    { value: 'delivering',            label: 'Delivering' },
    { value: 'delivered',             label: 'Delivered' },
    { value: 'empty_at_delivery',     label: 'Empty at Delivery' },
    { value: 'picked_up_empty',       label: 'Picked Up Empty' },
    { value: 'in_transit_return',     label: 'In Transit to Return' },
    { value: 'empty_returned',        label: 'Empty Returned' },
];

const STEP_LABEL_MAP = Object.fromEntries(DRAYAGE_STEPS.map(s => [s.value, s.label]));

// ----------------------------------------------------------------
// State
// ----------------------------------------------------------------
const loading = ref(false);
const container = ref(null);
const drayage = ref(null);
const events = ref([]);
const eventsLoading = ref(false);

// Step dialog
const showStepDialog = ref(false);
const submittingStep = ref(false);
const stepForm = ref({
    notes: '',
    timestamp: new Date(),
});

// Detail editing
const editingDetails = ref(false);
const savingDetails = ref(false);
const editForm = ref({
    dray_carrier:          '',
    driver_name:           '',
    driver_phone:          '',
    chassis_number:        '',
    seal_number:           '',
    appointment_datetime:  null,
});

// ----------------------------------------------------------------
// Computed
// ----------------------------------------------------------------
const currentStepIndex = computed(() => {
    const current = drayage.value?.current_step;
    if (!current) return -1;
    return DRAYAGE_STEPS.findIndex(s => s.value === current);
});

const nextStep = computed(() => {
    const idx = currentStepIndex.value;
    if (idx < 0 || idx >= DRAYAGE_STEPS.length - 1) return null;
    return DRAYAGE_STEPS[idx + 1];
});

const isFullyComplete = computed(() =>
    drayage.value?.current_step === 'empty_returned',
);

// ----------------------------------------------------------------
// Step helpers
// ----------------------------------------------------------------
function isCompleted(index) {
    return index < currentStepIndex.value;
}

function isCurrent(index) {
    return index === currentStepIndex.value;
}

function stepCircleClass(stepValue, index) {
    if (isCompleted(index)) return 'bg-green-500';
    if (isCurrent(index))   return 'bg-blue-600 ring-4 ring-blue-100';
    return 'bg-gray-200';
}

function stepEvent(stepValue) {
    return events.value.find(e => e.step === stepValue) || null;
}

function stepLabel(value) {
    return STEP_LABEL_MAP[value] || value;
}

// ----------------------------------------------------------------
// API calls
// ----------------------------------------------------------------
async function loadData() {
    loading.value = true;
    try {
        const resp = await api.get(`/drayage/${route.params.uuid}`);
        const data = resp.data?.data || resp.data;
        container.value = data.container || data;
        drayage.value   = data.drayage || data;
    } catch {
        container.value = null;
    } finally {
        loading.value = false;
    }
}

async function loadEvents() {
    eventsLoading.value = true;
    try {
        const resp = await api.get(`/drayage/${route.params.uuid}/events`);
        events.value = resp.data?.data || resp.data || [];
    } catch {
        events.value = [];
    } finally {
        eventsLoading.value = false;
    }
}

async function submitStep() {
    if (!nextStep.value) return;
    submittingStep.value = true;
    try {
        await api.patch(`/drayage/${route.params.uuid}/step`, {
            step:      nextStep.value.value,
            notes:     stepForm.value.notes || null,
            timestamp: stepForm.value.timestamp
                ? dayjs(stepForm.value.timestamp).toISOString()
                : dayjs().toISOString(),
        });

        toast.add({
            severity: 'success',
            summary:  'Step Updated',
            detail:   `Marked as ${nextStep.value.label}`,
            life:     3000,
        });

        showStepDialog.value = false;
        stepForm.value = { notes: '', timestamp: new Date() };

        await loadData();
        await loadEvents();
    } catch (err) {
        toast.add({
            severity: 'error',
            summary:  'Update Failed',
            detail:   err.response?.data?.message || 'Could not update drayage step',
            life:     4000,
        });
    } finally {
        submittingStep.value = false;
    }
}

function startEdit() {
    editForm.value = {
        dray_carrier:         drayage.value?.dray_carrier ?? '',
        driver_name:          drayage.value?.driver_name ?? '',
        driver_phone:         drayage.value?.driver_phone ?? '',
        chassis_number:       drayage.value?.chassis_number ?? '',
        seal_number:          drayage.value?.seal_number ?? '',
        appointment_datetime: drayage.value?.appointment_datetime
            ? new Date(drayage.value.appointment_datetime)
            : null,
    };
    editingDetails.value = true;
}

function cancelEdit() {
    editingDetails.value = false;
}

async function saveDetails() {
    savingDetails.value = true;
    try {
        const payload = { ...editForm.value };
        if (payload.appointment_datetime) {
            payload.appointment_datetime = dayjs(payload.appointment_datetime).toISOString();
        }
        const resp = await api.patch(`/drayage/${route.params.uuid}`, payload);
        drayage.value = { ...drayage.value, ...(resp.data?.data || resp.data) };
        editingDetails.value = false;
        toast.add({ severity: 'success', summary: 'Saved', detail: 'Drayage details updated', life: 3000 });
    } catch (err) {
        toast.add({
            severity: 'error',
            summary:  'Save Failed',
            detail:   err.response?.data?.message || 'Could not save details',
            life:     4000,
        });
    } finally {
        savingDetails.value = false;
    }
}

// ----------------------------------------------------------------
// Formatting
// ----------------------------------------------------------------
function formatDate(d) {
    return d ? dayjs(d).format('MMM D, YYYY') : '—';
}

function formatDateTime(d) {
    return d ? dayjs(d).format('MMM D, YYYY h:mm A') : '—';
}

onMounted(async () => {
    await Promise.all([loadData(), loadEvents()]);
});
</script>
