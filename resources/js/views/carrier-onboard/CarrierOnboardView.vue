<template>
    <div class="min-h-screen bg-surface-50 flex flex-col">
        <!-- Header -->
        <header class="bg-white border-b border-surface-200 px-6 py-4">
            <div class="max-w-2xl mx-auto flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center">
                    <i class="pi pi-truck text-white text-sm"></i>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-surface-900">Carrier Onboarding</h1>
                    <p v-if="inviteData?.tenant_name" class="text-xs text-surface-500">
                        Invited by {{ inviteData.tenant_name }}
                    </p>
                </div>
            </div>
        </header>

        <main class="flex-1 flex items-start justify-center px-4 py-8">
            <div class="w-full max-w-2xl">

                <!-- Loading state -->
                <div v-if="store.loading && !inviteData" class="text-center py-20">
                    <ProgressSpinner class="w-12 h-12" />
                    <p class="mt-4 text-surface-500">Validating invite link...</p>
                </div>

                <!-- Error state -->
                <div v-else-if="error" class="bg-white rounded-xl shadow-sm border border-red-200 p-8 text-center">
                    <i class="pi pi-exclamation-triangle text-4xl text-red-400 mb-4"></i>
                    <h2 class="text-xl font-bold text-surface-900 mb-2">Invalid Invite</h2>
                    <p class="text-surface-600">{{ error }}</p>
                </div>

                <!-- Success state -->
                <div v-else-if="store.onboardingComplete" class="bg-white rounded-xl shadow-sm border border-green-200 p-8 text-center">
                    <i class="pi pi-check-circle text-5xl text-green-500 mb-4"></i>
                    <h2 class="text-xl font-bold text-surface-900 mb-2">Onboarding Complete!</h2>
                    <p class="text-surface-600 mb-6">
                        Your carrier account has been connected. You are now linked with the shipper.
                    </p>
                    <Button label="Go to Login" icon="pi pi-sign-in" @click="$router.push('/login')" />
                </div>

                <!-- Onboarding wizard -->
                <div v-else-if="inviteData">
                    <!-- Steps indicator -->
                    <div class="flex items-center justify-center gap-2 mb-8">
                        <div v-for="s in 3" :key="s"
                            class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-colors"
                                :class="s <= step ? 'bg-primary text-white' : 'bg-surface-200 text-surface-500'">
                                {{ s }}
                            </div>
                            <span class="text-xs font-medium hidden sm:inline"
                                :class="s <= step ? 'text-primary' : 'text-surface-400'">
                                {{ stepLabels[s - 1] }}
                            </span>
                            <div v-if="s < 3" class="w-8 h-px bg-surface-300"></div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">

                        <!-- Step 1: SCAC / USDOT Lookup -->
                        <div v-if="step === 1">
                            <h2 class="text-lg font-bold text-surface-900 mb-1">Carrier Identification</h2>
                            <p class="text-sm text-surface-500 mb-6">
                                Enter your SCAC code or USDOT number to auto-populate your company info.
                            </p>

                            <div class="space-y-4">
                                <div class="flex gap-3">
                                    <div class="flex-1">
                                        <label class="block text-sm font-medium text-surface-700 mb-1">SCAC Code</label>
                                        <InputText v-model="form.scac" placeholder="e.g. FHGP" class="w-full"
                                            @keyup.enter="handleLookupScac" />
                                    </div>
                                    <div class="pt-6">
                                        <Button label="Lookup" icon="pi pi-search" :loading="store.lookupLoading"
                                            @click="handleLookupScac" :disabled="!form.scac" />
                                    </div>
                                </div>

                                <Divider align="center"><span class="text-xs text-surface-400">or</span></Divider>

                                <div class="flex gap-3">
                                    <div class="flex-1">
                                        <label class="block text-sm font-medium text-surface-700 mb-1">USDOT Number</label>
                                        <InputText v-model="usdotInput" placeholder="e.g. 1234567" class="w-full"
                                            @keyup.enter="handleLookupUsdot" />
                                    </div>
                                    <div class="pt-6">
                                        <Button label="Lookup" icon="pi pi-search" :loading="store.lookupLoading"
                                            @click="handleLookupUsdot" :disabled="!usdotInput" outlined />
                                    </div>
                                </div>

                                <!-- Lookup result -->
                                <Message v-if="lookupMessage" :severity="lookupSeverity" :closable="false" class="mt-4">
                                    {{ lookupMessage }}
                                </Message>
                            </div>

                            <div class="flex justify-end mt-6">
                                <Button label="Next" icon="pi pi-arrow-right" iconPos="right"
                                    @click="step = 2" :disabled="!form.scac" />
                            </div>
                        </div>

                        <!-- Step 2: Company Details -->
                        <div v-if="step === 2">
                            <h2 class="text-lg font-bold text-surface-900 mb-1">Company Details</h2>
                            <p class="text-sm text-surface-500 mb-6">
                                Review and complete your company information.
                            </p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-surface-700 mb-1">Company Name *</label>
                                    <InputText v-model="form.company_name" class="w-full" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-surface-700 mb-1">SCAC Code *</label>
                                    <InputText v-model="form.scac" class="w-full" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-surface-700 mb-1">USDOT Number</label>
                                    <InputText v-model="form.usdot" class="w-full" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-surface-700 mb-1">MC Number</label>
                                    <InputText v-model="form.mc_number" class="w-full" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-surface-700 mb-1">Fleet Size</label>
                                    <InputNumber v-model="form.fleet_size" class="w-full" :min="1" />
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-surface-700 mb-1">Address</label>
                                    <InputText v-model="form.address" class="w-full" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-surface-700 mb-1">City</label>
                                    <InputText v-model="form.city" class="w-full" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-surface-700 mb-1">State</label>
                                    <InputText v-model="form.state" class="w-full" maxlength="2" placeholder="FL" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-surface-700 mb-1">ZIP</label>
                                    <InputText v-model="form.zip" class="w-full" maxlength="10" />
                                </div>
                            </div>

                            <div class="flex justify-between mt-6">
                                <Button label="Back" icon="pi pi-arrow-left" outlined @click="step = 1" />
                                <Button label="Next" icon="pi pi-arrow-right" iconPos="right"
                                    @click="step = 3" :disabled="!form.company_name || !form.scac" />
                            </div>
                        </div>

                        <!-- Step 3: Contact & Submit -->
                        <div v-if="step === 3">
                            <h2 class="text-lg font-bold text-surface-900 mb-1">Contact Information</h2>
                            <p class="text-sm text-surface-500 mb-6">
                                Provide a primary contact for this carrier relationship.
                            </p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-surface-700 mb-1">Contact Name *</label>
                                    <InputText v-model="form.contact_name" class="w-full" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-surface-700 mb-1">Contact Email *</label>
                                    <InputText v-model="form.contact_email" type="email" class="w-full" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-surface-700 mb-1">Contact Phone</label>
                                    <InputText v-model="form.contact_phone" class="w-full" />
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-surface-700 mb-1">Equipment Types</label>
                                    <MultiSelect v-model="form.equipment_types" :options="equipmentOptions"
                                        optionLabel="label" optionValue="value" placeholder="Select equipment"
                                        class="w-full" />
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-surface-700 mb-1">Service Areas (States)</label>
                                    <Chips v-model="form.service_areas" placeholder="Type state codes (e.g. FL, GA)"
                                        class="w-full" />
                                </div>
                            </div>

                            <Message v-if="submitError" severity="error" :closable="false" class="mt-4">
                                {{ submitError }}
                            </Message>

                            <div class="flex justify-between mt-6">
                                <Button label="Back" icon="pi pi-arrow-left" outlined @click="step = 2" />
                                <Button label="Complete Onboarding" icon="pi pi-check" :loading="store.loading"
                                    @click="handleSubmit"
                                    :disabled="!form.contact_name || !form.contact_email" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCarrierOnboardingStore } from '@/stores/carrierOnboarding';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import MultiSelect from 'primevue/multiselect';
import Chips from 'primevue/chips';
import Divider from 'primevue/divider';
import Message from 'primevue/message';
import ProgressSpinner from 'primevue/progressspinner';

const route = useRoute();
const router = useRouter();
const store = useCarrierOnboardingStore();

const props = defineProps({
    tenantSlug: { type: String, required: true },
    token: { type: String, required: true },
});

const step = ref(1);
const error = ref(null);
const submitError = ref(null);
const usdotInput = ref('');
const lookupMessage = ref('');
const lookupSeverity = ref('info');
const inviteData = ref(null);

const stepLabels = ['Identify', 'Company', 'Contact'];

const form = reactive({
    company_name: '',
    scac: '',
    usdot: '',
    mc_number: '',
    contact_name: '',
    contact_email: '',
    contact_phone: '',
    address: '',
    city: '',
    state: '',
    zip: '',
    fleet_size: null,
    equipment_types: [],
    service_areas: [],
});

const equipmentOptions = [
    { label: 'Container Chassis', value: 'container' },
    { label: 'Flatbed', value: 'flatbed' },
    { label: 'Dry Van', value: 'dryvan' },
    { label: 'Reefer', value: 'reefer' },
    { label: 'Tanker', value: 'tanker' },
];

onMounted(async () => {
    try {
        const data = await store.validateInviteToken(props.tenantSlug, props.token);
        inviteData.value = data;

        // Pre-fill from invite if available
        if (data.email) form.contact_email = data.email;
        if (data.company_name) form.company_name = data.company_name;
    } catch (err) {
        const msg = err.response?.data?.message || err.response?.data?.error || 'This invite link is invalid or has expired.';
        error.value = msg;
    }
});

async function handleLookupScac() {
    if (!form.scac) return;
    lookupMessage.value = '';
    try {
        const result = await store.lookupScac(form.scac);
        if (result.found) {
            applyLookupData(result.carrier);
            lookupMessage.value = `Found: ${result.carrier.company_name} (source: ${result.carrier.source})`;
            lookupSeverity.value = 'success';
        } else {
            lookupMessage.value = result.message;
            lookupSeverity.value = 'warn';
        }
    } catch (err) {
        lookupMessage.value = 'Lookup failed. You can still enter your info manually.';
        lookupSeverity.value = 'warn';
    }
}

async function handleLookupUsdot() {
    if (!usdotInput.value) return;
    lookupMessage.value = '';
    try {
        const result = await store.lookupUsdot(usdotInput.value);
        if (result.found) {
            applyLookupData(result.carrier);
            lookupMessage.value = `Found: ${result.carrier.company_name} (source: ${result.carrier.source})`;
            lookupSeverity.value = 'success';
        } else {
            lookupMessage.value = result.message;
            lookupSeverity.value = 'warn';
        }
    } catch (err) {
        lookupMessage.value = 'Lookup failed. You can still enter your info manually.';
        lookupSeverity.value = 'warn';
    }
}

function applyLookupData(carrier) {
    if (carrier.company_name && !form.company_name) form.company_name = carrier.company_name;
    if (carrier.scac) form.scac = carrier.scac;
    if (carrier.usdot) form.usdot = carrier.usdot;
    if (carrier.mc_number) form.mc_number = carrier.mc_number;
    if (carrier.address) form.address = carrier.address;
    if (carrier.city) form.city = carrier.city;
    if (carrier.state) form.state = carrier.state;
    if (carrier.zip) form.zip = carrier.zip;
    if (carrier.contact_phone) form.contact_phone = carrier.contact_phone;
    if (carrier.fleet_size) form.fleet_size = carrier.fleet_size;
}

async function handleSubmit() {
    submitError.value = null;
    try {
        await store.completeOnboarding(props.tenantSlug, props.token, { ...form });
    } catch (err) {
        if (err.response?.status === 422) {
            const errors = err.response.data.errors;
            submitError.value = Object.values(errors).flat().join(' ');
        } else {
            submitError.value = err.response?.data?.message || 'Something went wrong. Please try again.';
        }
    }
}
</script>
