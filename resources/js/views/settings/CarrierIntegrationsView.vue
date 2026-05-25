<template>
    <div>
        <PageHeader title="Carrier Integrations" subtitle="Connect your steamship line accounts for live tracking" />

        <!-- Carrier Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="carrier in store.carriers" :key="carrier.scac"
                class="bg-white rounded-xl shadow-sm border border-surface-200 overflow-hidden">

                <!-- Color bar -->
                <div class="h-2" :style="{ backgroundColor: carrierColor(carrier.scac) }"></div>

                <div class="p-5">
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="font-semibold text-surface-900">{{ carrier.name }}</h3>
                            <span class="text-xs font-mono text-surface-400">{{ carrier.scac }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full"
                                :class="carrier.connected ? 'bg-green-500' : 'bg-surface-300'"></span>
                            <span class="text-xs" :class="carrier.connected ? 'text-green-600' : 'text-surface-400'">
                                {{ carrier.connected ? 'Connected' : 'Not connected' }}
                            </span>
                        </div>
                    </div>

                    <!-- Last used -->
                    <p v-if="carrier.last_used_at" class="text-xs text-surface-400 mb-3">
                        Last used {{ formatDate(carrier.last_used_at) }}
                    </p>

                    <!-- Error -->
                    <Message v-if="carrier.last_error && carrier.connected" severity="warn" :closable="false" class="text-xs mb-3">
                        {{ carrier.last_error }}
                    </Message>

                    <!-- Actions -->
                    <div class="flex gap-2">
                        <Button v-if="!carrier.connected" label="Connect" icon="pi pi-link" size="small"
                            @click="openConfigure(carrier)" />
                        <template v-else>
                            <Button label="Configure" icon="pi pi-cog" size="small" outlined
                                @click="openConfigure(carrier)" />
                            <Button icon="pi pi-refresh" size="small" outlined severity="info"
                                :loading="testing === carrier.scac" @click="handleTest(carrier.scac)"
                                v-tooltip.top="'Test Connection'" />
                            <Button icon="pi pi-times" size="small" outlined severity="danger"
                                @click="handleDisconnect(carrier)" v-tooltip.top="'Disconnect'" />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Configure Dialog -->
        <Dialog v-model:visible="showDialog" :header="`Configure ${dialogCarrier?.name || ''}`"
            modal :style="{ width: '480px' }">
            <div class="space-y-4">
                <!-- Dynamic fields based on auth_type -->
                <div v-if="dialogCarrier?.auth_type === 'api_key'">
                    <label class="block text-sm font-medium text-surface-700 mb-1">API Key</label>
                    <Password v-model="credForm.api_key" class="w-full" input-class="w-full"
                        placeholder="Enter API key" :feedback="false" toggle-mask />
                </div>
                <div v-if="dialogCarrier?.auth_type === 'consumer_key'">
                    <label class="block text-sm font-medium text-surface-700 mb-1">Consumer Key</label>
                    <Password v-model="credForm.consumer_key" class="w-full" input-class="w-full"
                        placeholder="Enter consumer key" :feedback="false" toggle-mask />
                </div>
                <template v-if="dialogCarrier?.auth_type === 'oauth2'">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Client ID</label>
                        <Password v-model="credForm.client_id" class="w-full" input-class="w-full"
                            placeholder="Enter client ID" :feedback="false" toggle-mask />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Client Secret</label>
                        <Password v-model="credForm.client_secret" class="w-full" input-class="w-full"
                            placeholder="Enter client secret" :feedback="false" toggle-mask />
                    </div>
                </template>

                <!-- Environment -->
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Environment</label>
                    <Dropdown v-model="credForm.environment" :options="envOptions"
                        option-label="label" option-value="value" class="w-full" />
                </div>

                <!-- Custom API URL -->
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Custom API URL (optional)</label>
                    <InputText v-model="credForm.api_url" class="w-full" placeholder="https://api.example.com" />
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" text @click="showDialog = false" />
                <Button label="Save" icon="pi pi-check" :loading="saving" @click="handleSave" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Password from 'primevue/password';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import Message from 'primevue/message';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import PageHeader from '@/components/PageHeader.vue';
import { useCarrierIntegrationsStore } from '@/stores/carrierIntegrations';
import dayjs from 'dayjs';

const toast = useToast();
const confirm = useConfirm();
const store = useCarrierIntegrationsStore();

const showDialog = ref(false);
const dialogCarrier = ref(null);
const saving = ref(false);
const testing = ref(null);

const credForm = reactive({
    carrier_scac: '',
    auth_type: '',
    api_key: '',
    consumer_key: '',
    client_id: '',
    client_secret: '',
    environment: 'production',
    api_url: '',
});

const envOptions = [
    { label: 'Production', value: 'production' },
    { label: 'Sandbox', value: 'sandbox' },
];

const CARRIER_COLORS = {
    MSCU: '#002B5C',
    MAEU: '#00243D',
    CMDU: '#00205B',
    COSU: '#1A3668',
    HLCU: '#FF6600',
    ONEY: '#EE2C75',
    EGLV: '#00703C',
    HDMU: '#E31937',
    YMLU: '#00A5DF',
    ZIMU: '#C5A028',
    WHLC: '#005FAD',
    PILU: '#0055A5',
    SUDU: '#CC0000',
    KMTU: '#003087',
    XPRU: '#009FE3',
};

function carrierColor(scac) {
    return CARRIER_COLORS[scac] || '#6B7280';
}

function formatDate(dateStr) {
    return dateStr ? dayjs(dateStr).format('MMM D, YYYY h:mm A') : '';
}

function openConfigure(carrier) {
    dialogCarrier.value = carrier;
    credForm.carrier_scac = carrier.scac;
    credForm.auth_type = carrier.auth_type;
    credForm.api_key = '';
    credForm.consumer_key = '';
    credForm.client_id = '';
    credForm.client_secret = '';
    credForm.environment = carrier.environment || 'production';
    credForm.api_url = '';
    showDialog.value = true;
}

async function handleSave() {
    saving.value = true;
    try {
        await store.saveCredentials({ ...credForm });
        showDialog.value = false;
        toast.add({ severity: 'success', summary: 'Saved', detail: `${dialogCarrier.value.name} credentials saved`, life: 3000 });
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Error', detail: err.response?.data?.message || 'Failed to save', life: 5000 });
    } finally {
        saving.value = false;
    }
}

async function handleTest(scac) {
    testing.value = scac;
    try {
        const result = await store.testConnection(scac);
        if (result.success || result.data?.success) {
            toast.add({ severity: 'success', summary: 'Success', detail: 'Connection test passed', life: 3000 });
        } else {
            toast.add({ severity: 'warn', summary: 'Failed', detail: result.message || result.data?.message || 'Connection test failed', life: 5000 });
        }
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Error', detail: err.response?.data?.message || 'Test failed', life: 5000 });
    } finally {
        testing.value = null;
    }
}

function handleDisconnect(carrier) {
    confirm.require({
        message: `Disconnect ${carrier.name}? This will remove saved credentials.`,
        header: 'Confirm Disconnect',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await store.disconnect(carrier.scac);
                toast.add({ severity: 'info', summary: 'Disconnected', detail: `${carrier.name} has been disconnected`, life: 3000 });
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to disconnect', life: 5000 });
            }
        },
    });
}

onMounted(() => {
    store.fetchCarriers();
});
</script>
