<template>
    <div>
        <PageHeader title="Organization Settings" />

        <div class="max-w-2xl space-y-6">
            <!-- Logo -->
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Logo</h3>
                <div class="flex items-center gap-6">
                    <div class="w-20 h-20 rounded-xl border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden bg-gray-50">
                        <img v-if="orgStore.organization?.logo_url" :src="orgStore.organization.logo_url" class="w-full h-full object-contain" alt="Logo" />
                        <i v-else class="pi pi-building text-gray-300 text-2xl"></i>
                    </div>
                    <div>
                        <input ref="logoInput" type="file" accept="image/*" class="hidden" @change="onLogoChange" />
                        <Button label="Upload Logo" icon="pi pi-upload" outlined size="small" @click="logoInput.click()" />
                        <p class="text-xs text-gray-400 mt-2">PNG, JPG or SVG. Max 2MB.</p>
                    </div>
                </div>
            </div>

            <!-- Organization info -->
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Organization Information</h3>
                <form @submit.prevent="saveOrg" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Organization Name</label>
                            <InputText v-model="form.name" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Industry</label>
                            <Select
                                v-model="form.industry"
                                :options="industryOptions"
                                option-label="label"
                                option-value="value"
                                class="w-full"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contact Email</label>
                            <InputText v-model="form.contact_email" type="email" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <InputText v-model="form.phone" class="w-full" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <InputText v-model="form.address" class="w-full" />
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                            <InputText v-model="form.city" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                            <InputText v-model="form.state" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                            <InputText v-model="form.country" class="w-full" />
                        </div>
                    </div>
                    <div class="flex justify-end pt-2">
                        <Button type="submit" label="Save Changes" :loading="saving" />
                    </div>
                </form>
            </div>

            <!-- SSO -->
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700">Single Sign-On (SSO)</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Allow users to sign in with your identity provider</p>
                    </div>
                    <ToggleSwitch v-model="ssoEnabled" @change="onSsoToggle" />
                </div>
                <div v-if="ssoEnabled" class="space-y-4 border-t border-gray-100 pt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SSO Provider</label>
                        <Select v-model="ssoForm.provider" :options="ssoProviders" option-label="label" option-value="value" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Entity ID / Client ID</label>
                        <InputText v-model="ssoForm.client_id" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Metadata URL / Client Secret</label>
                        <InputText v-model="ssoForm.metadata_url" class="w-full" />
                    </div>
                    <div class="flex justify-end">
                        <Button label="Save SSO Settings" size="small" @click="saveSso" :loading="savingSso" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';
import { useToast } from 'primevue/usetoast';
import PageHeader from '@/components/PageHeader.vue';
import { useOrganizationStore } from '@/stores/organization';

const orgStore = useOrganizationStore();
const toast = useToast();

const saving = ref(false);
const savingSso = ref(false);
const ssoEnabled = ref(false);
const logoInput = ref(null);

const form = reactive({
    name: '', industry: '', contact_email: '', phone: '', address: '', city: '', state: '', country: '',
});

const ssoForm = reactive({ provider: 'saml', client_id: '', metadata_url: '' });

const industryOptions = [
    { label: 'Freight & Logistics', value: 'freight' },
    { label: 'Manufacturing', value: 'manufacturing' },
    { label: 'Retail', value: 'retail' },
    { label: 'E-commerce', value: 'ecommerce' },
    { label: 'Other', value: 'other' },
];

const ssoProviders = [
    { label: 'SAML 2.0', value: 'saml' },
    { label: 'OAuth / OIDC', value: 'oidc' },
    { label: 'Microsoft Azure AD', value: 'azure' },
    { label: 'Google Workspace', value: 'google' },
    { label: 'Okta', value: 'okta' },
];

async function saveOrg() {
    saving.value = true;
    try {
        await orgStore.updateOrganization(form);
        toast.add({ severity: 'success', summary: 'Saved', detail: 'Organization settings updated', life: 3000 });
    } finally {
        saving.value = false;
    }
}

async function onLogoChange(event) {
    const file = event.target.files[0];
    if (!file) return;
    await orgStore.uploadLogo(file);
    toast.add({ severity: 'success', summary: 'Logo Updated', detail: 'Logo uploaded successfully', life: 3000 });
}

function onSsoToggle() {
    // persist toggle state
}

async function saveSso() {
    savingSso.value = true;
    try {
        await orgStore.updateSSOSettings({ ...ssoForm, enabled: ssoEnabled.value });
        toast.add({ severity: 'success', summary: 'Saved', detail: 'SSO settings updated', life: 3000 });
    } finally {
        savingSso.value = false;
    }
}

onMounted(async () => {
    await orgStore.fetchOrganization();
    const org = orgStore.organization;
    if (org) {
        Object.assign(form, {
            name: org.name || '', industry: org.industry || '',
            contact_email: org.contact_email || '', phone: org.phone || '',
            address: org.address || '', city: org.city || '', state: org.state || '', country: org.country || '',
        });
        ssoEnabled.value = !!org.sso?.enabled;
        if (org.sso) Object.assign(ssoForm, org.sso);
    }
});
</script>
