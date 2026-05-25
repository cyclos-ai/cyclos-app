<template>
    <div>
        <PageHeader title="Carrier Contracts" subtitle="Configure demurrage and detention free days by carrier">
            <template #actions>
                <Button label="Add Contract" icon="pi pi-plus" size="small" @click="openAdd" />
            </template>
        </PageHeader>

        <DataTable
            :value="settingsStore.carrierContracts"
            :loading="settingsStore.loading"
            data-key="uuid"
            striped-rows
            class="text-sm"
            expandable-rows
            v-model:expanded-rows="expandedRows"
        >
            <Column expander style="width: 3rem" />
            <Column field="carrier_name" header="Carrier" sortable>
                <template #body="{ data }"><span class="font-medium">{{ data.carrier_name }}</span></template>
            </Column>
            <Column field="scac" header="SCAC" sortable>
                <template #body="{ data }"><span class="font-mono text-xs">{{ data.scac }}</span></template>
            </Column>
            <Column field="port" header="Port">
                <template #body="{ data }"><span class="text-xs">{{ data.port || 'All Ports' }}</span></template>
            </Column>
            <Column field="contract_type" header="Type">
                <template #body="{ data }">
                    <span class="text-xs capitalize px-2 py-0.5 rounded" :class="data.contract_type === 'custom' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'">
                        {{ data.contract_type }}
                    </span>
                </template>
            </Column>
            <Column field="demurrage_free_days" header="Demurrage Free Days">
                <template #body="{ data }"><span class="font-semibold">{{ data.demurrage_free_days }}</span></template>
            </Column>
            <Column field="detention_free_days" header="Detention Free Days">
                <template #body="{ data }"><span class="font-semibold">{{ data.detention_free_days }}</span></template>
            </Column>
            <Column field="effective_from" header="Effective">
                <template #body="{ data }">
                    <span class="text-xs text-gray-500">
                        {{ formatDate(data.effective_from) }} – {{ data.effective_to ? formatDate(data.effective_to) : 'Ongoing' }}
                    </span>
                </template>
            </Column>
            <Column header="">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-pencil" text size="small" rounded @click="openEdit(data)" />
                        <Button icon="pi pi-trash" text size="small" rounded severity="danger" @click="deleteContract(data)" />
                    </div>
                </template>
            </Column>

            <!-- Expanded: tiered rates table -->
            <template #expansion="{ data }">
                <div class="p-4 bg-gray-50">
                    <h4 class="text-xs font-semibold text-gray-600 mb-3">Tiered Rates</h4>
                    <div v-if="data.tiers?.length" class="overflow-x-auto">
                        <table class="text-xs w-full">
                            <thead>
                                <tr class="text-gray-500">
                                    <th class="text-left py-1 pr-4">From Day</th>
                                    <th class="text-left py-1 pr-4">To Day</th>
                                    <th class="text-left py-1 pr-4">Demurrage/Day</th>
                                    <th class="text-left py-1">Detention/Day</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="tier in data.tiers" :key="tier.from_day" class="border-t border-gray-100">
                                    <td class="py-1 pr-4">{{ tier.from_day }}</td>
                                    <td class="py-1 pr-4">{{ tier.to_day || '∞' }}</td>
                                    <td class="py-1 pr-4">${{ formatCurrency(tier.demurrage_rate) }}</td>
                                    <td class="py-1">${{ formatCurrency(tier.detention_rate) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-gray-400 text-xs">No tiered rates configured</p>
                </div>
            </template>

            <template #empty>
                <div class="py-10 text-center text-gray-400">
                    <i class="pi pi-file-contract text-3xl mb-2 block"></i>
                    <p class="mb-3">No carrier contracts defined</p>
                    <Button label="Add Contract" size="small" @click="openAdd" />
                </div>
            </template>
        </DataTable>

        <!-- Dialog -->
        <Dialog v-model:visible="showDialog" :header="editing ? 'Edit Contract' : 'Add Carrier Contract'" modal class="w-[560px]">
            <form class="space-y-4 pt-2">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Carrier Name</label>
                        <InputText v-model="form.carrier_name" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SCAC</label>
                        <InputText v-model="form.scac" class="w-full font-mono" placeholder="MSCU" maxlength="4" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Port (optional)</label>
                        <InputText v-model="form.port" class="w-full" placeholder="USLAX or leave blank for all" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contract Type</label>
                        <Select v-model="form.contract_type" :options="[{ label: 'Spot', value: 'spot' }, { label: 'Custom', value: 'custom' }]" option-label="label" option-value="value" class="w-full" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Demurrage Free Days</label>
                        <InputNumber v-model="form.demurrage_free_days" :min="0" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Detention Free Days</label>
                        <InputNumber v-model="form.detention_free_days" :min="0" class="w-full" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Effective From</label>
                        <DatePicker v-model="form.effective_from" date-format="yy-mm-dd" show-icon class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Effective To (optional)</label>
                        <DatePicker v-model="form.effective_to" date-format="yy-mm-dd" show-icon class="w-full" />
                    </div>
                </div>

                <!-- Tiers -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-medium text-gray-700">Rate Tiers</label>
                        <Button label="Add Tier" icon="pi pi-plus" text size="small" @click="addTier" />
                    </div>
                    <div class="space-y-2">
                        <div v-for="(tier, idx) in form.tiers" :key="idx" class="flex items-center gap-2">
                            <InputNumber v-model="tier.from_day" placeholder="From" :min="0" class="w-20" size="small" />
                            <span class="text-gray-400 text-sm">–</span>
                            <InputNumber v-model="tier.to_day" placeholder="To" :min="0" class="w-20" size="small" />
                            <InputNumber v-model="tier.demurrage_rate" placeholder="Dem $" :min="0" mode="currency" currency="USD" class="w-28" size="small" />
                            <InputNumber v-model="tier.detention_rate" placeholder="Det $" :min="0" mode="currency" currency="USD" class="w-28" size="small" />
                            <Button icon="pi pi-times" text size="small" rounded severity="secondary" @click="removeTier(idx)" />
                        </div>
                    </div>
                </div>
            </form>
            <template #footer>
                <Button label="Cancel" text @click="showDialog = false" />
                <Button :label="editing ? 'Save Changes' : 'Add Contract'" :loading="saving" @click="save" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import dayjs from 'dayjs';
import PageHeader from '@/components/PageHeader.vue';
import { useSettingsStore } from '@/stores/settings';

const settingsStore = useSettingsStore();
const confirm = useConfirm();
const toast = useToast();

const showDialog = ref(false);
const editing = ref(null);
const saving = ref(false);
const expandedRows = ref([]);

const form = reactive({
    carrier_name: '', scac: '', port: '', contract_type: 'custom',
    demurrage_free_days: 5, detention_free_days: 5,
    effective_from: null, effective_to: null, tiers: [],
});

function formatDate(d) { return d ? dayjs(d).format('MMM D, YYYY') : '—'; }
function formatCurrency(v) { return v ? Number(v).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '0.00'; }

function addTier() { form.tiers.push({ from_day: 0, to_day: null, demurrage_rate: 0, detention_rate: 0 }); }
function removeTier(idx) { form.tiers.splice(idx, 1); }

function openAdd() {
    editing.value = null;
    Object.assign(form, { carrier_name: '', scac: '', port: '', contract_type: 'custom', demurrage_free_days: 5, detention_free_days: 5, effective_from: null, effective_to: null, tiers: [] });
    showDialog.value = true;
}

function openEdit(contract) {
    editing.value = contract;
    Object.assign(form, { ...contract, tiers: JSON.parse(JSON.stringify(contract.tiers || [])) });
    showDialog.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await settingsStore.updateCarrierContract(editing.value.uuid, form);
        } else {
            await settingsStore.createCarrierContract(form);
        }
        toast.add({ severity: 'success', summary: 'Saved', detail: 'Carrier contract saved', life: 3000 });
        showDialog.value = false;
    } finally {
        saving.value = false;
    }
}

function deleteContract(contract) {
    confirm.require({
        message: `Delete contract for ${contract.carrier_name}?`,
        header: 'Delete Contract',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => settingsStore.deleteCarrierContract(contract.uuid),
    });
}

onMounted(() => settingsStore.fetchCarrierContracts());
</script>
