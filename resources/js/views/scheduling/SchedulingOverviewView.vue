<template>
    <div>
        <PageHeader title="Scheduling Overview" subtitle="Terminal container status and volume metrics">
            <template #actions>
                <Button icon="pi pi-refresh" text size="small" rounded severity="secondary" :loading="loadingStats" @click="loadAll" />
                <Button label="Export" icon="pi pi-download" outlined size="small" />
            </template>
        </PageHeader>

        <!-- Stat Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow border-b-4 border-b-blue-500">
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-500 font-medium">Arriving Today</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1 leading-tight">{{ stats.arriving_today ?? '—' }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 ml-4 bg-blue-50">
                        <i class="pi pi-truck text-xl" style="color: #3b82f6;"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow border-b-4 border-b-cyan-500">
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-500 font-medium">Must Out Gate</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1 leading-tight">{{ stats.must_out_gate ?? '—' }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 ml-4 bg-cyan-50">
                        <i class="pi pi-directions text-xl" style="color: #06b6d4;"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow border-b-4 border-b-rose-500">
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-500 font-medium">Must Return</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1 leading-tight">{{ stats.must_return ?? '—' }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 ml-4 bg-rose-50">
                        <i class="pi pi-replay text-xl" style="color: #f43f5e;"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow border-b-4 border-b-yellow-500">
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-500 font-medium">At Terminal Holds</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1 leading-tight">{{ stats.at_terminal_holds ?? '—' }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 ml-4 bg-yellow-50">
                        <i class="pi pi-exclamation-circle text-xl" style="color: #eab308;"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Containers at Terminal -->
        <div class="bg-white rounded-xl border border-gray-200 mb-6">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-800">Containers at Terminal</h2>
                <div class="flex items-center gap-2">
                    <span class="p-input-icon-left">
                        <i class="pi pi-search text-gray-400 text-sm"></i>
                        <InputText
                            v-model="terminalSearch"
                            placeholder="Search groups..."
                            size="small"
                            class="pl-7 w-48 text-sm"
                        />
                    </span>
                    <Button icon="pi pi-filter" text size="small" rounded severity="secondary" />
                </div>
            </div>

            <DataTable
                :value="filteredTerminalGroups"
                :loading="loadingTerminal"
                row-group-mode="subheader"
                group-rows-by="group_name"
                expandable-row-groups
                v-model:expanded-row-groups="expandedGroups"
                data-key="uuid"
                size="small"
                class="text-sm"
            >
                <template #groupheader="{ data }">
                    <div class="flex items-center gap-2 py-1">
                        <i class="pi pi-building text-gray-400 text-xs"></i>
                        <span class="font-semibold text-gray-800 text-sm">{{ data.group_name }}</span>
                        <span class="text-xs text-gray-400 ml-1">({{ groupCount(data.group_name) }} containers)</span>
                    </div>
                </template>

                <Column field="group_name" header="Group" class="w-40">
                    <template #body="{ data }">
                        <span class="text-gray-700 text-xs">{{ data.group_name }}</span>
                    </template>
                </Column>

                <Column field="scheduled_drop_date_this_week" header="Sched. Drop Date This Week" class="text-center">
                    <template #body="{ data }">
                        <span :class="data.scheduled_drop_date_this_week ? 'text-gray-800' : 'text-gray-400'" class="text-xs">
                            {{ formatDate(data.scheduled_drop_date_this_week) }}
                        </span>
                    </template>
                </Column>

                <Column field="scheduled_drop_today" header="Sched. Drop Today" class="text-center">
                    <template #body="{ data }">
                        <span :class="data.scheduled_drop_today ? 'font-semibold text-blue-700' : 'text-gray-400'" class="text-xs">
                            {{ data.scheduled_drop_today ?? '—' }}
                        </span>
                    </template>
                </Column>

                <Column field="scheduled_drop_this_week_not_scheduled" header="This Week Not Scheduled" class="text-center">
                    <template #body="{ data }">
                        <span :class="data.scheduled_drop_this_week_not_scheduled > 0 ? 'text-orange-600 font-medium' : 'text-gray-400'" class="text-xs">
                            {{ data.scheduled_drop_this_week_not_scheduled ?? '—' }}
                        </span>
                    </template>
                </Column>

                <Column field="delivered_this_week" header="Delivered This Week" class="text-center">
                    <template #body="{ data }">
                        <span :class="data.delivered_this_week > 0 ? 'text-green-700 font-medium' : 'text-gray-400'" class="text-xs">
                            {{ data.delivered_this_week ?? '—' }}
                        </span>
                    </template>
                </Column>

                <Column field="delivery_today" header="Delivery Today" class="text-center">
                    <template #body="{ data }">
                        <span :class="data.delivery_today > 0 ? 'text-green-700 font-semibold' : 'text-gray-400'" class="text-xs">
                            {{ data.delivery_today ?? '—' }}
                        </span>
                    </template>
                </Column>

                <template #empty>
                    <div class="py-10 text-center text-gray-400">
                        <i class="pi pi-inbox text-3xl mb-2 block"></i>
                        <p class="text-sm">No containers at terminal</p>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Volume Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- Inbound Volume by Dray Carrier -->
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-800">Inbound Volume by Dray Carrier</h3>
                    <div class="flex items-center gap-1">
                        <Button icon="pi pi-filter" text size="small" rounded severity="secondary" />
                        <Button icon="pi pi-star" text size="small" rounded severity="secondary" />
                        <Button icon="pi pi-ellipsis-v" text size="small" rounded severity="secondary" />
                    </div>
                </div>
                <div class="h-56" v-if="!loadingCarrier">
                    <WidgetChart
                        type="doughnut"
                        :data="carrierChartData"
                        :options="doughnutOptions"
                    />
                </div>
                <div v-else class="h-56 flex items-center justify-center">
                    <i class="pi pi-spin pi-spinner text-2xl text-gray-300"></i>
                </div>
            </div>

            <!-- Inbound Volume by Final Destination -->
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-800">Inbound Volume by Final Destination</h3>
                    <div class="flex items-center gap-1">
                        <Button icon="pi pi-filter" text size="small" rounded severity="secondary" />
                        <Button icon="pi pi-star" text size="small" rounded severity="secondary" />
                        <Button icon="pi pi-ellipsis-v" text size="small" rounded severity="secondary" />
                    </div>
                </div>
                <div class="h-56" v-if="!loadingDestination">
                    <WidgetChart
                        type="doughnut"
                        :data="destinationChartData"
                        :options="doughnutOptions"
                    />
                </div>
                <div v-else class="h-56 flex items-center justify-center">
                    <i class="pi pi-spin pi-spinner text-2xl text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import PageHeader from '@/components/PageHeader.vue';
import WidgetChart from '@/components/WidgetChart.vue';
import { useApi } from '@/composables/useApi';
import dayjs from 'dayjs';

const { execute } = useApi();

// Loading states
const loadingStats       = ref(false);
const loadingTerminal    = ref(false);
const loadingCarrier     = ref(false);
const loadingDestination = ref(false);

// Data
const stats = reactive({
    arriving_today:     null,
    must_out_gate:      null,
    must_return:        null,
    at_terminal_holds:  null,
});

const terminalGroups   = ref([]);
const terminalSearch   = ref('');
const expandedGroups   = ref([]);

const carrierData      = ref([]);
const destinationData  = ref([]);

// Palette shared across charts
const CHART_COLORS = [
    '#3b82f6', '#06b6d4', '#8b5cf6', '#f97316',
    '#22c55e', '#f43f5e', '#eab308', '#64748b',
    '#10b981', '#a855f7',
];

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '65%',
    plugins: {
        legend: {
            position: 'right',
            labels: { boxWidth: 12, font: { size: 11 }, padding: 8 },
        },
        tooltip: {
            backgroundColor: '#1e293b',
            titleFont: { size: 12 },
            bodyFont: { size: 11 },
            padding: 10,
            cornerRadius: 8,
        },
    },
};

const filteredTerminalGroups = computed(() => {
    if (!terminalSearch.value) return terminalGroups.value;
    const q = terminalSearch.value.toLowerCase();
    return terminalGroups.value.filter(row => row.group_name?.toLowerCase().includes(q));
});

function groupCount(groupName) {
    return terminalGroups.value.filter(r => r.group_name === groupName).length;
}

const carrierChartData = computed(() => ({
    labels: carrierData.value.map(d => d.carrier || d.label || 'Unknown'),
    datasets: [{
        data: carrierData.value.map(d => d.count || d.value || 0),
        backgroundColor: CHART_COLORS.slice(0, carrierData.value.length),
        borderWidth: 0,
    }],
}));

const destinationChartData = computed(() => ({
    labels: destinationData.value.map(d => d.destination || d.label || 'Unknown'),
    datasets: [{
        data: destinationData.value.map(d => d.count || d.value || 0),
        backgroundColor: CHART_COLORS.slice(0, destinationData.value.length),
        borderWidth: 0,
    }],
}));

function formatDate(date) {
    return date ? dayjs(date).format('MMM D') : '—';
}

async function loadStats() {
    loadingStats.value = true;
    try {
        const data = await execute('get', '/drayage/overview-stats');
        Object.assign(stats, data?.data ?? data ?? {});
    } catch {
        // keep zeros
    } finally {
        loadingStats.value = false;
    }
}

async function loadTerminal() {
    loadingTerminal.value = true;
    try {
        const data = await execute('get', '/drayage/containers-at-terminal');
        terminalGroups.value = data?.data ?? data ?? [];
    } catch {
        terminalGroups.value = [];
    } finally {
        loadingTerminal.value = false;
    }
}

async function loadCarrier() {
    loadingCarrier.value = true;
    try {
        const data = await execute('get', '/drayage/volume-by-carrier');
        carrierData.value = data?.data ?? data ?? [];
    } catch {
        carrierData.value = [];
    } finally {
        loadingCarrier.value = false;
    }
}

async function loadDestination() {
    loadingDestination.value = true;
    try {
        const data = await execute('get', '/drayage/volume-by-destination');
        destinationData.value = data?.data ?? data ?? [];
    } catch {
        destinationData.value = [];
    } finally {
        loadingDestination.value = false;
    }
}

async function loadAll() {
    await Promise.allSettled([loadStats(), loadTerminal(), loadCarrier(), loadDestination()]);
}

onMounted(loadAll);
</script>
