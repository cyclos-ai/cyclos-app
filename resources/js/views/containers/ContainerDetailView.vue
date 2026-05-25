<template>
    <div v-if="container">
        <!-- Header -->
        <div class="flex items-start justify-between mb-6">
            <div class="flex items-start gap-4">
                <button @click="$router.back()" class="mt-1 text-gray-400 hover:text-gray-600">
                    <i class="pi pi-arrow-left"></i>
                </button>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold font-mono text-gray-900">{{ container.container_number }}</h1>
                        <StatusBadge :status="container.status" />
                        <button @click="togglePriority" :title="container.priority ? 'Remove priority' : 'Set priority'">
                            <i :class="['pi pi-star text-lg', container.priority ? 'pi-star-fill text-yellow-400' : 'text-gray-300 hover:text-yellow-400']"></i>
                        </button>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ container.carrier_name }} &middot; {{ container.container_type }} &middot; {{ container.size }}ft
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Button label="Edit" icon="pi pi-pencil" outlined size="small" @click="showEdit = true" />
                <Button label="Mark Empty Return" icon="pi pi-refresh" outlined size="small" severity="secondary" @click="markEmptyReturn" />
                <Button icon="pi pi-trash" outlined size="small" severity="danger" @click="confirmDelete" />
            </div>
        </div>

        <!-- Tabs -->
        <TabView v-model:active-index="activeTab">
            <!-- Overview -->
            <TabPanel header="Overview">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 pt-4">
                    <div class="space-y-4">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Shipment Details</h3>
                        <DetailRow label="MBL Number" :value="container.mbl_number" :link="container.mbl_uuid ? { name: 'mbl-detail', params: { uuid: container.mbl_uuid } } : null" />
                        <DetailRow label="Booking Number" :value="container.booking_number" />
                        <DetailRow label="SCAC / Carrier" :value="`${container.scac || '—'} / ${container.carrier_name || '—'}`" />
                        <DetailRow label="Container Type" :value="container.container_type" />
                        <DetailRow label="Size" :value="container.size ? `${container.size}ft` : '—'" />
                        <DetailRow label="Weight" :value="container.weight ? `${container.weight.toLocaleString()} kg` : '—'" />
                        <DetailRow label="Commodity" :value="container.commodity" />
                    </div>
                    <div class="space-y-4">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Routing</h3>
                        <DetailRow label="POL" :value="container.pol_name || container.pol" />
                        <DetailRow label="POD" :value="container.pod_name || container.pod" />
                        <DetailRow label="Vessel" :value="container.vessel_name" :link="container.vessel_uuid ? { name: 'vessel-detail', params: { uuid: container.vessel_uuid } } : null" />
                        <DetailRow label="Voyage" :value="container.voyage_number" />
                        <DetailRow label="ETD" :value="formatDate(container.etd)" />
                        <DetailRow label="ETA" :value="formatDate(container.eta)" />
                        <DetailRow label="ATA" :value="formatDate(container.ata)" />
                    </div>
                    <div class="space-y-4">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Terminal & Charges</h3>
                        <DetailRow label="Terminal" :value="container.terminal_name" />
                        <DetailRow label="Last Free Day" :value="formatDate(container.last_free_day)" :highlight="lfdHighlight" />
                        <DetailRow label="Empty Return By" :value="formatDate(container.empty_return_by)" />
                        <DetailRow label="Demurrage Start" :value="formatDate(container.demurrage_start_date)" />
                        <DetailRow label="Detention Start" :value="formatDate(container.detention_start_date)" />
                        <DetailRow label="Priority" :value="container.priority ? 'Yes' : 'No'" />
                    </div>
                </div>
            </TabPanel>

            <!-- Timeline -->
            <TabPanel header="Timeline">
                <div class="pt-4 max-w-2xl">
                    <ContainerTimeline :events="container.events || []" />
                </div>
            </TabPanel>

            <!-- Demurrage/Detention -->
            <TabPanel header="Demurrage & Detention">
                <div class="pt-4 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Demurrage card -->
                    <div class="bg-orange-50 border border-orange-200 rounded-xl p-5">
                        <h3 class="font-semibold text-orange-800 mb-4 flex items-center gap-2">
                            <i class="pi pi-clock"></i> Demurrage
                        </h3>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Last Free Day</span>
                                <span class="font-medium">{{ formatDate(container.last_free_day) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Free Days</span>
                                <span class="font-medium">{{ container.demurrage_free_days || '—' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Days Accruing</span>
                                <span class="font-medium text-red-600">{{ container.demurrage_days_accruing || 0 }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Daily Rate</span>
                                <span class="font-medium">${{ container.demurrage_daily_rate || '—' }}</span>
                            </div>
                            <div class="border-t border-orange-200 pt-3 flex justify-between">
                                <span class="font-semibold text-gray-700">Current Charges</span>
                                <span class="font-bold text-orange-700 text-lg">${{ formatCurrency(container.demurrage_total) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Projected (7 days)</span>
                                <span class="font-medium text-orange-600">${{ formatCurrency(container.demurrage_projected_7d) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Detention card -->
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5">
                        <h3 class="font-semibold text-blue-800 mb-4 flex items-center gap-2">
                            <i class="pi pi-calendar-times"></i> Detention
                        </h3>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Return By</span>
                                <span class="font-medium">{{ formatDate(container.empty_return_by) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Free Days</span>
                                <span class="font-medium">{{ container.detention_free_days || '—' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Days Accruing</span>
                                <span class="font-medium text-red-600">{{ container.detention_days_accruing || 0 }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Daily Rate</span>
                                <span class="font-medium">${{ container.detention_daily_rate || '—' }}</span>
                            </div>
                            <div class="border-t border-blue-200 pt-3 flex justify-between">
                                <span class="font-semibold text-gray-700">Current Charges</span>
                                <span class="font-bold text-blue-700 text-lg">${{ formatCurrency(container.detention_total) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </TabPanel>

            <!-- Documents -->
            <TabPanel header="Documents">
                <div class="pt-4">
                    <div class="text-center py-10 text-gray-400">
                        <i class="pi pi-paperclip text-3xl mb-2 block"></i>
                        <p class="text-sm">No documents attached</p>
                        <Button label="Upload Document" icon="pi pi-upload" outlined size="small" class="mt-3" />
                    </div>
                </div>
            </TabPanel>

            <!-- Custom Fields -->
            <TabPanel header="Custom Fields">
                <div class="pt-4">
                    <div v-if="container.custom_fields?.length" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <DetailRow
                            v-for="field in container.custom_fields"
                            :key="field.column_uuid"
                            :label="field.column_name"
                            :value="field.value"
                        />
                    </div>
                    <div v-else class="text-center py-10 text-gray-400">
                        <i class="pi pi-sliders-v text-3xl mb-2 block"></i>
                        <p class="text-sm">No custom fields configured</p>
                        <router-link
                            to="/settings/custom-columns"
                            class="text-blue-600 text-sm hover:underline mt-2 block"
                        >
                            Configure custom columns
                        </router-link>
                    </div>
                </div>
            </TabPanel>

            <!-- History -->
            <TabPanel header="History">
                <div class="pt-4 max-w-2xl">
                    <div v-if="container.activity_log?.length" class="space-y-3">
                        <div
                            v-for="log in container.activity_log"
                            :key="log.id"
                            class="flex items-start gap-3 text-sm"
                        >
                            <div class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0">
                                <i class="pi pi-user text-gray-400 text-xs"></i>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">{{ log.causer_name }}</span>
                                <span class="text-gray-500"> {{ log.description }}</span>
                                <p class="text-xs text-gray-400 mt-0.5">{{ formatDateTime(log.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-10 text-gray-400">
                        <i class="pi pi-history text-3xl mb-2 block"></i>
                        <p class="text-sm">No activity recorded</p>
                    </div>
                </div>
            </TabPanel>
        </TabView>
    </div>

    <div v-else-if="loading" class="flex items-center justify-center py-20">
        <ProgressSpinner />
    </div>

    <div v-else class="text-center py-20 text-gray-400">
        <i class="pi pi-box text-4xl mb-3 block"></i>
        <p>Container not found</p>
        <Button label="Back to Containers" outlined class="mt-4" @click="$router.push({ name: 'containers' })" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import ProgressSpinner from 'primevue/progressspinner';
import dayjs from 'dayjs';
import StatusBadge from '@/components/StatusBadge.vue';
import ContainerTimeline from '@/components/ContainerTimeline.vue';
import { useContainersStore } from '@/stores/containers';

// Inline DetailRow component
const DetailRow = {
    props: ['label', 'value', 'link', 'highlight'],
    template: `
        <div class="flex justify-between items-baseline py-1.5 border-b border-gray-100 last:border-0">
            <span class="text-xs text-gray-500 flex-shrink-0 mr-3">{{ label }}</span>
            <router-link v-if="link" :to="link" class="text-sm font-medium text-blue-600 hover:underline text-right">{{ value || '—' }}</router-link>
            <span v-else :class="['text-sm font-medium text-right', highlight]">{{ value || '—' }}</span>
        </div>
    `,
};

const route = useRoute();
const router = useRouter();
const confirm = useConfirm();
const toast = useToast();
const containersStore = useContainersStore();

const loading = ref(false);
const showEdit = ref(false);
const activeTab = ref(0);

const container = computed(() => containersStore.currentContainer);

const lfdHighlight = computed(() => {
    const lfd = container.value?.last_free_day;
    if (!lfd) return '';
    const days = dayjs(lfd).diff(dayjs(), 'day');
    if (days < 0) return 'text-red-600';
    if (days <= 2) return 'text-orange-600';
    if (days <= 5) return 'text-yellow-600';
    return '';
});

function formatDate(d) { return d ? dayjs(d).format('MMM D, YYYY') : '—'; }
function formatDateTime(d) { return d ? dayjs(d).format('MMM D, YYYY h:mm A') : '—'; }
function formatCurrency(v) { return v ? Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0.00'; }

async function togglePriority() {
    await containersStore.updateContainer(container.value.uuid, { priority: !container.value.priority });
}

async function markEmptyReturn() {
    await containersStore.updateContainer(container.value.uuid, { status: 'empty_return' });
    toast.add({ severity: 'success', summary: 'Updated', detail: 'Container marked as empty return', life: 3000 });
}

function confirmDelete() {
    confirm.require({
        message: `Delete container ${container.value.container_number}? This cannot be undone.`,
        header: 'Confirm Delete',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            await containersStore.deleteContainer(container.value.uuid);
            router.push({ name: 'containers' });
        },
    });
}

onMounted(async () => {
    loading.value = true;
    try {
        await containersStore.fetchContainer(route.params.uuid);
    } finally {
        loading.value = false;
    }
});
</script>
