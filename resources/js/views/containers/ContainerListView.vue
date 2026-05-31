<template>
    <div>
        <PageHeader title="Containers" :subtitle="`${containersStore.totalContainers} total`">
            <template #actions>
                <DataExport @export="handleExport" />
                <Button
                    label="New Tracking Request"
                    icon="pi pi-plus"
                    size="small"
                    @click="$router.push({ name: 'tracking-requests-new' })"
                />
            </template>
        </PageHeader>

        <!-- Status tabs -->
        <div class="flex gap-1 mb-4 bg-gray-100 p-1 rounded-lg w-fit">
            <button
                v-for="tab in statusTabs"
                :key="tab.value"
                class="px-4 py-1.5 rounded-md text-sm font-medium transition-colors"
                :class="activeTab === tab.value
                    ? 'bg-white text-gray-900 shadow-sm'
                    : 'text-gray-500 hover:text-gray-700'"
                @click="setTab(tab.value)"
            >
                {{ tab.label }}
                <span v-if="tab.count !== undefined" class="ml-1.5 text-xs text-gray-400">{{ tab.count }}</span>
            </button>
        </div>

        <!-- Filters row -->
        <div class="flex items-center gap-3 mb-4">
            <span class="p-input-icon-left flex-1 max-w-sm">
                <i class="pi pi-search text-gray-400"></i>
                <InputText
                    v-model="search"
                    placeholder="Search container, MBL..."
                    class="pl-8 w-full"
                    size="small"
                    @input="debouncedSearch"
                />
            </span>
            <Select
                v-model="filters.carrier"
                :options="carrierOptions"
                option-label="label"
                option-value="value"
                placeholder="All Carriers"
                size="small"
                class="w-40"
                show-clear
                @change="applyFilters"
            />
            <Select
                v-model="filters.status"
                :options="statusOptions"
                option-label="label"
                option-value="value"
                placeholder="All Statuses"
                size="small"
                class="w-40"
                show-clear
                @change="applyFilters"
            />
            <Button
                icon="pi pi-filter"
                label="More Filters"
                outlined
                size="small"
                :badge="activeFilterCount > 0 ? String(activeFilterCount) : ''"
                @click="showFilters = !showFilters"
            />
        </div>

        <!-- Advanced filter builder -->
        <div v-if="showFilters" class="mb-4">
            <FilterBuilder :fields="filterFields" @apply="onFilterBuilderApply" @clear="clearFilters" />
        </div>

        <!-- Bulk actions bar -->
        <Transition name="slide-down">
            <div v-if="selectedRows.length" class="flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-lg px-4 py-2 mb-4">
                <span class="text-sm text-blue-700 font-medium">{{ selectedRows.length }} selected</span>
                <Button label="Update Status" size="small" text @click="bulkUpdateStatus" />
                <Button label="Export Selected" size="small" text @click="exportSelected" />
                <Button label="Deselect" size="small" text severity="secondary" @click="selectedRows = []" />
            </div>
        </Transition>

        <!-- DataTable -->
        <DataTable
            v-model:selection="selectedRows"
            :value="containersStore.containers"
            :loading="containersStore.loading"
            data-key="uuid"
            paginator
            :rows="pageSize"
            :first="(currentPage - 1) * pageSize"
            :total-records="containersStore.totalContainers"
            :rows-per-page-options="[10, 25, 50, 100]"
            lazy
            sort-mode="single"
            removable-sort
            striped-rows
            hover
            class="text-sm"
            @page="onPage"
            @sort="onSort"
        >
            <Column selection-mode="multiple" header-style="width: 3rem" />

            <Column field="container_number" header="Container #" sortable>
                <template #body="{ data }">
                    <router-link
                        :to="{ name: 'container-detail', params: { uuid: data.uuid } }"
                        class="font-mono font-semibold text-blue-600 hover:text-blue-800"
                    >
                        {{ data.container_number }}
                    </router-link>
                    <i
                        v-if="data.priority"
                        class="pi pi-star-fill text-yellow-400 text-xs ml-1"
                        title="Priority"
                    ></i>
                </template>
            </Column>

            <Column field="mbl_number" header="MBL" sortable>
                <template #body="{ data }">
                    <router-link
                        v-if="data.mbl_uuid"
                        :to="{ name: 'mbl-detail', params: { uuid: data.mbl_uuid } }"
                        class="text-blue-600 hover:underline text-xs"
                    >
                        {{ data.mbl_number || '—' }}
                    </router-link>
                    <span v-else class="text-gray-400 text-xs">—</span>
                </template>
            </Column>

            <Column field="status" header="Status" sortable>
                <template #body="{ data }">
                    <StatusBadge :status="data.status" size="small" />
                </template>
            </Column>

            <Column field="shipping_line" header="Carrier" sortable>
                <template #body="{ data }">
                    <span class="text-sm">{{ data.shipping_line || data.carrier_scac || '—' }}</span>
                </template>
            </Column>

            <Column field="pol_name" header="POL" sortable>
                <template #body="{ data }">
                    <span class="text-xs text-gray-600">{{ data.pol_name || data.pol || '—' }}</span>
                </template>
            </Column>

            <Column field="pod_name" header="POD" sortable>
                <template #body="{ data }">
                    <span class="text-xs text-gray-600">{{ data.pod_name || data.pod || '—' }}</span>
                </template>
            </Column>

            <Column field="eta" header="ETA" sortable>
                <template #body="{ data }">
                    <span :class="isOverdue(data.eta) ? 'text-red-600 font-medium' : 'text-gray-700'" class="text-xs">
                        {{ formatDate(data.eta) }}
                    </span>
                </template>
            </Column>

            <Column field="last_free_day" header="LFD" sortable>
                <template #body="{ data }">
                    <span
                        :class="lfdUrgency(data.last_free_day)"
                        class="text-xs font-medium"
                    >
                        {{ formatDate(data.last_free_day) }}
                    </span>
                </template>
            </Column>

            <Column header="" style="width: 3rem">
                <template #body="{ data }">
                    <Button
                        icon="pi pi-ellipsis-v"
                        text
                        rounded
                        size="small"
                        @click="openRowMenu($event, data)"
                    />
                </template>
            </Column>

            <template #empty>
                <div class="py-10 text-center text-gray-400">
                    <i class="pi pi-box text-3xl mb-2 block"></i>
                    <p>No containers found</p>
                </div>
            </template>
        </DataTable>

        <!-- Row context menu -->
        <Menu ref="rowMenu" :model="rowMenuItems" :popup="true" />
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Menu from 'primevue/menu';
import { debounce } from 'lodash-es';
import dayjs from 'dayjs';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import FilterBuilder from '@/components/FilterBuilder.vue';
import DataExport from '@/components/DataExport.vue';
import { useContainersStore } from '@/stores/containers';
import api from '@/plugins/api';

const props = defineProps({
    defaultFilter: { type: String, default: null },
});

const router = useRouter();
const containersStore = useContainersStore();

const selectedRows = ref([]);
const showFilters = ref(false);
const search = ref('');
const pageSize = ref(25);
const currentPage = ref(1);
const sortField = ref(null);
const sortOrder = ref(null);
const rowMenu = ref(null);
const rowMenuTarget = ref(null);
const activeTab = ref(props.defaultFilter || 'all');

const filters = reactive({
    status: null,
    carrier: null,
});

const statusTabs = [
    { label: 'All', value: 'all' },
    { label: 'Active', value: 'active' },
    { label: 'At Terminal', value: 'at_terminal' },
    { label: 'Not Tracking', value: 'not_tracking' },
];

const statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'In Transit', value: 'in_transit' },
    { label: 'On Vessel', value: 'on_vessel' },
    { label: 'At Terminal', value: 'at_terminal' },
    { label: 'Delivered', value: 'delivered' },
    { label: 'Not Tracking', value: 'not_tracking' },
    { label: 'Customs Hold', value: 'customs_hold' },
];

const carrierOptions = ref([]);

const filterFields = [
    { label: 'Container #', value: 'container_number', type: 'text' },
    { label: 'MBL Number', value: 'mbl_number', type: 'text' },
    { label: 'Status', value: 'status', type: 'select', options: statusOptions },
    { label: 'ETA', value: 'eta', type: 'date' },
    { label: 'Last Free Day', value: 'last_free_day', type: 'date' },
    { label: 'POL', value: 'pol', type: 'text' },
    { label: 'POD', value: 'pod', type: 'text' },
];

const activeFilterCount = computed(() =>
    Object.values(filters).filter(v => v !== null && v !== '').length,
);

const rowMenuItems = computed(() => [
    { label: 'View Details', icon: 'pi pi-eye', command: () => router.push({ name: 'container-detail', params: { uuid: rowMenuTarget.value?.uuid } }) },
    { label: 'Edit', icon: 'pi pi-pencil', command: () => {} },
    { separator: true },
    { label: 'Mark Priority', icon: 'pi pi-star', command: () => {} },
    { label: 'Delete', icon: 'pi pi-trash', class: 'text-red-600', command: () => {} },
]);

function setTab(tab) {
    activeTab.value = tab;
    filters.status = tab === 'all' ? null : tab;
    loadContainers();
}

function openRowMenu(event, data) {
    rowMenuTarget.value = data;
    rowMenu.value.toggle(event);
}

const debouncedSearch = debounce(() => {
    containersStore.filterContainers({ search: search.value || null });
    loadContainers();
}, 400);

function applyFilters() {
    containersStore.filterContainers(filters);
    loadContainers();
}

function onFilterBuilderApply(filterData) {
    // Convert filter builder rules to API params
    loadContainers();
}

function clearFilters() {
    filters.status = null;
    filters.carrier = null;
    search.value = '';
    containersStore.resetFilters();
    loadContainers();
}

async function loadContainers() {
    await containersStore.fetchContainers({
        page_num: currentPage.value - 1,
        page_size: pageSize.value,
        order_by: sortField.value,
        direction: sortOrder.value === 1 ? 1 : -1,
    });
}

function onPage(event) {
    currentPage.value = event.page + 1;
    pageSize.value = event.rows;
    loadContainers();
}

function onSort(event) {
    sortField.value = event.sortField;
    sortOrder.value = event.sortOrder;
    loadContainers();
}

async function handleExport(format) {
    try {
        const response = await api.get('/containers/export', {
            params: { format, ...containersStore.filters },
            responseType: 'blob',
        });
        const url = URL.createObjectURL(response.data);
        const a = document.createElement('a');
        a.href = url;
        a.download = `containers.${format}`;
        a.click();
        URL.revokeObjectURL(url);
    } catch {
        // handle error
    }
}

function exportSelected() {
    // export selected UUIDs
}

function bulkUpdateStatus() {
    // open bulk status dialog
}

function formatDate(date) {
    return date ? dayjs(date).format('MMM D, YYYY') : '—';
}

function isOverdue(date) {
    return date && dayjs(date).isBefore(dayjs(), 'day');
}

function lfdUrgency(lfd) {
    if (!lfd) return 'text-gray-400';
    const daysUntil = dayjs(lfd).diff(dayjs(), 'day');
    if (daysUntil < 0) return 'text-red-600';
    if (daysUntil <= 2) return 'text-orange-600';
    if (daysUntil <= 5) return 'text-yellow-600';
    return 'text-gray-700';
}

onMounted(async () => {
    if (props.defaultFilter) {
        filters.status = props.defaultFilter;
        containersStore.filterContainers({ status: props.defaultFilter });
    }
    await loadContainers();
    // Load carriers for filter
    try {
        const resp = await api.get('/carriers');
        carrierOptions.value = (resp.data.data || resp.data).map(c => ({ label: c.name, value: c.scac }));
    } catch {}
});
</script>
