<template>
    <div>
        <PageHeader
            title="Track a Container"
            subtitle="Enter a container number to fetch live status from JSONCargo"
        />

        <!-- Not configured warning (only shows if API key missing) -->
        <Message
            v-if="statusChecked && !jsonCargoStore.configured"
            severity="info"
            :closable="false"
            class="mb-6"
        >
            Live tracking requires the JSONCargo API key. It is configured on the server.
        </Message>

        <!-- Search card -->
        <div class="mx-auto max-w-2xl">
            <div class="rounded-xl border border-surface-200 bg-surface-0 p-6 shadow-sm">
                <div class="flex flex-col gap-4">
                    <!-- Container number input -->
                    <div class="flex gap-3">
                        <div class="relative flex-1">
                            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 pointer-events-none z-10"></i>
                            <InputText
                                ref="inputRef"
                                v-model="containerNumber"
                                placeholder="e.g. MEDU9091004"
                                class="w-full pl-9 text-lg h-12 font-mono tracking-wider uppercase"
                                :class="{ 'p-invalid': validationError }"
                                @input="onInput"
                                @keydown.enter="track"
                            />
                        </div>
                        <Button
                            label="Track"
                            icon="pi pi-arrow-right"
                            icon-pos="right"
                            class="h-12 px-6 font-semibold"
                            :loading="tracking"
                            :disabled="tracking"
                            @click="track"
                        />
                    </div>

                    <!-- Validation error -->
                    <p v-if="validationError" class="text-sm text-red-500 -mt-2">
                        {{ validationError }}
                    </p>

                    <!-- Shipping line selector -->
                    <div v-if="jsonCargoStore.shippingLines?.length" class="flex items-center gap-3">
                        <label class="text-sm text-surface-500 whitespace-nowrap">
                            Shipping line
                            <span class="text-surface-400 font-normal">(optional, for shared prefixes)</span>
                        </label>
                        <Select
                            v-model="selectedLine"
                            :options="shippingLineOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Auto-detect"
                            class="flex-1"
                            show-clear
                            size="small"
                        />
                    </div>

                    <!-- Example chips -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs text-surface-400">Try:</span>
                        <button
                            v-for="example in EXAMPLES"
                            :key="example"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full border border-teal-200 bg-teal-50 px-3 py-1 font-mono text-xs font-medium text-teal-700 transition-colors hover:bg-teal-100 hover:border-teal-300"
                            @click="fillAndTrack(example)"
                        >
                            {{ example }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Result states ── -->

        <!-- Loading -->
        <div v-if="tracking" class="mt-10 flex flex-col items-center gap-3 text-surface-500">
            <ProgressSpinner style="width: 48px; height: 48px;" stroke-width="4" />
            <p class="font-medium">Contacting carrier network&hellip;</p>
            <p class="text-sm text-surface-400">(this can take a few seconds)</p>
        </div>

        <!-- Not found (404) -->
        <div v-else-if="errorType === 'not_found'" class="mx-auto mt-8 max-w-2xl">
            <Message severity="warn" :closable="false">
                No tracking data found for <strong class="font-mono">{{ lastQueried }}</strong>.
                Check the number, or add the shipping line if it uses a shared prefix.
            </Message>
        </div>

        <!-- Provider / timeout error -->
        <div v-else-if="errorType === 'provider_error'" class="mx-auto mt-8 max-w-2xl">
            <Message severity="error" :closable="false">
                The carrier network didn't respond in time. Try again in a moment.
            </Message>
        </div>

        <!-- Success result -->
        <div v-else-if="result" class="mx-auto mt-8 max-w-3xl space-y-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-4 rounded-xl border border-surface-200 bg-surface-0 px-6 py-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <i class="pi pi-box text-teal-600 text-xl"></i>
                    <span class="font-mono text-xl font-bold text-surface-900 tracking-wider">
                        {{ result.container_id || lastQueried }}
                    </span>
                    <StatusBadge
                        v-if="result.container_status"
                        :status="normalizeStatus(result.container_status)"
                        size="normal"
                    />
                    <Tag
                        v-else
                        value="Unknown"
                        severity="secondary"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <span v-if="result.container_type" class="rounded-full bg-surface-100 px-3 py-1 text-xs font-medium text-surface-600">
                        {{ result.container_type }}
                    </span>
                </div>
            </div>

            <!-- Detail grid -->
            <div class="rounded-xl border border-surface-200 bg-surface-0 shadow-sm overflow-hidden">
                <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-surface-100">
                    <!-- Left column -->
                    <div class="divide-y divide-surface-100">
                        <DetailRow label="Carrier" :value="result.shipping_line_name" />
                        <DetailRow label="SCAC / Line ID" :value="result.shipping_line_id" mono />
                        <DetailRow label="Current Vessel" :value="vesselDisplay" />
                        <DetailRow label="Route" :value="routeDisplay" />
                        <DetailRow label="Loading Port" :value="result.loading_port" />
                        <DetailRow label="Discharging Port" :value="result.discharging_port" />
                    </div>
                    <!-- Right column -->
                    <div class="divide-y divide-surface-100">
                        <DetailRow label="Last Location" :value="result.last_location" />
                        <DetailRow label="Next Location" :value="result.next_location" />
                        <DetailRow label="ATD Origin" :value="formatDate(result.atd_origin)" />
                        <DetailRow label="ETA Final Dest." :value="formatDate(result.eta_final_destination)" />
                        <DetailRow label="Bill of Lading" :value="result.bill_of_lading" mono />
                        <DetailRow label="Last Updated" :value="formatDate(result.last_updated)" />
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3">
                <Button
                    label="Create Tracking Request from this"
                    icon="pi pi-plus"
                    class="font-medium"
                    :loading="creatingRequest"
                    @click="createTrackingRequest"
                />
                <Button
                    label="Track another"
                    icon="pi pi-refresh"
                    outlined
                    @click="reset"
                />
            </div>

            <!-- Create request feedback -->
            <Message v-if="requestCreated" severity="success" :closable="true" @close="requestCreated = false">
                Tracking request created successfully.
                <router-link to="/tracking-requests" class="ml-1 underline font-medium">View requests</router-link>
            </Message>
            <Message v-if="requestError" severity="error" :closable="true" @close="requestError = ''">
                {{ requestError }}
            </Message>

            <!-- Raw API response (collapsible) -->
            <details class="rounded-xl border border-surface-200 bg-surface-0 overflow-hidden shadow-sm">
                <summary class="flex cursor-pointer items-center gap-2 px-4 py-3 text-sm font-medium text-surface-600 hover:bg-surface-50 select-none">
                    <i class="pi pi-code text-surface-400"></i>
                    Raw API response
                    <i class="pi pi-chevron-down ml-auto text-xs text-surface-400 transition-transform details-chevron"></i>
                </summary>
                <pre class="overflow-x-auto bg-surface-50 px-4 py-3 text-xs font-mono text-surface-700 border-t border-surface-100 leading-relaxed">{{ JSON.stringify(result, null, 2) }}</pre>
            </details>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Message from 'primevue/message';
import ProgressSpinner from 'primevue/progressspinner';
import Tag from 'primevue/tag';
import dayjs from 'dayjs';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { useJsonCargoStore } from '@/stores/jsonCargo';
import api from '@/plugins/api';

// ── Sub-component: detail row ─────────────────────────────────────────────
const DetailRow = {
    props: {
        label: { type: String, required: true },
        value: { type: String, default: null },
        mono: { type: Boolean, default: false },
    },
    template: `
        <div class="flex flex-col gap-0.5 px-4 py-3">
            <span class="text-xs font-medium text-surface-400 uppercase tracking-wide">{{ label }}</span>
            <span :class="mono ? 'font-mono text-sm text-surface-700' : 'text-sm text-surface-800'">
                {{ value || '—' }}
            </span>
        </div>
    `,
};

// ── Constants ─────────────────────────────────────────────────────────────
const EXAMPLES = ['MEDU9091004', 'MSCU1234567'];

// ── Store + router ────────────────────────────────────────────────────────
const jsonCargoStore = useJsonCargoStore();
const router = useRouter();

// ── State ─────────────────────────────────────────────────────────────────
const containerNumber = ref('');
const selectedLine = ref(null);
const tracking = ref(false);
const result = ref(null);
const errorType = ref(null); // 'not_found' | 'provider_error' | null
const lastQueried = ref('');
const validationError = ref('');
const statusChecked = ref(false);
const creatingRequest = ref(false);
const requestCreated = ref(false);
const requestError = ref('');
const inputRef = ref(null);

// ── Computed ──────────────────────────────────────────────────────────────
const shippingLineOptions = computed(() =>
    (jsonCargoStore.shippingLines || []).map(line => ({
        label: typeof line === 'string' ? line : (line.name || line),
        value: typeof line === 'string' ? line : (line.id || line.name || line),
    })),
);

const vesselDisplay = computed(() => {
    if (!result.value) return null;
    const parts = [result.value.current_vessel_name];
    if (result.value.current_voyage_number) parts.push(`Voyage ${result.value.current_voyage_number}`);
    const joined = parts.filter(Boolean).join(' · ');
    return joined || (result.value.last_vessel_name ? `Last: ${result.value.last_vessel_name}` : null);
});

const routeDisplay = computed(() => {
    if (!result.value) return null;
    const from = result.value.shipped_from;
    const to = result.value.shipped_to;
    if (from && to) return `${from} → ${to}`;
    return from || to || null;
});

// ── Helpers ───────────────────────────────────────────────────────────────
function formatDate(val) {
    if (!val) return null;
    const d = dayjs(val);
    return d.isValid() ? d.format('MMM D, YYYY') : val;
}

function normalizeStatus(raw) {
    if (!raw) return 'unknown';
    // Map JSONCargo status strings to StatusBadge keys
    const map = {
        'In Transit': 'in_transit',
        'On Vessel': 'on_vessel',
        'At Terminal': 'at_terminal',
        'Delivered': 'delivered',
        'Active': 'active',
        'Delayed': 'delayed',
        'Customs Hold': 'customs_hold',
        'Empty Return': 'empty_return',
    };
    return map[raw] || raw.toLowerCase().replace(/\s+/g, '_');
}

function onInput(e) {
    // Force uppercase as they type
    containerNumber.value = e.target.value.toUpperCase();
    validationError.value = '';
}

function reset() {
    result.value = null;
    errorType.value = null;
    validationError.value = '';
    requestCreated.value = false;
    requestError.value = '';
    containerNumber.value = '';
    selectedLine.value = null;
    lastQueried.value = '';
}

// ── Core track action ─────────────────────────────────────────────────────
async function track() {
    const num = containerNumber.value.trim().toUpperCase();

    if (!num) {
        validationError.value = 'Enter a container number to track.';
        inputRef.value?.$el?.focus();
        return;
    }

    // Clear previous state
    result.value = null;
    errorType.value = null;
    validationError.value = '';
    requestCreated.value = false;
    requestError.value = '';
    lastQueried.value = num;
    tracking.value = true;

    try {
        const data = await jsonCargoStore.trackContainer(num, selectedLine.value || null);
        result.value = data;
    } catch (err) {
        const status = err.response?.status;
        if (status === 404) {
            errorType.value = 'not_found';
        } else {
            errorType.value = 'provider_error';
        }
    } finally {
        tracking.value = false;
    }
}

function fillAndTrack(example) {
    containerNumber.value = example;
    track();
}

// ── Create tracking request ───────────────────────────────────────────────
async function createTrackingRequest() {
    if (!result.value) return;
    creatingRequest.value = true;
    requestCreated.value = false;
    requestError.value = '';

    try {
        await api.post('/tracking-requests', {
            reference_number: lastQueried.value,
            type: 'container',
            scac: result.value.shipping_line_id || selectedLine.value || null,
        });
        requestCreated.value = true;
    } catch (err) {
        requestError.value = err.response?.data?.message || 'Failed to create tracking request. Try again.';
    } finally {
        creatingRequest.value = false;
    }
}

// ── Lifecycle ─────────────────────────────────────────────────────────────
onMounted(async () => {
    try {
        await jsonCargoStore.fetchStatus();
    } catch {
        // Non-fatal — tracking still works if status call fails
    } finally {
        statusChecked.value = true;
    }
});
</script>

<style scoped>
details[open] .details-chevron {
    transform: rotate(180deg);
}
</style>
