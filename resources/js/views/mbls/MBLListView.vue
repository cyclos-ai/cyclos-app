<template>
    <div>
        <PageHeader title="Master Bills of Lading">
            <template #actions>
                <DataExport @export="() => {}" />
                <Button label="New MBL" icon="pi pi-plus" size="small" @click="showCreateDialog = true" />
            </template>
        </PageHeader>

        <!-- Create MBL Dialog -->
        <Dialog v-model:visible="showCreateDialog" header="New Master Bill of Lading" modal class="w-[560px]">
            <form @submit.prevent="submitCreateMbl" class="space-y-4 pt-2">
                <!-- OCR auto-fill panel -->
                <div class="rounded-lg border border-dashed border-surface-300 bg-surface-50/50 p-3">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 text-sm font-medium text-teal-600"
                        @click="showMblOcr = !showMblOcr"
                    >
                        <i class="pi pi-file-import"></i>
                        Auto-fill from document (optional)
                        <i :class="showMblOcr ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" class="ml-auto text-xs"></i>
                    </button>
                    <div v-if="showMblOcr" class="mt-3">
                        <DocumentDropZone @extracted="onMblExtracted" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">MBL Number <span class="text-red-500">*</span></label>
                        <InputText v-model="mblForm.mbl_number" placeholder="MAEU123456789" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Carrier SCAC</label>
                        <InputText v-model="mblForm.carrier_scac" placeholder="MAEU" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vessel</label>
                        <InputText v-model="mblForm.vessel_name" placeholder="Vessel name" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">POL</label>
                        <InputText v-model="mblForm.pol" placeholder="CNSHA" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">POD</label>
                        <InputText v-model="mblForm.pod" placeholder="USLAX" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ETA</label>
                        <DatePicker v-model="mblForm.eta" placeholder="Select date" class="w-full" date-format="mm/dd/yy" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Voyage Number</label>
                        <InputText v-model="mblForm.voyage_number" placeholder="123W" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Shipper</label>
                        <InputText v-model="mblForm.shipper_name" placeholder="Shipper company" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Consignee</label>
                        <InputText v-model="mblForm.consignee_name" placeholder="Consignee company" class="w-full" />
                    </div>
                </div>
                <Message v-if="createMblError" severity="error" :closable="false">{{ createMblError }}</Message>
            </form>
            <template #footer>
                <Button label="Cancel" text @click="showCreateDialog = false" />
                <Button label="Create MBL" icon="pi pi-check" :loading="creatingMbl" @click="submitCreateMbl" />
            </template>
        </Dialog>

        <DataTable :value="items" :loading="loading" data-key="uuid" striped-rows paginator :rows="25" class="text-sm">
            <Column field="mbl_number" header="MBL Number" sortable>
                <template #body="{ data }">
                    <router-link :to="{ name: 'mbl-detail', params: { uuid: data.uuid } }" class="font-mono font-semibold text-blue-600 hover:text-blue-800">{{ data.mbl_number }}</router-link>
                </template>
            </Column>
            <Column field="carrier_name" header="Carrier" sortable />
            <Column field="vessel_name" header="Vessel" sortable />
            <Column field="voyage_number" header="Voyage" />
            <Column field="pol_name" header="POL" sortable />
            <Column field="pod_name" header="POD" sortable />
            <Column field="etd" header="ETD" sortable>
                <template #body="{ data }"><span class="text-xs">{{ formatDate(data.etd) }}</span></template>
            </Column>
            <Column field="eta" header="ETA" sortable>
                <template #body="{ data }"><span class="text-xs">{{ formatDate(data.eta) }}</span></template>
            </Column>
            <Column field="container_count" header="Containers">
                <template #body="{ data }"><span class="font-semibold text-blue-600">{{ data.container_count || 0 }}</span></template>
            </Column>
            <template #empty>
                <div class="py-10 text-center text-gray-400">
                    <i class="pi pi-file text-3xl mb-2 block"></i>
                    <p>No MBLs found</p>
                </div>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import DatePicker from 'primevue/datepicker';
import Message from 'primevue/message';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import dayjs from 'dayjs';
import PageHeader from '@/components/PageHeader.vue';
import DataExport from '@/components/DataExport.vue';
import DocumentDropZone from '@/components/documents/DocumentDropZone.vue';
import api from '@/plugins/api';

const items = ref([]);
const loading = ref(false);

// Create MBL dialog
const showCreateDialog = ref(false);
const showMblOcr = ref(false);
const creatingMbl = ref(false);
const createMblError = ref('');
const mblForm = reactive({
    mbl_number: '',
    carrier_scac: '',
    vessel_name: '',
    voyage_number: '',
    pol: '',
    pod: '',
    eta: null,
    shipper_name: '',
    consignee_name: '',
});

function onMblExtracted(data) {
    if (data.mbl_number) mblForm.mbl_number = data.mbl_number;
    if (data.carrier_scac) mblForm.carrier_scac = data.carrier_scac;
    if (data.vessel_name) mblForm.vessel_name = data.vessel_name;
    if (data.voyage_number) mblForm.voyage_number = data.voyage_number;
    if (data.pol) mblForm.pol = data.pol;
    if (data.pod) mblForm.pod = data.pod;
    if (data.eta) mblForm.eta = new Date(data.eta);
    if (data.shipper) mblForm.shipper_name = data.shipper;
    if (data.consignee) mblForm.consignee_name = data.consignee;
    showMblOcr.value = false;
    createMblError.value = '';
}

async function submitCreateMbl() {
    if (!mblForm.mbl_number.trim()) {
        createMblError.value = 'MBL number is required.';
        return;
    }
    creatingMbl.value = true;
    createMblError.value = '';
    try {
        const payload = { ...mblForm };
        if (payload.eta) payload.eta = dayjs(payload.eta).format('YYYY-MM-DD');
        await api.post('/mbls', payload);
        showCreateDialog.value = false;
        Object.assign(mblForm, {
            mbl_number: '', carrier_scac: '', vessel_name: '', voyage_number: '',
            pol: '', pod: '', eta: null, shipper_name: '', consignee_name: '',
        });
        showMblOcr.value = false;
        await loadMbls();
    } catch (err) {
        createMblError.value = err.response?.data?.message || 'Failed to create MBL.';
    } finally {
        creatingMbl.value = false;
    }
}

function formatDate(d) { return d ? dayjs(d).format('MMM D, YYYY') : '—'; }

async function loadMbls() {
    loading.value = true;
    try { const r = await api.get('/mbls'); items.value = r.data.data || r.data; }
    catch { items.value = []; }
    finally { loading.value = false; }
}

onMounted(loadMbls);
</script>
