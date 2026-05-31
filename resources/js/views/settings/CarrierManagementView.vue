<template>
    <div>
        <PageHeader title="Carrier Management" subtitle="Manage drayage carrier connections and send onboarding invites" />

        <!-- Tab navigation -->
        <TabView v-model:activeIndex="activeTab" class="mb-6">
            <TabPanel header="Connected Carriers">
                <!-- Carrier list -->
                <div class="mb-4 flex justify-between items-center">
                    <span class="text-sm text-surface-500">
                        {{ store.carriers.length }} carrier{{ store.carriers.length !== 1 ? 's' : '' }} connected
                    </span>
                    <Button label="Invite Carrier" icon="pi pi-plus" @click="showInviteDialog = true" />
                </div>

                <div v-if="store.loading" class="flex justify-center py-12">
                    <ProgressSpinner class="w-10 h-10" />
                </div>

                <div v-else-if="store.carriers.length === 0" class="text-center py-16 bg-white rounded-xl border border-surface-200">
                    <i class="pi pi-truck text-4xl text-surface-300 mb-4"></i>
                    <h3 class="text-lg font-semibold text-surface-700 mb-2">No Carriers Connected</h3>
                    <p class="text-surface-500 mb-6 max-w-md mx-auto">
                        Send an invite link to your drayage carriers so they can register with their SCAC code.
                    </p>
                    <Button label="Send First Invite" icon="pi pi-send" @click="showInviteDialog = true" />
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="carrier in store.carriers" :key="carrier.id"
                        class="bg-white rounded-xl shadow-sm border border-surface-200 overflow-hidden">
                        <div class="h-1.5" :class="statusColor(carrier.status)"></div>
                        <div class="p-5">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h3 class="font-semibold text-surface-900">{{ carrier.company_name }}</h3>
                                    <span class="text-xs font-mono text-surface-400">SCAC: {{ carrier.scac }}</span>
                                </div>
                                <Tag :value="carrier.status" :severity="statusSeverity(carrier.status)" />
                            </div>

                            <div class="space-y-1 text-sm text-surface-600">
                                <p v-if="carrier.contact_name">
                                    <i class="pi pi-user text-xs mr-1.5"></i>{{ carrier.contact_name }}
                                </p>
                                <p v-if="carrier.contact_email">
                                    <i class="pi pi-envelope text-xs mr-1.5"></i>{{ carrier.contact_email }}
                                </p>
                                <p v-if="carrier.contact_phone">
                                    <i class="pi pi-phone text-xs mr-1.5"></i>{{ carrier.contact_phone }}
                                </p>
                                <p v-if="carrier.usdot">
                                    <span class="text-xs text-surface-400">USDOT:</span> {{ carrier.usdot }}
                                </p>
                                <p v-if="carrier.city && carrier.state">
                                    <i class="pi pi-map-marker text-xs mr-1.5"></i>{{ carrier.city }}, {{ carrier.state }}
                                </p>
                            </div>

                            <div v-if="carrier.equipment_types?.length" class="flex flex-wrap gap-1 mt-3">
                                <Tag v-for="eq in carrier.equipment_types" :key="eq" :value="eq"
                                    severity="secondary" class="text-xs" />
                            </div>
                        </div>
                    </div>
                </div>
            </TabPanel>

            <TabPanel header="Invites">
                <div class="mb-4 flex justify-between items-center">
                    <span class="text-sm text-surface-500">
                        {{ store.pendingInvites.length }} pending invite{{ store.pendingInvites.length !== 1 ? 's' : '' }}
                    </span>
                    <Button label="New Invite" icon="pi pi-plus" size="small" @click="showInviteDialog = true" />
                </div>

                <DataTable :value="store.invites" :loading="store.loading" stripedRows
                    class="text-sm" responsiveLayout="scroll">
                    <Column field="email" header="Email">
                        <template #body="{ data }">
                            {{ data.email || '-' }}
                        </template>
                    </Column>
                    <Column field="company_name" header="Company">
                        <template #body="{ data }">
                            {{ data.company_name || '-' }}
                        </template>
                    </Column>
                    <Column field="status" header="Status">
                        <template #body="{ data }">
                            <Tag :value="data.status" :severity="inviteStatusSeverity(data.status)" />
                        </template>
                    </Column>
                    <Column field="expires_at" header="Expires">
                        <template #body="{ data }">
                            {{ formatDate(data.expires_at) }}
                        </template>
                    </Column>
                    <Column field="created_at" header="Created">
                        <template #body="{ data }">
                            {{ formatDate(data.created_at) }}
                        </template>
                    </Column>
                    <Column header="Actions" style="width: 120px">
                        <template #body="{ data }">
                            <div class="flex gap-1">
                                <Button v-if="data.status === 'pending'" icon="pi pi-copy" size="small" text
                                    v-tooltip.top="'Copy invite link'" @click="copyInviteLink(data)" />
                                <Button v-if="data.status === 'pending'" icon="pi pi-times" size="small" text
                                    severity="danger" v-tooltip.top="'Revoke'" @click="handleRevoke(data)" />
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <div class="text-center py-8 text-surface-400">
                            No invites yet. Send one to get started.
                        </div>
                    </template>
                </DataTable>
            </TabPanel>
        </TabView>

        <!-- Create Invite Dialog -->
        <Dialog v-model:visible="showInviteDialog" header="Invite a Carrier" modal :style="{ width: '440px' }">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Carrier Email (optional)</label>
                    <InputText v-model="inviteForm.email" type="email" class="w-full"
                        placeholder="carrier@company.com" />
                    <small class="text-surface-400">If provided, the carrier will receive a notification email.</small>
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Company Name (optional)</label>
                    <InputText v-model="inviteForm.company_name" class="w-full"
                        placeholder="Carrier Company Name" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" outlined @click="showInviteDialog = false" />
                <Button label="Generate Invite Link" icon="pi pi-link" :loading="creating"
                    @click="handleCreateInvite" />
            </template>
        </Dialog>

        <!-- Invite Link Result Dialog -->
        <Dialog v-model:visible="showLinkDialog" header="Invite Link Generated" modal :style="{ width: '520px' }">
            <p class="text-sm text-surface-600 mb-4">
                Share this link with your carrier. They will use it to complete their onboarding.
                The link expires in 7 days.
            </p>
            <div class="flex items-center gap-2 bg-surface-50 border border-surface-200 rounded-lg p-3">
                <InputText :modelValue="generatedLink" readonly class="w-full text-xs font-mono" />
                <Button icon="pi pi-copy" outlined @click="copyToClipboard(generatedLink)" v-tooltip.top="'Copy'" />
            </div>
            <Message v-if="copied" severity="success" :closable="false" class="mt-3">
                Copied to clipboard!
            </Message>
            <template #footer>
                <Button label="Done" @click="showLinkDialog = false" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useCarrierOnboardingStore } from '@/stores/carrierOnboarding';
import PageHeader from '@/components/PageHeader.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import Tag from 'primevue/tag';
import Message from 'primevue/message';
import ProgressSpinner from 'primevue/progressspinner';

const store = useCarrierOnboardingStore();

const activeTab = ref(0);
const showInviteDialog = ref(false);
const showLinkDialog = ref(false);
const generatedLink = ref('');
const creating = ref(false);
const copied = ref(false);

const inviteForm = reactive({
    email: '',
    company_name: '',
});

onMounted(() => {
    store.fetchCarriers();
    store.fetchInvites();
});

function statusColor(status) {
    return {
        active: 'bg-green-500',
        suspended: 'bg-yellow-500',
        inactive: 'bg-surface-300',
    }[status] || 'bg-surface-300';
}

function statusSeverity(status) {
    return { active: 'success', suspended: 'warn', inactive: 'secondary' }[status] || 'secondary';
}

function inviteStatusSeverity(status) {
    return {
        pending: 'warn',
        accepted: 'success',
        expired: 'secondary',
        revoked: 'danger',
    }[status] || 'secondary';
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
}

async function handleCreateInvite() {
    creating.value = true;
    try {
        const invite = await store.createInvite({
            email: inviteForm.email || null,
            company_name: inviteForm.company_name || null,
        });
        generatedLink.value = invite.onboarding_url;
        showInviteDialog.value = false;
        showLinkDialog.value = true;
        inviteForm.email = '';
        inviteForm.company_name = '';
        store.fetchInvites();
    } catch (err) {
        console.error('Failed to create invite:', err);
    } finally {
        creating.value = false;
    }
}

async function handleRevoke(invite) {
    try {
        await store.revokeInvite(invite.id);
        store.fetchInvites();
    } catch (err) {
        console.error('Failed to revoke invite:', err);
    }
}

function copyInviteLink(invite) {
    // Build the link from the token
    const tenantSlug = window.location.hostname.split('.')[0] || 'demo';
    const link = `${window.location.origin}/carrier/onboard/${tenantSlug}/${invite.token}`;
    copyToClipboard(link);
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}
</script>
