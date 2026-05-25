<template>
    <div>
        <PageHeader title="Custom Columns" subtitle="Define custom fields for containers">
            <template #actions>
                <Button label="Add Column" icon="pi pi-plus" size="small" @click="openAdd" />
            </template>
        </PageHeader>

        <DataTable
            :value="settingsStore.customColumns"
            :loading="settingsStore.loading"
            data-key="uuid"
            class="text-sm"
        >
            <Column field="name" header="Column Name" sortable>
                <template #body="{ data }">
                    <span class="font-medium text-gray-900">{{ data.name }}</span>
                </template>
            </Column>
            <Column field="key" header="Key">
                <template #body="{ data }">
                    <span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded">{{ data.key }}</span>
                </template>
            </Column>
            <Column field="type" header="Type">
                <template #body="{ data }">
                    <span class="capitalize text-sm">{{ data.type }}</span>
                </template>
            </Column>
            <Column field="required" header="Required">
                <template #body="{ data }">
                    <i :class="['pi text-sm', data.required ? 'pi-check text-green-500' : 'pi-minus text-gray-300']"></i>
                </template>
            </Column>
            <Column field="default_value" header="Default">
                <template #body="{ data }">
                    <span class="text-xs text-gray-500">{{ data.default_value || '—' }}</span>
                </template>
            </Column>
            <Column header="">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-pencil" text size="small" rounded @click="openEdit(data)" />
                        <Button icon="pi pi-trash" text size="small" rounded severity="danger" @click="deleteColumn(data)" />
                    </div>
                </template>
            </Column>
            <template #empty>
                <div class="py-10 text-center text-gray-400">
                    <i class="pi pi-sliders-v text-3xl mb-2 block"></i>
                    <p class="mb-3">No custom columns defined</p>
                    <Button label="Add First Column" size="small" @click="openAdd" />
                </div>
            </template>
        </DataTable>

        <!-- Add/Edit Dialog -->
        <Dialog v-model:visible="showDialog" :header="editing ? 'Edit Column' : 'Add Custom Column'" modal class="w-96">
            <form @submit.prevent="save" class="space-y-4 pt-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Column Name</label>
                    <InputText v-model="form.name" class="w-full" placeholder="e.g. Shipper Reference" @input="autoKey" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Key (auto-generated)</label>
                    <InputText v-model="form.key" class="w-full font-mono" placeholder="shipper_reference" />
                    <small class="text-gray-400 text-xs">Used as the field identifier in API responses</small>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <Select
                        v-model="form.type"
                        :options="typeOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                </div>
                <div v-if="form.type === 'select'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Options (comma-separated)</label>
                    <InputText v-model="form.options_raw" class="w-full" placeholder="Option 1, Option 2, Option 3" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Default Value</label>
                    <InputText v-model="form.default_value" class="w-full" placeholder="Optional" />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox v-model="form.required" input-id="required" binary />
                    <label for="required" class="text-sm text-gray-700 cursor-pointer">Required field</label>
                </div>
            </form>
            <template #footer>
                <Button label="Cancel" text @click="showDialog = false" />
                <Button :label="editing ? 'Save Changes' : 'Add Column'" :loading="saving" @click="save" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Checkbox from 'primevue/checkbox';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import PageHeader from '@/components/PageHeader.vue';
import { useSettingsStore } from '@/stores/settings';

const settingsStore = useSettingsStore();
const confirm = useConfirm();
const toast = useToast();

const showDialog = ref(false);
const editing = ref(null);
const saving = ref(false);
const form = reactive({ name: '', key: '', type: 'text', default_value: '', required: false, options_raw: '' });

const typeOptions = [
    { label: 'Text', value: 'text' },
    { label: 'Number', value: 'number' },
    { label: 'Date', value: 'date' },
    { label: 'Boolean (Yes/No)', value: 'boolean' },
    { label: 'Dropdown', value: 'select' },
];

function autoKey() {
    if (!editing.value) {
        form.key = form.name.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
    }
}

function openAdd() {
    editing.value = null;
    Object.assign(form, { name: '', key: '', type: 'text', default_value: '', required: false, options_raw: '' });
    showDialog.value = true;
}

function openEdit(col) {
    editing.value = col;
    Object.assign(form, { ...col, options_raw: (col.options || []).join(', ') });
    showDialog.value = true;
}

async function save() {
    if (!form.name || !form.key) return;
    saving.value = true;
    const data = {
        ...form,
        options: form.type === 'select' ? form.options_raw.split(',').map(o => o.trim()).filter(Boolean) : [],
    };
    try {
        if (editing.value) {
            await settingsStore.updateCustomColumn(editing.value.uuid, data);
        } else {
            await settingsStore.createCustomColumn(data);
        }
        toast.add({ severity: 'success', summary: 'Saved', detail: 'Custom column saved', life: 3000 });
        showDialog.value = false;
    } finally {
        saving.value = false;
    }
}

function deleteColumn(col) {
    confirm.require({
        message: `Delete column "${col.name}"? This will remove all data stored in this column.`,
        header: 'Delete Column',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => settingsStore.deleteCustomColumn(col.uuid),
    });
}

onMounted(() => settingsStore.fetchCustomColumns());
</script>
