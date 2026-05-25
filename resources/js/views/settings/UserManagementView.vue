<template>
    <div>
        <PageHeader title="Users & Permissions">
            <template #actions>
                <Button label="Invite Member" icon="pi pi-user-plus" size="small" @click="showInvite = true" />
            </template>
        </PageHeader>

        <DataTable
            :value="orgStore.members"
            :loading="orgStore.loading"
            data-key="id"
            class="text-sm"
        >
            <Column field="name" header="Name">
                <template #body="{ data }">
                    <div class="flex items-center gap-3">
                        <Avatar :label="data.first_name?.charAt(0) || data.email?.charAt(0)" shape="circle" class="bg-blue-600 text-white w-8 h-8 text-sm" />
                        <div>
                            <p class="font-medium text-gray-900">{{ data.first_name }} {{ data.last_name }}</p>
                            <p class="text-xs text-gray-500">{{ data.email }}</p>
                        </div>
                    </div>
                </template>
            </Column>
            <Column field="role" header="Role">
                <template #body="{ data }">
                    <Select
                        :model-value="data.role"
                        :options="roleOptions"
                        option-label="label"
                        option-value="value"
                        size="small"
                        class="w-36"
                        @update:model-value="(role) => changeRole(data, role)"
                    />
                </template>
            </Column>
            <Column field="status" header="Status">
                <template #body="{ data }">
                    <span
                        class="text-xs px-2 py-0.5 rounded-full"
                        :class="data.email_verified_at ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'"
                    >
                        {{ data.email_verified_at ? 'Active' : 'Pending' }}
                    </span>
                </template>
            </Column>
            <Column field="last_login_at" header="Last Login">
                <template #body="{ data }">
                    <span class="text-xs text-gray-500">{{ data.last_login_at ? dayjs(data.last_login_at).fromNow() : 'Never' }}</span>
                </template>
            </Column>
            <Column header="">
                <template #body="{ data }">
                    <Button
                        icon="pi pi-user-minus"
                        text
                        size="small"
                        rounded
                        severity="danger"
                        title="Remove member"
                        @click="removeMember(data)"
                    />
                </template>
            </Column>
            <template #empty>
                <div class="py-10 text-center text-gray-400">
                    <i class="pi pi-users text-3xl mb-2 block"></i>
                    <p>No members yet</p>
                </div>
            </template>
        </DataTable>

        <!-- Invite Dialog -->
        <Dialog v-model:visible="showInvite" header="Invite Team Member" modal class="w-96">
            <form @submit.prevent="invite" class="space-y-4 pt-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <InputText v-model="inviteForm.email" type="email" placeholder="colleague@company.com" class="w-full" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <Select
                        v-model="inviteForm.role"
                        :options="roleOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name (optional)</label>
                    <InputText v-model="inviteForm.first_name" placeholder="John" class="w-full" />
                </div>
            </form>
            <template #footer>
                <Button label="Cancel" text @click="showInvite = false" />
                <Button label="Send Invite" icon="pi pi-send" :loading="inviting" @click="invite" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Avatar from 'primevue/avatar';
import Dialog from 'primevue/dialog';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import PageHeader from '@/components/PageHeader.vue';
import { useOrganizationStore } from '@/stores/organization';

dayjs.extend(relativeTime);

const orgStore = useOrganizationStore();
const confirm = useConfirm();
const toast = useToast();

const showInvite = ref(false);
const inviting = ref(false);
const inviteForm = reactive({ email: '', role: 'member', first_name: '' });

const roleOptions = [
    { label: 'Admin', value: 'admin' },
    { label: 'Manager', value: 'manager' },
    { label: 'Member', value: 'member' },
    { label: 'Read Only', value: 'read_only' },
];

async function changeRole(member, role) {
    await orgStore.updateMemberRole(member.id, role);
    toast.add({ severity: 'success', summary: 'Updated', detail: `${member.email} role changed to ${role}`, life: 3000 });
}

function removeMember(member) {
    confirm.require({
        message: `Remove ${member.email} from the organization?`,
        header: 'Remove Member',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            await orgStore.removeMember(member.id);
            toast.add({ severity: 'info', summary: 'Removed', detail: 'Member removed', life: 3000 });
        },
    });
}

async function invite() {
    if (!inviteForm.email) return;
    inviting.value = true;
    try {
        await orgStore.inviteMember({ ...inviteForm });
        toast.add({ severity: 'success', summary: 'Invited', detail: `Invitation sent to ${inviteForm.email}`, life: 3000 });
        showInvite.value = false;
        Object.assign(inviteForm, { email: '', role: 'member', first_name: '' });
    } finally {
        inviting.value = false;
    }
}

onMounted(() => orgStore.fetchMembers());
</script>
