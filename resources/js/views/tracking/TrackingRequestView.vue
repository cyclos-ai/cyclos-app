<template>
    <div>
        <PageHeader title="Tracking Requests" subtitle="Create and manage shipment tracking">
            <template #actions>
                <Button label="New Tracking Request" icon="pi pi-plus" size="small" @click="showForm = true" />
            </template>
        </PageHeader>

        <!-- Create form dialog -->
        <Dialog v-model:visible="showForm" header="New Tracking Request" modal class="w-[480px]">
            <form @submit.prevent="submitRequest" class="space-y-4 pt-2">
                <!-- OCR auto-fill panel -->
                <div class="rounded-lg border border-dashed border-surface-300 bg-surface-50/50 p-3">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 text-sm font-medium text-teal-600"
                        @click="showOcr = !showOcr"
                    >
                        <i class="pi pi-file-import"></i>
                        Auto-fill from document (optional)
                        <i :class="showOcr ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" class="ml-auto text-xs"></i>
                    </button>
                    <div v-if="showOcr" class="mt-3">
                        <DocumentDropZone @extracted="onExtracted" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tracking Type</label>
                    <SelectButton
                        v-model="form.type"
                        :options="trackingTypes"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ typeLabel }} Number
                    </label>
                    <InputText v-model="form.reference_number" :placeholder="typePlaceholder" class="w-full" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Carrier (optional)</label>
                    <Select
                        v-model="form.scac"
                        :options="carrierOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Auto-detect carrier"
                        class="w-full"
                        show-clear
                    />
                    <small class="text-gray-400 text-xs mt-1">Leave blank to auto-detect from the reference number</small>
                </div>
                <div v-if="form.type === 'mbl' || form.type === 'container'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">POD (optional)</label>
                    <InputText v-model="form.pod" placeholder="USLAX" class="w-full" />
                </div>
                <Message v-if="formError" severity="error" :closable="false">{{ formError }}</Message>
            </form>
            <template #footer>
                <Button label="Cancel" text @click="showForm = false" />
                <Button label="Create Request" icon="pi pi-check" :loading="submitting" @click="submitRequest" />
            </template>
        </Dialog>

        <!-- Table -->
        <DataTable
            :value="trackingStore.trackingRequests"
            :loading="trackingStore.loading"
            data-key="uuid"
            striped-rows
            paginator
            :rows="25"
            class="text-sm"
        >
            <Column field="type" header="Type" sortable>
                <template #body="{ data }">
                    <span class="text-xs font-semibold uppercase text-gray-600 bg-gray-100 px-2 py-0.5 rounded">
                        {{ data.type }}
                    </span>
                </template>
            </Column>
            <Column field="reference_number" header="Reference Number" sortable>
                <template #body="{ data }">
                    <span class="font-mono font-semibold text-gray-900">{{ data.reference_number }}</span>
                </template>
            </Column>
            <Column field="scac" header="Carrier" sortable>
                <template #body="{ data }">
                    <span class="text-sm">{{ data.carrier_name || data.scac || '—' }}</span>
                </template>
            </Column>
            <Column field="status" header="Status" sortable>
                <template #body="{ data }">
                    <div class="flex items-center gap-2">
                        <span
                            class="w-2 h-2 rounded-full flex-shrink-0"
                            :class="{
                                'bg-green-500': data.status === 'active',
                                'bg-yellow-500': data.status === 'processing',
                                'bg-red-500': data.status === 'failed',
                                'bg-gray-400': data.status === 'inactive',
                            }"
                        ></span>
                        <span class="capitalize text-sm">{{ data.status }}</span>
                    </div>
                </template>
            </Column>
            <Column field="last_event" header="Last Event">
                <template #body="{ data }">
                    <span class="text-xs text-gray-600">{{ data.last_event || '—' }}</span>
                </template>
            </Column>
            <Column field="created_at" header="Created" sortable>
                <template #body="{ data }">
                    <span class="text-xs text-gray-500">{{ formatDate(data.created_at) }}</span>
                </template>
            </Column>
            <Column header="">
                <template #body="{ data }">
                    <div class="flex items-center gap-1">
                        <Button
                            v-if="data.status === 'failed'"
                            icon="pi pi-refresh"
                            text
                            size="small"
                            rounded
                            title="Retry"
                            @click="retryRequest(data)"
                        />
                        <Button
                            icon="pi pi-trash"
                            text
                            size="small"
                            rounded
                            severity="danger"
                            title="Delete"
                            @click="deleteRequest(data)"
                        />
                    </div>
                </template>
            </Column>

            <template #empty>
                <div class="py-10 text-center text-gray-400">
                    <i class="pi pi-map-marker text-3xl mb-2 block"></i>
                    <p class="mb-3">No tracking requests yet</p>
                    <Button label="Create First Request" size="small" @click="showForm = true" />
                </div>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Message from 'primevue/message';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import { useConfirm } from 'primevue/useconfirm';
import dayjs from 'dayjs';
import PageHeader from '@/components/PageHeader.vue';
import DocumentDropZone from '@/components/documents/DocumentDropZone.vue';
import { useTrackingStore } from '@/stores/tracking';
import api from '@/plugins/api';

const props = defineProps({ showForm: { type: Boolean, default: false } });

const trackingStore = useTrackingStore();
const confirm = useConfirm();

const showFormDialog = ref(props.showForm);
const showForm = computed({
    get: () => showFormDialog.value,
    set: (v) => { showFormDialog.value = v; },
});
const submitting = ref(false);
const formError = ref('');
const carrierOptions = ref([]);
const showOcr = ref(false);

const form = reactive({
    type: 'container',
    reference_number: '',
    scac: null,
    pod: '',
});

const trackingTypes = [
    { label: 'Container', value: 'container' },
    { label: 'MBL', value: 'mbl' },
    { label: 'Booking', value: 'booking' },
    { label: 'AWB', value: 'awb' },
];

const typeLabel = computed(() => trackingTypes.find(t => t.value === form.type)?.label || 'Reference');
const placeholderMap = { container: 'MSCU1234567', mbl: 'MAEU123456789', booking: 'BK123456789', awb: '123-45678901' };
const typePlaceholder = computed(() => placeholderMap[form.type] || '');

function onExtracted(data) {
    // Map OCR data to tracking form fields
    if (data.container_numbers?.length) {
        form.type = 'container';
        form.reference_number = data.container_numbers[0];
    } else if (data.mbl_number) {
        form.type = 'mbl';
        form.reference_number = data.mbl_number;
    } else if (data.booking_number) {
        form.type = 'booking';
        form.reference_number = data.booking_number;
    }
    if (data.carrier_scac) form.scac = data.carrier_scac;
    if (data.pod) form.pod = data.pod;
    showOcr.value = false;
    formError.value = '';
}

async function submitRequest() {
    if (!form.reference_number.trim()) { formError.value = 'Reference number is required'; return; }
    submitting.value = true;
    formError.value = '';
    try {
        await trackingStore.createTrackingRequest({ ...form });
        showFormDialog.value = false;
        Object.assign(form, { type: 'container', reference_number: '', scac: null, pod: '' });
    } catch (err) {
        formError.value = err.response?.data?.message || 'Failed to create tracking request.';
    } finally {
        submitting.value = false;
    }
}

async function retryRequest(data) {
    await trackingStore.retryTrackingRequest(data.uuid);
}

function deleteRequest(data) {
    confirm.require({
        message: `Delete tracking for ${data.reference_number}?`,
        header: 'Confirm Delete',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => trackingStore.deleteTrackingRequest(data.uuid),
    });
}

function formatDate(d) { return d ? dayjs(d).format('MMM D, YYYY') : '—'; }

onMounted(async () => {
    await trackingStore.fetchTrackingRequests();
    try {
        const resp = await api.get('/carriers');
        carrierOptions.value = (resp.data.data || resp.data).map(c => ({ label: `${c.name} (${c.scac})`, value: c.scac }));
    } catch {}
});
</script>
