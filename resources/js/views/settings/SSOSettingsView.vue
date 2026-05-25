<template>
    <div>
        <PageHeader title="Single Sign-On" subtitle="Configure SSO for your organization" />
        <div class="max-w-2xl">
            <div class="bg-white border border-gray-200 rounded-xl p-6 space-y-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-800">Enable SSO</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Allow team members to sign in with your identity provider</p>
                    </div>
                    <ToggleSwitch v-model="form.enabled" />
                </div>

                <Divider />

                <div v-if="form.enabled" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Provider</label>
                        <Select v-model="form.provider" :options="providers" option-label="label" option-value="value" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Client ID / Entity ID</label>
                        <InputText v-model="form.client_id" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Client Secret / Metadata URL</label>
                        <Password v-model="form.client_secret" class="w-full" input-class="w-full" :feedback="false" toggle-mask />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Callback URL (read-only)</label>
                        <InputText :value="callbackUrl" readonly class="w-full bg-gray-50 font-mono text-xs" />
                    </div>
                    <div class="flex items-center gap-2 pt-2">
                        <Checkbox v-model="form.force_sso" input-id="force_sso" binary />
                        <label for="force_sso" class="text-sm text-gray-700 cursor-pointer">Require SSO for all users (disable password login)</label>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <Button label="Save SSO Settings" :loading="saving" @click="save" />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Select from 'primevue/select';
import Checkbox from 'primevue/checkbox';
import ToggleSwitch from 'primevue/toggleswitch';
import Divider from 'primevue/divider';
import { useToast } from 'primevue/usetoast';
import PageHeader from '@/components/PageHeader.vue';
import { useOrganizationStore } from '@/stores/organization';

const orgStore = useOrganizationStore();
const toast = useToast();
const saving = ref(false);

const form = reactive({ enabled: false, provider: 'saml', client_id: '', client_secret: '', force_sso: false });

const providers = [
    { label: 'SAML 2.0', value: 'saml' },
    { label: 'OAuth 2.0 / OIDC', value: 'oidc' },
    { label: 'Microsoft Azure AD', value: 'azure' },
    { label: 'Google Workspace', value: 'google' },
    { label: 'Okta', value: 'okta' },
];

const callbackUrl = computed(() => `${window.location.origin}/auth/sso/callback`);

async function save() {
    saving.value = true;
    try {
        await orgStore.updateSSOSettings(form);
        toast.add({ severity: 'success', summary: 'Saved', detail: 'SSO settings updated', life: 3000 });
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    await orgStore.fetchOrganization();
    if (orgStore.organization?.sso) Object.assign(form, orgStore.organization.sso);
});
</script>
