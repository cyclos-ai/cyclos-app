<template>
    <div>
        <PageHeader
            title="Document Upload"
            subtitle="Drop shipping documents to auto-extract tracking data"
        />

        <div class="max-w-3xl">
            <!-- Drop zone -->
            <DocumentDropZone @extracted="onExtracted" />

            <!-- Editable extracted data form -->
            <div v-if="extracted" class="mt-6 rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800">
                <div class="px-4 py-3 border-b border-surface-100 dark:border-surface-700">
                    <h2 class="text-sm font-semibold text-surface-800 dark:text-surface-100">Extracted Fields</h2>
                    <p class="text-xs text-surface-400 mt-0.5">Review and edit before creating a tracking request or container.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
                    <div>
                        <label class="block text-xs font-medium text-surface-500 mb-1">Container Numbers</label>
                        <InputText
                            v-model="form.container_numbers"
                            placeholder="MSCU1234567, HLCU7654321"
                            class="w-full text-sm"
                        />
                        <p class="text-xs text-surface-400 mt-0.5">Comma-separated</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-surface-500 mb-1">MBL Number</label>
                        <InputText v-model="form.mbl_number" placeholder="MAEU123456789" class="w-full text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-surface-500 mb-1">Carrier SCAC</label>
                        <InputText v-model="form.carrier_scac" placeholder="MAEU" class="w-full text-sm" maxlength="4" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-surface-500 mb-1">Carrier Name</label>
                        <InputText v-model="form.carrier_name" placeholder="Maersk" class="w-full text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-surface-500 mb-1">Port of Loading</label>
                        <InputText v-model="form.pol" placeholder="CNSHA" class="w-full text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-surface-500 mb-1">Port of Discharge</label>
                        <InputText v-model="form.pod" placeholder="USLAX" class="w-full text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-surface-500 mb-1">ETA</label>
                        <InputText v-model="form.eta" placeholder="YYYY-MM-DD" class="w-full text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-surface-500 mb-1">Vessel</label>
                        <InputText v-model="form.vessel_name" placeholder="MSC ANNA" class="w-full text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-surface-500 mb-1">Booking Number</label>
                        <InputText v-model="form.booking_number" placeholder="BK123456" class="w-full text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-surface-500 mb-1">Final Destination</label>
                        <InputText v-model="form.final_destination" placeholder="Los Angeles, CA" class="w-full text-sm" />
                    </div>
                </div>

                <Message v-if="actionError" severity="error" :closable="true" class="mx-4 mb-4" @close="actionError = ''">
                    {{ actionError }}
                </Message>
                <Message v-if="actionSuccess" severity="success" :closable="true" class="mx-4 mb-4" @close="actionSuccess = ''">
                    {{ actionSuccess }}
                </Message>

                <!-- Action buttons -->
                <div class="flex items-center gap-3 px-4 pb-4">
                    <Button
                        label="Create Tracking Request"
                        icon="pi pi-map-marker"
                        :loading="creatingTracking"
                        @click="createTrackingRequest"
                    />
                    <Button
                        label="Create Container"
                        icon="pi pi-box"
                        severity="secondary"
                        :loading="creatingContainer"
                        @click="createContainer"
                    />
                    <Button
                        label="Clear"
                        icon="pi pi-times"
                        text
                        severity="secondary"
                        @click="clearExtracted"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import PageHeader from '@/components/PageHeader.vue';
import DocumentDropZone from '@/components/documents/DocumentDropZone.vue';
import api from '@/plugins/api';

const extracted = ref(null);
const creatingTracking = ref(false);
const creatingContainer = ref(false);
const actionError = ref('');
const actionSuccess = ref('');

const form = reactive({
    container_numbers: '',
    mbl_number: '',
    carrier_scac: '',
    carrier_name: '',
    pol: '',
    pod: '',
    eta: '',
    vessel_name: '',
    booking_number: '',
    final_destination: '',
});

function onExtracted(data) {
    extracted.value = data;
    form.container_numbers  = (data.container_numbers ?? []).join(', ');
    form.mbl_number         = data.mbl_number        ?? '';
    form.carrier_scac       = data.carrier_scac      ?? '';
    form.carrier_name       = data.carrier_name      ?? '';
    form.pol                = data.pol               ?? '';
    form.pod                = data.pod               ?? '';
    form.eta                = data.eta               ?? '';
    form.vessel_name        = data.vessel_name       ?? '';
    form.booking_number     = data.booking_number    ?? '';
    form.final_destination  = data.final_destination ?? '';
}

async function createTrackingRequest() {
    const containers = form.container_numbers
        .split(',')
        .map(s => s.trim())
        .filter(Boolean);

    const referenceNumber = containers[0] || form.mbl_number || form.booking_number;

    if (!referenceNumber) {
        actionError.value = 'No container number, MBL, or booking number found to create a tracking request.';
        return;
    }

    creatingTracking.value = true;
    actionError.value = '';

    try {
        await api.post('/tracking-requests', {
            reference_number: referenceNumber,
            request_type:     containers[0] ? 'CONTAINER' : (form.mbl_number ? 'MBL' : 'BOOKING'),
            carrier_scac:     form.carrier_scac || null,
        });
        actionSuccess.value = `Tracking request created for ${referenceNumber}.`;
    } catch (err) {
        actionError.value = err.response?.data?.message || 'Failed to create tracking request.';
    } finally {
        creatingTracking.value = false;
    }
}

async function createContainer() {
    const containerNumbers = form.container_numbers
        .split(',')
        .map(s => s.trim())
        .filter(Boolean);

    if (!containerNumbers.length) {
        actionError.value = 'No container number found to create a container.';
        return;
    }

    creatingContainer.value = true;
    actionError.value = '';

    try {
        await api.post('/containers', {
            container_number: containerNumbers[0],
            carrier_scac:     form.carrier_scac     || null,
            mbl_number:       form.mbl_number       || null,
            booking_number:   form.booking_number   || null,
            pol:              form.pol              || null,
            pod:              form.pod              || null,
            eta:              form.eta              || null,
            vessel_name:      form.vessel_name      || null,
        });
        actionSuccess.value = `Container ${containerNumbers[0]} created successfully.`;
    } catch (err) {
        actionError.value = err.response?.data?.message || 'Failed to create container.';
    } finally {
        creatingContainer.value = false;
    }
}

function clearExtracted() {
    extracted.value = null;
    actionError.value = '';
    actionSuccess.value = '';
    Object.keys(form).forEach(k => { form[k] = ''; });
}
</script>
