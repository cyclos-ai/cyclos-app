<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Onboarding</h1>
            <p class="text-sm text-gray-500 mt-1">Manage pending registrations and invite users</p>
        </div>

        <TabView>
            <!-- Tab 1: Pending Approvals -->
            <TabPanel header="Pending Approvals">
                <div class="pt-4">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-sm text-gray-500">
                            {{ adminStore.pendingCount }} pending registration{{ adminStore.pendingCount !== 1 ? 's' : '' }}
                        </p>
                        <Button
                            icon="pi pi-refresh"
                            text
                            size="small"
                            :loading="adminStore.loading"
                            @click="adminStore.fetchPendingRegistrations()"
                        />
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <DataTable
                            :value="adminStore.pendingRegistrations"
                            :loading="adminStore.loading"
                            size="small"
                        >
                            <template #empty>
                                <div class="text-center py-8 text-gray-400">No pending registrations</div>
                            </template>
                            <Column field="first_name" header="Name">
                                <template #body="{ data }">
                                    {{ data.first_name }} {{ data.last_name }}
                                </template>
                            </Column>
                            <Column field="email" header="Email" />
                            <Column field="company_name" header="Company" />
                            <Column field="role" header="Role">
                                <template #body="{ data }">
                                    <Tag :value="formatRole(data.role)" severity="secondary" />
                                </template>
                            </Column>
                            <Column field="created_at" header="Date" sortable />
                            <Column header="Actions">
                                <template #body="{ data }">
                                    <div class="flex items-center gap-2">
                                        <Button
                                            label="Approve"
                                            size="small"
                                            severity="success"
                                            :loading="processingUuid === data.uuid && processingAction === 'approve'"
                                            @click="handleApprove(data.uuid)"
                                        />
                                        <Button
                                            label="Reject"
                                            size="small"
                                            severity="danger"
                                            outlined
                                            :loading="processingUuid === data.uuid && processingAction === 'reject'"
                                            @click="openRejectDialog(data)"
                                        />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </TabPanel>

            <!-- Tab 2: Invite User -->
            <TabPanel header="Invite User">
                <div class="pt-4 max-w-lg">
                    <form @submit.prevent="handleInvite" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                <InputText v-model="inviteForm.first_name" class="w-full" placeholder="Jane" :class="{ 'p-invalid': inviteErrors.first_name }" />
                                <small v-if="inviteErrors.first_name" class="p-error">{{ inviteErrors.first_name }}</small>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                <InputText v-model="inviteForm.last_name" class="w-full" placeholder="Doe" :class="{ 'p-invalid': inviteErrors.last_name }" />
                                <small v-if="inviteErrors.last_name" class="p-error">{{ inviteErrors.last_name }}</small>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <InputText v-model="inviteForm.email" type="email" class="w-full" placeholder="jane@company.com" :class="{ 'p-invalid': inviteErrors.email }" />
                            <small v-if="inviteErrors.email" class="p-error">{{ inviteErrors.email }}</small>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                            <InputText v-model="inviteForm.company_name" class="w-full" placeholder="Acme Corp" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <Select
                                v-model="inviteForm.role"
                                :options="roleOptions"
                                option-label="label"
                                option-value="value"
                                placeholder="Select a role"
                                class="w-full"
                                :class="{ 'p-invalid': inviteErrors.role }"
                            />
                            <small v-if="inviteErrors.role" class="p-error">{{ inviteErrors.role }}</small>
                        </div>

                        <Message v-if="inviteError" severity="error" :closable="false">{{ inviteError }}</Message>

                        <Button type="submit" label="Create Account" icon="pi pi-user-plus" :loading="inviting" />
                    </form>
                </div>
            </TabPanel>
        </TabView>

        <!-- Reject dialog -->
        <Dialog v-model:visible="showRejectDialog" header="Reject Registration" modal class="w-96">
            <div class="pt-2 space-y-3">
                <p class="text-sm text-gray-600">
                    Reject registration for <strong>{{ rejectTarget?.first_name }} {{ rejectTarget?.last_name }}</strong>?
                </p>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason (optional)</label>
                    <Textarea v-model="rejectReason" rows="3" class="w-full" placeholder="Reason for rejection..." />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" text @click="showRejectDialog = false" />
                <Button label="Reject" severity="danger" :loading="processingAction === 'reject'" @click="confirmReject" />
            </template>
        </Dialog>

        <!-- Temp password dialog -->
        <Dialog v-model:visible="showPasswordDialog" header="Account Created" modal class="w-96">
            <div class="pt-2 space-y-3">
                <p class="text-sm text-gray-600">The account has been created. Share the temporary password with the user:</p>
                <div class="bg-gray-50 rounded-lg p-3 font-mono text-sm text-gray-900 border border-gray-200">
                    {{ tempPassword }}
                </div>
                <p class="text-xs text-gray-400">The user will be prompted to change their password on first login.</p>
            </div>
            <template #footer>
                <Button label="Done" @click="showPasswordDialog = false" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Message from 'primevue/message';
import Dialog from 'primevue/dialog';
import { useAdminStore } from '@/stores/admin';

const adminStore = useAdminStore();

const processingUuid = ref(null);
const processingAction = ref('');
const showRejectDialog = ref(false);
const rejectTarget = ref(null);
const rejectReason = ref('');

const inviting = ref(false);
const inviteError = ref('');
const showPasswordDialog = ref(false);
const tempPassword = ref('');

const inviteForm = reactive({
    first_name: '',
    last_name: '',
    email: '',
    company_name: '',
    role: null,
});

const inviteErrors = reactive({
    first_name: '',
    last_name: '',
    email: '',
    role: '',
});

const roleOptions = [
    { label: 'Shipper Admin', value: 'shipper_admin' },
    { label: 'Shipper User', value: 'shipper_user' },
    { label: 'Drayage Admin', value: 'drayage_admin' },
    { label: 'Drayage Dispatcher', value: 'drayage_dispatcher' },
];

function formatRole(role) {
    return role ? role.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : '';
}

async function handleApprove(uuid) {
    processingUuid.value = uuid;
    processingAction.value = 'approve';
    try {
        await adminStore.approveRegistration(uuid);
    } finally {
        processingUuid.value = null;
        processingAction.value = '';
    }
}

function openRejectDialog(registration) {
    rejectTarget.value = registration;
    rejectReason.value = '';
    showRejectDialog.value = true;
}

async function confirmReject() {
    processingAction.value = 'reject';
    try {
        await adminStore.rejectRegistration(rejectTarget.value.uuid, rejectReason.value);
        showRejectDialog.value = false;
    } finally {
        processingAction.value = '';
    }
}

function validateInvite() {
    inviteErrors.first_name = '';
    inviteErrors.last_name = '';
    inviteErrors.email = '';
    inviteErrors.role = '';
    let valid = true;
    if (!inviteForm.first_name) { inviteErrors.first_name = 'Required'; valid = false; }
    if (!inviteForm.last_name) { inviteErrors.last_name = 'Required'; valid = false; }
    if (!inviteForm.email || !/\S+@\S+\.\S+/.test(inviteForm.email)) { inviteErrors.email = 'Valid email required'; valid = false; }
    if (!inviteForm.role) { inviteErrors.role = 'Please select a role'; valid = false; }
    return valid;
}

async function handleInvite() {
    if (!validateInvite()) return;
    inviting.value = true;
    inviteError.value = '';
    try {
        const result = await adminStore.inviteUser({ ...inviteForm });
        tempPassword.value = result.temp_password || '(see email)';
        showPasswordDialog.value = true;
        Object.assign(inviteForm, { first_name: '', last_name: '', email: '', company_name: '', role: null });
    } catch (err) {
        inviteError.value = err.response?.data?.message || 'Failed to create account.';
    } finally {
        inviting.value = false;
    }
}

onMounted(() => {
    adminStore.fetchPendingRegistrations();
});
</script>
