<template>
    <div>
        <!-- Not configured notice -->
        <Message v-if="!store.configured" severity="info" :closable="false" class="mb-4">
            Document extraction requires an Anthropic API key. Add <code class="font-mono text-xs bg-surface-100 px-1 rounded">ANTHROPIC_API_KEY</code> to your environment.
        </Message>

        <!-- Drop zone -->
        <div
            v-if="store.configured"
            class="relative"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <div
                class="flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed px-6 py-12 transition-colors cursor-pointer"
                :class="[
                    dragging
                        ? 'border-teal-400 bg-teal-50 dark:bg-teal-950/20'
                        : 'border-surface-300 dark:border-surface-600 hover:border-teal-400 hover:bg-surface-50 dark:hover:bg-surface-800/50',
                    store.loading ? 'pointer-events-none opacity-70' : '',
                ]"
                @click="!store.loading && fileInput?.click()"
            >
                <!-- Loading state -->
                <template v-if="store.loading">
                    <ProgressSpinner style="width: 48px; height: 48px" stroke-width="4" />
                    <p class="text-sm text-surface-500">Extracting data from <span class="font-semibold text-surface-700 dark:text-surface-300">{{ pendingFileName }}</span>...</p>
                </template>

                <!-- Idle state -->
                <template v-else>
                    <i class="pi pi-cloud-upload text-4xl text-teal-500"></i>
                    <div class="text-center">
                        <p class="text-sm font-medium text-surface-700 dark:text-surface-200">
                            Drop a Delivery Order, BOL, or Arrival Notice PDF here
                        </p>
                        <p class="text-xs text-surface-400 mt-1">or click to browse &mdash; PDF only, max 10 MB</p>
                    </div>
                </template>

                <input
                    ref="fileInput"
                    type="file"
                    accept="application/pdf,.pdf"
                    class="hidden"
                    @change="onFileSelected"
                />
            </div>
        </div>

        <!-- Error message -->
        <Message v-if="store.error" severity="error" :closable="true" class="mt-3" @close="store.error = null">
            {{ store.error }}
        </Message>

        <!-- Result summary card -->
        <div v-if="result && !store.loading" class="mt-4 rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 overflow-hidden">
            <!-- Card header -->
            <div class="flex items-center justify-between px-4 py-3 border-b border-surface-100 dark:border-surface-700 bg-surface-50 dark:bg-surface-900">
                <div class="flex items-center gap-2">
                    <i class="pi pi-file-pdf text-red-500"></i>
                    <span class="font-semibold text-sm text-surface-800 dark:text-surface-100 capitalize">
                        {{ formatDocType(result.document_type) }}
                    </span>
                </div>
                <span
                    class="text-xs font-semibold px-2 py-0.5 rounded-full"
                    :class="{
                        'bg-green-100 text-green-700': result.confidence === 'high',
                        'bg-yellow-100 text-yellow-700': result.confidence === 'medium',
                        'bg-red-100 text-red-700': result.confidence === 'low',
                    }"
                >
                    {{ result.confidence }} confidence
                </span>
            </div>

            <!-- Key fields grid -->
            <div class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-3 p-4">
                <div v-if="result.container_numbers?.length">
                    <p class="text-xs text-surface-400 uppercase tracking-wide mb-0.5">Containers</p>
                    <p class="text-sm font-mono font-semibold text-surface-800 dark:text-surface-100">
                        {{ result.container_numbers.join(', ') }}
                    </p>
                </div>
                <div v-if="result.mbl_number">
                    <p class="text-xs text-surface-400 uppercase tracking-wide mb-0.5">MBL</p>
                    <p class="text-sm font-mono font-semibold text-surface-800 dark:text-surface-100">{{ result.mbl_number }}</p>
                </div>
                <div v-if="result.booking_number">
                    <p class="text-xs text-surface-400 uppercase tracking-wide mb-0.5">Booking</p>
                    <p class="text-sm font-mono font-semibold text-surface-800 dark:text-surface-100">{{ result.booking_number }}</p>
                </div>
                <div v-if="result.carrier_name || result.carrier_scac">
                    <p class="text-xs text-surface-400 uppercase tracking-wide mb-0.5">Carrier</p>
                    <p class="text-sm text-surface-800 dark:text-surface-100">
                        {{ result.carrier_name || '' }}
                        <span v-if="result.carrier_scac" class="text-xs text-surface-400 ml-1">({{ result.carrier_scac }})</span>
                    </p>
                </div>
                <div v-if="result.pol || result.pod">
                    <p class="text-xs text-surface-400 uppercase tracking-wide mb-0.5">Route</p>
                    <p class="text-sm text-surface-800 dark:text-surface-100">
                        {{ result.pol || '—' }} &rarr; {{ result.pod || '—' }}
                    </p>
                </div>
                <div v-if="result.eta">
                    <p class="text-xs text-surface-400 uppercase tracking-wide mb-0.5">ETA</p>
                    <p class="text-sm text-surface-800 dark:text-surface-100">{{ result.eta }}</p>
                </div>
                <div v-if="result.vessel_name">
                    <p class="text-xs text-surface-400 uppercase tracking-wide mb-0.5">Vessel</p>
                    <p class="text-sm text-surface-800 dark:text-surface-100">
                        {{ result.vessel_name }}
                        <span v-if="result.voyage_number" class="text-xs text-surface-400 ml-1">v.{{ result.voyage_number }}</span>
                    </p>
                </div>
                <div v-if="result.shipper">
                    <p class="text-xs text-surface-400 uppercase tracking-wide mb-0.5">Shipper</p>
                    <p class="text-sm text-surface-800 dark:text-surface-100 truncate">{{ result.shipper }}</p>
                </div>
                <div v-if="result.consignee">
                    <p class="text-xs text-surface-400 uppercase tracking-wide mb-0.5">Consignee</p>
                    <p class="text-sm text-surface-800 dark:text-surface-100 truncate">{{ result.consignee }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import Message from 'primevue/message';
import ProgressSpinner from 'primevue/progressspinner';
import { useDocumentExtractionStore } from '@/stores/documentExtraction';

const emit = defineEmits(['extracted']);

const store = useDocumentExtractionStore();
const fileInput = ref(null);
const dragging = ref(false);
const pendingFileName = ref('');
const result = ref(null);

function formatDocType(type) {
    if (!type) return 'Document';
    return type.replace(/_/g, ' ');
}

async function processFile(file) {
    if (!file) return;
    if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
        store.error = 'Only PDF files are supported.';
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        store.error = 'File exceeds the 10 MB limit.';
        return;
    }

    pendingFileName.value = file.name;
    result.value = null;
    store.error = null;

    try {
        const data = await store.extractFromFile(file);
        result.value = data;
        emit('extracted', data);
    } catch {
        // error already set in store
    }
}

function onDrop(event) {
    dragging.value = false;
    const file = event.dataTransfer?.files?.[0];
    if (file) processFile(file);
}

function onFileSelected(event) {
    const file = event.target?.files?.[0];
    if (file) processFile(file);
    // reset so same file can be re-selected
    if (fileInput.value) fileInput.value.value = '';
}

onMounted(() => {
    store.checkStatus();
});
</script>
