<template>
    <Dialog
        :visible="visible"
        @update:visible="$emit('update:visible', $event)"
        header="New Rail Shipment"
        modal
        :style="{ width: '520px' }"
        :pt="{ content: { class: 'pb-0' } }"
    >
        <form @submit.prevent="submit" class="space-y-4 pt-1 pb-4">
            <!-- Container selector -->
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium text-gray-700">Container <span class="text-red-500">*</span></label>
                <Select
                    v-model="form.container_uuid"
                    :options="containers"
                    option-label="container_number"
                    option-value="uuid"
                    placeholder="Search container..."
                    filter
                    filter-placeholder="Type container number..."
                    :loading="loadingContainers"
                    class="w-full"
                    :invalid="!!errors.container_uuid"
                    @filter="onContainerFilter"
                />
                <small v-if="errors.container_uuid" class="text-red-500 text-xs">{{ errors.container_uuid }}</small>
            </div>

            <!-- Rail carrier -->
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium text-gray-700">Rail Carrier <span class="text-red-500">*</span></label>
                <Select
                    v-model="form.rail_carrier"
                    :options="carrierOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="Select carrier"
                    class="w-full"
                    :invalid="!!errors.rail_carrier"
                    @change="onCarrierChange"
                />
                <small v-if="errors.rail_carrier" class="text-red-500 text-xs">{{ errors.rail_carrier }}</small>
            </div>

            <!-- Origin + Destination ramps in a row -->
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium text-gray-700">Origin Ramp <span class="text-red-500">*</span></label>
                    <Select
                        v-model="form.origin_ramp_uuid"
                        :options="filteredRamps"
                        option-label="display_label"
                        option-value="uuid"
                        placeholder="Origin"
                        filter
                        filter-placeholder="Search ramps..."
                        :loading="loadingRamps"
                        class="w-full"
                        :invalid="!!errors.origin_ramp_uuid"
                        :disabled="!form.rail_carrier"
                    />
                    <small v-if="errors.origin_ramp_uuid" class="text-red-500 text-xs">{{ errors.origin_ramp_uuid }}</small>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium text-gray-700">Destination Ramp <span class="text-red-500">*</span></label>
                    <Select
                        v-model="form.destination_ramp_uuid"
                        :options="filteredRamps"
                        option-label="display_label"
                        option-value="uuid"
                        placeholder="Destination"
                        filter
                        filter-placeholder="Search ramps..."
                        :loading="loadingRamps"
                        class="w-full"
                        :invalid="!!errors.destination_ramp_uuid"
                        :disabled="!form.rail_carrier"
                    />
                    <small v-if="errors.destination_ramp_uuid" class="text-red-500 text-xs">{{ errors.destination_ramp_uuid }}</small>
                </div>
            </div>

            <!-- Train ID + ETA in a row -->
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium text-gray-700">Train ID <span class="text-gray-400 font-normal">(optional)</span></label>
                    <InputText
                        v-model="form.train_id"
                        placeholder="e.g. BNSF-12345"
                        class="w-full font-mono"
                    />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium text-gray-700">ETA <span class="text-red-500">*</span></label>
                    <DatePicker
                        v-model="form.eta"
                        placeholder="Select date"
                        date-format="M d, yy"
                        :manual-input="false"
                        class="w-full"
                        :invalid="!!errors.eta"
                    />
                    <small v-if="errors.eta" class="text-red-500 text-xs">{{ errors.eta }}</small>
                </div>
            </div>

            <!-- Notes -->
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium text-gray-700">Notes <span class="text-gray-400 font-normal">(optional)</span></label>
                <Textarea
                    v-model="form.notes"
                    placeholder="Any additional notes..."
                    rows="3"
                    class="w-full resize-none"
                />
            </div>

            <!-- Error banner -->
            <div v-if="submitError" class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-3 py-2">
                <i class="pi pi-exclamation-triangle mr-1.5"></i>{{ submitError }}
            </div>
        </form>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button
                    label="Cancel"
                    outlined
                    size="small"
                    @click="$emit('update:visible', false)"
                />
                <Button
                    label="Create Shipment"
                    icon="pi pi-check"
                    size="small"
                    :loading="submitting"
                    @click="submit"
                />
            </div>
        </template>
    </Dialog>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import DatePicker from 'primevue/datepicker';
import Textarea from 'primevue/textarea';
import dayjs from 'dayjs';
import api from '@/plugins/api';
import { useRailStore } from '@/stores/rail';

const props = defineProps({
    visible: { type: Boolean, default: false },
});

const emit = defineEmits(['update:visible', 'created']);

const railStore = useRailStore();

const submitting = ref(false);
const submitError = ref(null);
const loadingContainers = ref(false);
const loadingRamps = ref(false);
const containers = ref([]);
const ramps = ref([]);

const form = reactive({
    container_uuid:       null,
    rail_carrier:         null,
    origin_ramp_uuid:     null,
    destination_ramp_uuid: null,
    train_id:             '',
    eta:                  null,
    notes:                '',
});

const errors = reactive({
    container_uuid:       null,
    rail_carrier:         null,
    origin_ramp_uuid:     null,
    destination_ramp_uuid: null,
    eta:                  null,
});

const carrierOptions = [
    { label: 'BNSF', value: 'BNSF' },
    { label: 'UP',   value: 'UP' },
    { label: 'CSX',  value: 'CSX' },
    { label: 'NS',   value: 'NS' },
    { label: 'CN',   value: 'CN' },
    { label: 'CP',   value: 'CP' },
    { label: 'KCS',  value: 'KCS' },
];

// Filter ramps by selected carrier
const filteredRamps = computed(() => {
    if (!form.rail_carrier) return ramps.value;
    return ramps.value.filter(r => r.carrier === form.rail_carrier);
});

function onCarrierChange() {
    // Clear ramp selections when carrier changes
    form.origin_ramp_uuid = null;
    form.destination_ramp_uuid = null;
}

let containerSearchTimeout = null;
function onContainerFilter(event) {
    clearTimeout(containerSearchTimeout);
    containerSearchTimeout = setTimeout(() => loadContainers(event.value), 350);
}

async function loadContainers(search = '') {
    loadingContainers.value = true;
    try {
        const resp = await api.get('/containers', { params: { search, per_page: 50 } });
        containers.value = resp.data.data || resp.data;
    } catch {
        containers.value = [];
    } finally {
        loadingContainers.value = false;
    }
}

async function loadRamps() {
    loadingRamps.value = true;
    try {
        // Use store ramps if already loaded, else fetch
        if (railStore.ramps.length) {
            ramps.value = railStore.ramps.map(r => ({
                ...r,
                display_label: `${r.code} — ${r.name || r.city}`,
            }));
        } else {
            await railStore.fetchRamps();
            ramps.value = railStore.ramps.map(r => ({
                ...r,
                display_label: `${r.code} — ${r.name || r.city}`,
            }));
        }
    } finally {
        loadingRamps.value = false;
    }
}

function validate() {
    let valid = true;
    errors.container_uuid = null;
    errors.rail_carrier = null;
    errors.origin_ramp_uuid = null;
    errors.destination_ramp_uuid = null;
    errors.eta = null;

    if (!form.container_uuid) {
        errors.container_uuid = 'Container is required.';
        valid = false;
    }
    if (!form.rail_carrier) {
        errors.rail_carrier = 'Rail carrier is required.';
        valid = false;
    }
    if (!form.origin_ramp_uuid) {
        errors.origin_ramp_uuid = 'Origin ramp is required.';
        valid = false;
    }
    if (!form.destination_ramp_uuid) {
        errors.destination_ramp_uuid = 'Destination ramp is required.';
        valid = false;
    }
    if (!form.eta) {
        errors.eta = 'ETA is required.';
        valid = false;
    }
    return valid;
}

function resetForm() {
    form.container_uuid = null;
    form.rail_carrier = null;
    form.origin_ramp_uuid = null;
    form.destination_ramp_uuid = null;
    form.train_id = '';
    form.eta = null;
    form.notes = '';
    submitError.value = null;
    Object.keys(errors).forEach(k => (errors[k] = null));
}

async function submit() {
    if (!validate()) return;
    submitting.value = true;
    submitError.value = null;
    try {
        const payload = {
            container_uuid:        form.container_uuid,
            rail_carrier:          form.rail_carrier,
            origin_ramp_uuid:      form.origin_ramp_uuid,
            destination_ramp_uuid: form.destination_ramp_uuid,
            train_id:              form.train_id || null,
            eta:                   form.eta ? dayjs(form.eta).format('YYYY-MM-DD') : null,
            notes:                 form.notes || null,
        };
        const result = await railStore.createShipment(payload);
        resetForm();
        emit('created', result);
    } catch (e) {
        const data = e?.response?.data;
        if (data?.errors) {
            // Map Laravel validation errors
            Object.entries(data.errors).forEach(([field, msgs]) => {
                if (field in errors) errors[field] = msgs[0];
            });
        } else {
            submitError.value = data?.message || 'Failed to create shipment. Please try again.';
        }
    } finally {
        submitting.value = false;
    }
}

// Load containers and ramps when dialog opens
watch(() => props.visible, (val) => {
    if (val) {
        loadContainers();
        loadRamps();
    } else {
        resetForm();
    }
});
</script>
