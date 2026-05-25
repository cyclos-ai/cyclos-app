<template>
    <div>
        <PageHeader title="Webhooks" subtitle="Receive real-time event notifications">
            <template #actions>
                <Button label="Add Webhook" icon="pi pi-plus" size="small" @click="openAdd" />
            </template>
        </PageHeader>

        <div class="space-y-4">
            <div
                v-for="webhook in settingsStore.webhooks"
                :key="webhook.uuid"
                class="bg-white border border-gray-200 rounded-xl p-5"
            >
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" :class="webhook.active ? 'bg-green-500' : 'bg-gray-300'"></div>
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 truncate">{{ webhook.url }}</p>
                            <div class="flex flex-wrap gap-1 mt-1.5">
                                <span
                                    v-for="event in webhook.events"
                                    :key="event"
                                    class="text-xs bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded"
                                >
                                    {{ event }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0 ml-4">
                        <Button label="Test" icon="pi pi-send" size="small" outlined @click="testWebhook(webhook)" :loading="testing === webhook.uuid" />
                        <Button icon="pi pi-pencil" text size="small" rounded @click="openEdit(webhook)" />
                        <Button icon="pi pi-trash" text size="small" rounded severity="danger" @click="deleteWebhook(webhook)" />
                    </div>
                </div>

                <!-- Delivery logs -->
                <div v-if="logs[webhook.uuid]" class="mt-4 border-t border-gray-100 pt-4">
                    <h4 class="text-xs font-semibold text-gray-500 mb-2">Recent Deliveries</h4>
                    <div class="space-y-1.5">
                        <div
                            v-for="log in logs[webhook.uuid].slice(0, 5)"
                            :key="log.id"
                            class="flex items-center gap-3 text-xs"
                        >
                            <span :class="['font-semibold', log.response_code >= 200 && log.response_code < 300 ? 'text-green-600' : 'text-red-600']">
                                {{ log.response_code }}
                            </span>
                            <span class="text-gray-500 flex-1 truncate">{{ log.event }}</span>
                            <span class="text-gray-400">{{ dayjs(log.created_at).fromNow() }}</span>
                        </div>
                    </div>
                </div>
                <button
                    v-else
                    class="mt-3 text-xs text-blue-600 hover:text-blue-700"
                    @click="loadLogs(webhook)"
                >
                    View delivery logs
                </button>
            </div>

            <div v-if="!settingsStore.loading && !settingsStore.webhooks.length" class="text-center py-16 text-gray-400">
                <i class="pi pi-link text-4xl mb-3 block"></i>
                <p class="font-medium text-gray-600">No webhooks configured</p>
                <Button label="Add First Webhook" class="mt-4" @click="openAdd" />
            </div>
        </div>

        <!-- Dialog -->
        <Dialog v-model:visible="showDialog" :header="editing ? 'Edit Webhook' : 'Add Webhook'" modal class="w-[500px]">
            <form class="space-y-4 pt-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Endpoint URL</label>
                    <InputText v-model="form.url" placeholder="https://your-server.com/webhook" class="w-full" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Secret (optional)</label>
                    <Password v-model="form.secret" placeholder="Used to verify webhook signatures" class="w-full" input-class="w-full" :feedback="false" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Events to Subscribe</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label
                            v-for="event in availableEvents"
                            :key="event"
                            class="flex items-center gap-2 cursor-pointer text-sm"
                        >
                            <Checkbox v-model="form.events" :value="event" />
                            {{ event }}
                        </label>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <ToggleSwitch v-model="form.active" />
                    <label class="text-sm text-gray-700">Active</label>
                </div>
            </form>
            <template #footer>
                <Button label="Cancel" text @click="showDialog = false" />
                <Button :label="editing ? 'Save Changes' : 'Add Webhook'" :loading="saving" @click="save" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Checkbox from 'primevue/checkbox';
import ToggleSwitch from 'primevue/toggleswitch';
import Dialog from 'primevue/dialog';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import PageHeader from '@/components/PageHeader.vue';
import { useSettingsStore } from '@/stores/settings';

dayjs.extend(relativeTime);

const settingsStore = useSettingsStore();
const confirm = useConfirm();
const toast = useToast();

const showDialog = ref(false);
const editing = ref(null);
const saving = ref(false);
const testing = ref(null);
const logs = ref({});

const form = reactive({ url: '', secret: '', events: [], active: true });

const availableEvents = [
    'container.status_changed', 'container.arrived', 'container.delivered',
    'demurrage.alarm', 'demurrage.lfd_approaching',
    'invoice.created', 'invoice.status_changed',
    'tracking.failed', 'tracking.updated',
    'vessel.arrived', 'vessel.departed',
];

function openAdd() {
    editing.value = null;
    Object.assign(form, { url: '', secret: '', events: [], active: true });
    showDialog.value = true;
}

function openEdit(webhook) {
    editing.value = webhook;
    Object.assign(form, { url: webhook.url, secret: '', events: [...(webhook.events || [])], active: webhook.active });
    showDialog.value = true;
}

async function save() {
    if (!form.url) return;
    saving.value = true;
    try {
        if (editing.value) {
            await settingsStore.updateWebhook(editing.value.uuid, form);
        } else {
            await settingsStore.createWebhook(form);
        }
        toast.add({ severity: 'success', summary: 'Saved', detail: 'Webhook saved', life: 3000 });
        showDialog.value = false;
    } finally {
        saving.value = false;
    }
}

async function testWebhook(webhook) {
    testing.value = webhook.uuid;
    try {
        await settingsStore.testWebhook(webhook.uuid);
        toast.add({ severity: 'success', summary: 'Test Sent', detail: 'Test payload delivered', life: 3000 });
    } catch {
        toast.add({ severity: 'error', summary: 'Test Failed', detail: 'Could not deliver test payload', life: 3000 });
    } finally {
        testing.value = null;
    }
}

function deleteWebhook(webhook) {
    confirm.require({
        message: `Delete webhook for ${webhook.url}?`,
        header: 'Delete Webhook',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => settingsStore.deleteWebhook(webhook.uuid),
    });
}

async function loadLogs(webhook) {
    const data = await settingsStore.fetchWebhookLogs(webhook.uuid);
    logs.value[webhook.uuid] = data;
}

onMounted(() => settingsStore.fetchWebhooks());
</script>
