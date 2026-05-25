<template>
    <div>
        <PageHeader title="Dashboard">
            <template #actions>
                <DatePicker
                    v-model="dateRange"
                    selection-mode="range"
                    :manual-input="false"
                    placeholder="Date range"
                    size="small"
                    show-icon
                    class="w-52"
                    @update:model-value="loadStats"
                />
                <Button
                    label="Add Widget"
                    icon="pi pi-plus"
                    size="small"
                    @click="showAddWidget = true"
                />
            </template>
        </PageHeader>

        <!-- Quick stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <StatCard
                label="Total Containers"
                :value="stats.total_containers"
                icon="pi-box"
                icon-color="#06c4a7"
                icon-bg="bg-primary-50"
                :trend="stats.containers_trend"
            />
            <StatCard
                label="In Transit"
                :value="stats.in_transit"
                icon="pi-send"
                icon-color="#0ea5e9"
                icon-bg="bg-sky-50"
                :trend="stats.transit_trend"
            />
            <StatCard
                label="At Terminal"
                :value="stats.at_terminal"
                icon="pi-map-marker"
                icon-color="#f59e0b"
                icon-bg="bg-amber-50"
                :trend="stats.terminal_trend"
            />
            <StatCard
                label="Alerts"
                :value="stats.alerts"
                icon="pi-exclamation-triangle"
                icon-color="#ef4444"
                icon-bg="bg-rose-50"
                :trend="stats.alerts_trend"
            />
        </div>

        <!-- Widgets grid -->
        <div v-if="widgets.length" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
            <div
                v-for="widget in widgets"
                :key="widget.uuid"
                class="bg-white rounded-xl border border-surface-200 p-4"
                :class="widget.size === 'large' ? 'lg:col-span-2' : ''"
            >
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-surface-800">{{ widget.title }}</h3>
                    <div class="flex items-center gap-1">
                        <Button icon="pi pi-pencil" text size="small" rounded severity="secondary" @click="editWidget(widget)" />
                        <Button icon="pi pi-times" text size="small" rounded severity="secondary" @click="removeWidget(widget)" />
                    </div>
                </div>

                <!-- Chart widget -->
                <div v-if="['bar', 'line', 'pie', 'doughnut'].includes(widget.type)" class="h-48">
                    <WidgetChart
                        :type="widget.type"
                        :data="widget.data || placeholderChartData(widget.type)"
                        :options="widget.options || {}"
                    />
                </div>

                <!-- Stat widget -->
                <div v-else-if="widget.type === 'stat'" class="py-4">
                    <p class="text-4xl font-bold text-surface-900">{{ widget.value || '—' }}</p>
                    <p class="text-sm text-surface-500 mt-1">{{ widget.subtitle }}</p>
                </div>

                <!-- Table widget -->
                <div v-else-if="widget.type === 'table'">
                    <DataTable
                        :value="widget.data || []"
                        size="small"
                        class="text-xs"
                        :rows="5"
                    >
                        <Column
                            v-for="col in widget.columns || []"
                            :key="col.field"
                            :field="col.field"
                            :header="col.header"
                        />
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="bg-white rounded-xl border border-dashed border-surface-300 p-12 text-center">
            <i class="pi pi-chart-bar text-4xl text-surface-300 mb-3 block"></i>
            <h3 class="text-surface-600 font-medium mb-1">No widgets yet</h3>
            <p class="text-sm text-surface-400 mb-4">Add widgets to customize your dashboard</p>
            <Button label="Add Widget" icon="pi pi-plus" @click="showAddWidget = true" />
        </div>

        <!-- Add Widget Dialog -->
        <Dialog v-model:visible="showAddWidget" header="Add Widget" modal class="w-96">
            <div class="space-y-4 pt-2">
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Widget Title</label>
                    <InputText v-model="newWidget.title" class="w-full" placeholder="My Widget" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Type</label>
                    <Select
                        v-model="newWidget.type"
                        :options="widgetTypes"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-700 mb-1">Size</label>
                    <SelectButton v-model="newWidget.size" :options="['normal', 'large']" class="w-full" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" text @click="showAddWidget = false" />
                <Button label="Add Widget" @click="addWidget" :loading="adding" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import WidgetChart from '@/components/WidgetChart.vue';
import { useDashboardStore } from '@/stores/dashboard';

const dashboardStore = useDashboardStore();

const dateRange = ref(null);
const showAddWidget = ref(false);
const adding = ref(false);

const stats = reactive({
    total_containers: 0,
    in_transit: 0,
    at_terminal: 0,
    alerts: 0,
    containers_trend: null,
    transit_trend: null,
    terminal_trend: null,
    alerts_trend: null,
});

const widgets = ref([]);

const newWidget = reactive({ title: '', type: 'bar', size: 'normal' });

const widgetTypes = [
    { label: 'Bar Chart', value: 'bar' },
    { label: 'Line Chart', value: 'line' },
    { label: 'Pie Chart', value: 'pie' },
    { label: 'Stat Card', value: 'stat' },
    { label: 'Data Table', value: 'table' },
];

function placeholderChartData(type) {
    const labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
    const data = [12, 19, 8, 15, 22, 14];
    if (type === 'pie' || type === 'doughnut') {
        return {
            labels: ['Active', 'At Terminal', 'In Transit', 'Delivered'],
            datasets: [{ data: [35, 20, 28, 17], backgroundColor: ['#06c4a7', '#f59e0b', '#0ea5e9', '#22c55e'] }],
        };
    }
    return {
        labels,
        datasets: [{ label: 'Containers', data, backgroundColor: '#06c4a7', borderColor: '#06c4a7', fill: false, tension: 0.4 }],
    };
}

async function loadStats() {
    try {
        const data = await dashboardStore.fetchDashboardStats();
        Object.assign(stats, data);
    } catch {
        // Use zeros
    }
}

async function addWidget() {
    if (!newWidget.title) return;
    adding.value = true;
    try {
        const dashboard = dashboardStore.currentDashboard;
        if (dashboard) {
            const w = await dashboardStore.addWidget(dashboard.uuid, { ...newWidget });
            widgets.value.push(w);
        } else {
            widgets.value.push({ uuid: Date.now().toString(), ...newWidget, data: null });
        }
        showAddWidget.value = false;
        Object.assign(newWidget, { title: '', type: 'bar', size: 'normal' });
    } finally {
        adding.value = false;
    }
}

function editWidget(widget) {
    // Open edit dialog - simplified
}

async function removeWidget(widget) {
    widgets.value = widgets.value.filter(w => w.uuid !== widget.uuid);
    const dashboard = dashboardStore.currentDashboard;
    if (dashboard) {
        await dashboardStore.deleteWidget(dashboard.uuid, widget.uuid).catch(() => {});
    }
}

onMounted(async () => {
    await loadStats();
    try {
        const data = await dashboardStore.fetchDefaultDashboard();
        widgets.value = data.widgets || [];
    } catch {
        widgets.value = [];
    }
});
</script>
