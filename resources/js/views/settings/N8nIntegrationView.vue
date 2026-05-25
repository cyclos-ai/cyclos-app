<template>
    <div>
        <PageHeader title="Workflow Automation" subtitle="Connect n8n to automate container lifecycle events" />

        <!-- Connection Status -->
        <div class="bg-white rounded-xl border border-surface-200 p-6 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center"
                         :class="store.isConnected ? 'bg-primary-50' : 'bg-surface-100'">
                        <svg class="w-6 h-6" :class="store.isConnected ? 'text-primary-600' : 'text-surface-400'"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-surface-900 tracking-tight">n8n Workflow Engine</h3>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="w-2 h-2 rounded-full"
                                  :class="store.isConnected ? 'bg-emerald-500' : 'bg-surface-300'"></span>
                            <span class="text-sm" :class="store.isConnected ? 'text-emerald-600' : 'text-surface-500'">
                                {{ store.isConnected ? 'Connected' : 'Not connected' }}
                            </span>
                            <span v-if="store.status?.host_url" class="text-xs text-surface-400 ml-2">
                                {{ store.status.host_url }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <template v-if="store.isConnected">
                        <Button label="Health Check" icon="pi pi-heart" size="small" outlined
                                :loading="healthChecking" @click="runHealthCheck" />
                        <Button label="Disconnect" icon="pi pi-times" size="small" outlined severity="danger"
                                @click="handleDisconnect" />
                    </template>
                    <Button v-else label="Connect n8n" icon="pi pi-link" size="small"
                            @click="showConnectDialog = true" />
                </div>
            </div>

            <!-- Stats row when connected -->
            <div v-if="store.isConnected" class="grid grid-cols-3 gap-4 mt-5 pt-5 border-t border-surface-100">
                <div>
                    <p class="text-xs text-surface-500 uppercase tracking-wider">Workflows</p>
                    <p class="text-2xl font-semibold text-surface-900 tracking-tight mt-0.5">
                        {{ store.status?.workflow_count ?? 0 }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-surface-500 uppercase tracking-wider">Active</p>
                    <p class="text-2xl font-semibold text-primary-600 tracking-tight mt-0.5">
                        {{ store.status?.active_workflows ?? 0 }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-surface-500 uppercase tracking-wider">Last Health</p>
                    <Tag :severity="healthSeverity" :value="store.status?.last_health_status || 'unknown'" class="mt-1" />
                </div>
            </div>
        </div>

        <!-- Templates Section -->
        <div v-if="store.isConnected" class="mb-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-surface-900 tracking-tight">Workflow Templates</h2>
                <Button label="Refresh" icon="pi pi-refresh" text size="small" @click="loadTemplates" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="tpl in store.templates" :key="tpl.key"
                     class="bg-white rounded-xl border border-surface-200 p-5
                            hover:shadow-md hover:-translate-y-0.5
                            transition-all duration-200 ease-out-quart">
                    <div class="flex items-start justify-between mb-2">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center"
                             :class="categoryBg(tpl.category)">
                            <i :class="`pi ${categoryIcon(tpl.category)} text-sm`"
                               :style="{ color: categoryColor(tpl.category) }"></i>
                        </div>
                        <Tag v-if="tpl.deployed" value="Deployed" severity="success" class="text-xs" />
                    </div>
                    <h4 class="font-semibold text-surface-900 text-sm mt-3">{{ tpl.name }}</h4>
                    <p class="text-xs text-surface-500 mt-1 line-clamp-2">{{ tpl.description }}</p>
                    <div class="flex items-center justify-between mt-4">
                        <span class="text-xs font-mono text-surface-400">{{ tpl.trigger_event }}</span>
                        <Button v-if="!tpl.deployed" label="Deploy" icon="pi pi-cloud-upload" size="small" outlined
                                :loading="deploying === tpl.key" @click="handleDeploy(tpl.key)" />
                    </div>
                </div>

                <div v-if="store.templates.length === 0"
                     class="col-span-full text-center py-10 text-surface-400 text-sm">
                    No templates available.
                </div>
            </div>
        </div>

        <!-- Workflows Table -->
        <div v-if="store.isConnected" class="bg-white rounded-xl border border-surface-200 mb-6">
            <div class="flex items-center justify-between px-5 py-4 border-b border-surface-100">
                <h2 class="text-lg font-semibold text-surface-900 tracking-tight">Workflows</h2>
                <div class="flex gap-2">
                    <Button label="Sync from n8n" icon="pi pi-sync" size="small" outlined
                            :loading="syncing" @click="handleSync" />
                </div>
            </div>
            <DataTable :value="store.workflows" :loading="loadingWorkflows" class="text-sm"
                       empty-message="No workflows found. Sync from n8n or deploy a template.">
                <Column field="name" header="Workflow" class="font-medium" />
                <Column field="active" header="Status">
                    <template #body="{ data }">
                        <Tag :value="data.active ? 'Active' : 'Inactive'"
                             :severity="data.active ? 'success' : 'secondary'" />
                    </template>
                </Column>
                <Column field="cyclos_event" header="Trigger Event">
                    <template #body="{ data }">
                        <span v-if="data.cyclos_event"
                              class="text-xs font-mono text-primary-700 bg-primary-50 px-2 py-0.5 rounded">
                            {{ data.cyclos_event }}
                        </span>
                        <span v-else class="text-xs text-surface-400">Not mapped</span>
                    </template>
                </Column>
                <Column field="updatedAt" header="Last Updated">
                    <template #body="{ data }">
                        <span class="text-xs text-surface-500">{{ formatDate(data.updatedAt) }}</span>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Recent Executions -->
        <div v-if="store.isConnected" class="bg-white rounded-xl border border-surface-200">
            <div class="flex items-center justify-between px-5 py-4 border-b border-surface-100">
                <h2 class="text-lg font-semibold text-surface-900 tracking-tight">Recent Executions</h2>
                <Button icon="pi pi-refresh" text size="small" rounded @click="loadExecutions" />
            </div>
            <DataTable :value="store.executions" :loading="loadingExecutions" class="text-sm"
                       empty-message="No executions yet.">
                <Column field="workflowData.name" header="Workflow" />
                <Column field="status" header="Status">
                    <template #body="{ data }">
                        <Tag :value="data.status"
                             :severity="executionSeverity(data.status)" />
                    </template>
                </Column>
                <Column field="startedAt" header="Started">
                    <template #body="{ data }">
                        <span class="text-xs text-surface-500">{{ formatDate(data.startedAt) }}</span>
                    </template>
                </Column>
                <Column field="stoppedAt" header="Finished">
                    <template #body="{ data }">
                        <span class="text-xs text-surface-500">
                            {{ data.stoppedAt ? formatDate(data.stoppedAt) : '—' }}
                        </span>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Not Connected Empty State -->
        <div v-if="!store.isConnected && !store.loading"
             class="bg-white rounded-xl border border-dashed border-surface-300 p-12 text-center mt-6">
            <svg class="w-16 h-16 text-surface-300 mx-auto mb-4" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.5">
                <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            <h3 class="text-surface-700 font-semibold mb-1">Connect n8n to get started</h3>
            <p class="text-sm text-surface-500 mb-4 max-w-md mx-auto">
                Automate your container lifecycle with n8n workflows. Get notified on arrivals,
                demurrage alerts, and more.
            </p>
            <Button label="Connect n8n" icon="pi pi-link" @click="showConnectDialog = true" />
        </div>

        <!-- Connect Dialog -->
        <Dialog v-model:visible="showConnectDialog" header="Connect to n8n" modal :style="{ width: '480px' }">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">n8n Host URL</label>
                    <InputText v-model="connectForm.host_url" class="w-full"
                               placeholder="https://n8n.your-domain.com" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">API Key</label>
                    <Password v-model="connectForm.api_key" class="w-full" input-class="w-full"
                              placeholder="n8n API key from Settings > n8n API"
                              :feedback="false" toggle-mask />
                    <small class="text-xs text-surface-400 mt-1 block">
                        Generate at n8n Settings &rarr; n8n API &rarr; Create API Key
                    </small>
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">
                        Webhook Base URL <span class="text-surface-400 font-normal">(optional)</span>
                    </label>
                    <InputText v-model="connectForm.webhook_base_url" class="w-full"
                               placeholder="Same as host URL if not set" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" text @click="showConnectDialog = false" />
                <Button label="Connect" icon="pi pi-link" :loading="connecting" @click="handleConnect" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Tag from 'primevue/tag';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import PageHeader from '@/components/PageHeader.vue';
import { useN8nIntegrationStore } from '@/stores/n8nIntegration';
import dayjs from 'dayjs';

const toast = useToast();
const confirm = useConfirm();
const store = useN8nIntegrationStore();

const showConnectDialog = ref(false);
const connecting = ref(false);
const syncing = ref(false);
const deploying = ref(null);
const healthChecking = ref(false);
const loadingWorkflows = ref(false);
const loadingExecutions = ref(false);

const connectForm = reactive({
    host_url: '',
    api_key: '',
    webhook_base_url: '',
});

const healthSeverity = computed(() => {
    const s = store.status?.last_health_status;
    if (s === 'healthy') return 'success';
    if (s === 'auth_failed') return 'danger';
    if (s === 'unreachable') return 'warn';
    return 'secondary';
});

function formatDate(d) {
    return d ? dayjs(d).format('MMM D, h:mm A') : '';
}

function executionSeverity(status) {
    if (status === 'success') return 'success';
    if (status === 'error') return 'danger';
    if (status === 'running' || status === 'waiting') return 'info';
    return 'secondary';
}

const CATEGORY_MAP = {
    tracking: { icon: 'pi-map-marker', bg: 'bg-sky-50',   color: '#0ea5e9' },
    finance:  { icon: 'pi-dollar',     bg: 'bg-amber-50',  color: '#f59e0b' },
};

function categoryIcon(cat)  { return CATEGORY_MAP[cat]?.icon  || 'pi-cog'; }
function categoryBg(cat)    { return CATEGORY_MAP[cat]?.bg    || 'bg-surface-50'; }
function categoryColor(cat) { return CATEGORY_MAP[cat]?.color || '#6e8383'; }

async function handleConnect() {
    if (!connectForm.host_url || !connectForm.api_key) return;
    connecting.value = true;
    try {
        await store.connect({ ...connectForm });
        showConnectDialog.value = false;
        toast.add({ severity: 'success', summary: 'Connected', detail: 'n8n connected successfully', life: 3000 });
        await loadAll();
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Connection Failed',
            detail: err.response?.data?.health?.message || err.response?.data?.message || 'Unable to connect',
            life: 5000,
        });
    } finally {
        connecting.value = false;
    }
}

function handleDisconnect() {
    confirm.require({
        message: 'Disconnect from n8n? Workflow automations will stop.',
        header: 'Confirm Disconnect',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            await store.disconnect();
            toast.add({ severity: 'info', summary: 'Disconnected', detail: 'n8n has been disconnected', life: 3000 });
        },
    });
}

async function runHealthCheck() {
    healthChecking.value = true;
    try {
        const result = await store.healthCheck();
        const sev = result.status === 'healthy' ? 'success' : 'warn';
        toast.add({ severity: sev, summary: 'Health Check', detail: `Status: ${result.status}`, life: 3000 });
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Health check failed', life: 5000 });
    } finally {
        healthChecking.value = false;
    }
}

async function handleSync() {
    syncing.value = true;
    try {
        const result = await store.syncWorkflows();
        await loadWorkflows();
        toast.add({ severity: 'success', summary: 'Synced', detail: result.message, life: 3000 });
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Sync failed', life: 5000 });
    } finally {
        syncing.value = false;
    }
}

async function handleDeploy(key) {
    deploying.value = key;
    try {
        const result = await store.deployTemplate(key);
        toast.add({ severity: 'success', summary: 'Deployed', detail: result.message, life: 3000 });
        await loadWorkflows();
    } catch (err) {
        toast.add({
            severity: 'error',
            summary: 'Deploy Failed',
            detail: err.response?.data?.message || 'Failed to deploy',
            life: 5000,
        });
    } finally {
        deploying.value = null;
    }
}

async function loadTemplates() {
    await store.fetchTemplates();
}

async function loadWorkflows() {
    loadingWorkflows.value = true;
    try {
        await store.fetchWorkflows();
    } finally {
        loadingWorkflows.value = false;
    }
}

async function loadExecutions() {
    loadingExecutions.value = true;
    try {
        await store.fetchExecutions();
    } finally {
        loadingExecutions.value = false;
    }
}

async function loadAll() {
    if (store.isConnected) {
        await Promise.all([loadTemplates(), loadWorkflows(), loadExecutions()]);
    }
}

onMounted(async () => {
    await store.fetchStatus();
    await loadAll();
});
</script>
