<template>
    <div>
        <PageHeader title="Rail Shipments" :subtitle="`${railStore.pagination.total} total`">
            <template #actions>
                <Button
                    label="New Shipment"
                    icon="pi pi-plus"
                    size="small"
                    @click="showCreateDialog = true"
                />
            </template>
        </PageHeader>

        <!-- Filters row -->
        <div class="flex items-center gap-3 mb-4">
            <span class="p-input-icon-left flex-1 max-w-sm">
                <i class="pi pi-search text-gray-400"></i>
                <InputText
                    v-model="search"
                    placeholder="Container #, train ID..."
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
                class="w-36"
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
                icon="pi pi-map"
                label="Map View"
                outlined
                size="small"
                @click="$router.push({ name: 'rail-map' })"
            />
        </div>

        <!-- DataTable -->
        <DataTable
            :value="railStore.shipments"
            :loading="railStore.loading"
            data-key="uuid"
            paginator
            :rows="pageSize"
            :first="(currentPage - 1) * pageSize"
            :total-records="railStore.pagination.total"
            :rows-per-page-options="[10, 25, 50, 100]"
            lazy
            sort-mode="single"
            removable-sort
            striped-rows
            hover
            class="text-sm"
            @page="onPage"
            @sort="onSort"
            @row-click="onRowClick"
        >
            <Column field="container_number" header="Container #" sortable>
                <template #body="{ data }">
                    <span class="font-mono font-semibold text-blue-600 cursor-pointer hover:text-blue-800" @click="openDetail(data)">
                        {{ data.container_number }}
                    </span>
                </template>
            </Column>

            <Column field="rail_carrier" header="Rail Carrier" sortable>
                <template #body="{ data }">
                    <span
                        class="font-semibold text-sm"
                        :style="{ color: carrierColor(data.rail_carrier) }"
                    >
                        {{ data.rail_carrier || '—' }}
                    </span>
                </template>
            </Column>

            <Column field="origin_ramp_code" header="Origin Ramp" sortable>
                <template #body="{ data }">
                    <span class="font-mono text-xs text-gray-700">{{ data.origin_ramp_code || '—' }}</span>
                    <span v-if="data.origin_ramp_name" class="block text-xs text-gray-400">{{ data.origin_ramp_name }}</span>
                </template>
            </Column>

            <Column field="destination_ramp_code" header="Dest. Ramp" sortable>
                <template #body="{ data }">
                    <span class="font-mono text-xs text-gray-700">{{ data.destination_ramp_code || '—' }}</span>
                    <span v-if="data.destination_ramp_name" class="block text-xs text-gray-400">{{ data.destination_ramp_name }}</span>
                </template>
            </Column>

            <Column field="status" header="Status" sortable>
                <template #body="{ data }">
                    <StatusBadge :status="data.status" size="small" />
                </template>
            </Column>

            <Column field="train_id" header="Train ID" sortable>
                <template #body="{ data }">
                    <span class="font-mono text-xs">{{ data.train_id || '—' }}</span>
                </template>
            </Column>

            <Column field="departed_at" header="Departed" sortable>
                <template #body="{ data }">
                    <span class="text-xs text-gray-600">{{ formatDate(data.departed_at) }}</span>
                </template>
            </Column>

            <Column field="eta" header="ETA" sortable>
                <template #body="{ data }">
                    <span
                        class="text-xs font-medium"
                        :class="isOverdue(data.eta) ? 'text-red-600' : 'text-gray-700'"
                    >
                        {{ formatDate(data.eta) }}
                    </span>
                </template>
            </Column>

            <Column field="arrived_at" header="Arrived" sortable>
                <template #body="{ data }">
                    <span class="text-xs text-gray-600">{{ formatDate(data.arrived_at) }}</span>
                </template>
            </Column>

            <Column header="" style="width: 3rem">
                <template #body="{ data }">
                    <Button
                        icon="pi pi-ellipsis-v"
                        text
                        rounded
                        size="small"
                        @click.stop="openRowMenu($event, data)"
                    />
                </template>
            </Column>

            <template #empty>
                <div class="py-10 text-center text-gray-400">
                    <i class="pi pi-list text-3xl mb-2 block"></i>
                    <p>No rail shipments found</p>
                </div>
            </template>
        </DataTable>

        <!-- Row context menu -->
        <Menu ref="rowMenu" :model="rowMenuItems" :popup="true" />

        <!-- Create dialog -->
        <CreateRailShipmentDialog
            v-model:visible="showCreateDialog"
            @created="onShipmentCreated"
        />

        <!-- Detail dialog -->
        <Dialog
            v-model:visible="showDetailDialog"
            :header="detailShipment?.container_number || 'Shipment Detail'"
            modal
            :style="{ width: '520px' }"
        >
            <div v-if="detailShipment" class="space-y-3 text-sm">
                <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                    <div>
                        <p class="text-xs text-gray-500 mb-0.5">Container #</p>
                        <p class="font-mono font-semibold">{{ detailShipment.container_number }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-0.5">Status</p>
                        <StatusBadge :status="detailShipment.status" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-0.5">Rail Carrier</p>
                        <p class="font-semibold" :style="{ color: carrierColor(detailShipment.rail_carrier) }">{{ detailShipment.rail_carrier || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-0.5">Train ID</p>
                        <p class="font-mono">{{ detailShipment.train_id || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-0.5">Origin Ramp</p>
                        <p class="font-mono">{{ detailShipment.origin_ramp_code || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-0.5">Destination Ramp</p>
                        <p class="font-mono">{{ detailShipment.destination_ramp_code || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-0.5">Departed</p>
                        <p>{{ formatDate(detailShipment.departed_at) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-0.5">ETA</p>
                        <p :class="isOverdue(detailShipment.eta) ? 'text-red-600 font-medium' : ''">{{ formatDate(detailShipment.eta) }}</p>
                    </div>
                </div>
                <div v-if="detailShipment.notes">
                    <p class="text-xs text-gray-500 mb-0.5">Notes</p>
                    <p class="text-gray-700">{{ detailShipment.notes }}</p>
                </div>
            </div>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Menu from 'primevue/menu';
import Dialog from 'primevue/dialog';
import { debounce } from 'lodash-es';
import dayjs from 'dayjs';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import CreateRailShipmentDialog from '@/components/rail/CreateRailShipmentDialog.vue';
import { useRailStore } from '@/stores/rail';

const router = useRouter();
const railStore = useRailStore();

const showCreateDialog = ref(false);
const showDetailDialog = ref(false);
const detailShipment = ref(null);
const search = ref('');
const pageSize = ref(25);
const currentPage = ref(1);
const sortField = ref(null);
const sortOrder = ref(null);
const rowMenu = ref(null);
const rowMenuTarget = ref(null);

const filters = reactive({
    carrier: null,
    status: null,
    search: null,
});

const CARRIER_COLORS = {
    BNSF: '#f97316',
    UP:   '#eab308',
    CSX:  '#3b82f6',
    NS:   '#22c55e',
    CN:   '#ef4444',
    CP:   '#a855f7',
    KCS:  '#14b8a6',
};

const carrierOptions = Object.keys(CARRIER_COLORS).map(k => ({ label: k, value: k }));

const statusOptions = [
    { label: 'Pending',    value: 'pending' },
    { label: 'Loaded',     value: 'loaded' },
    { label: 'In Transit', value: 'in_transit' },
    { label: 'Arrived',    value: 'arrived' },
    { label: 'Available',  value: 'available' },
    { label: 'Picked Up',  value: 'picked_up' },
];

const rowMenuItems = computed(() => [
    { label: 'View Details', icon: 'pi pi-eye',    command: () => openDetail(rowMenuTarget.value) },
    { label: 'View on Map',  icon: 'pi pi-map',    command: () => router.push({ name: 'rail-map' }) },
    { separator: true },
    { label: 'Mark Arrived', icon: 'pi pi-check',  command: () => updateStatus(rowMenuTarget.value, 'arrived') },
    { label: 'Mark Picked Up', icon: 'pi pi-check-circle', command: () => updateStatus(rowMenuTarget.value, 'picked_up') },
]);

function carrierColor(scac) {
    return CARRIER_COLORS[scac] || '#94a3b8';
}

function formatDate(d) {
    return d ? dayjs(d).format('MMM D, YYYY') : '—';
}

function isOverdue(date) {
    return date && dayjs(date).isBefore(dayjs(), 'day');
}

function openRowMenu(event, data) {
    rowMenuTarget.value = data;
    rowMenu.value.toggle(event);
}

function openDetail(data) {
    detailShipment.value = data;
    showDetailDialog.value = true;
}

function onRowClick(event) {
    openDetail(event.data);
}

const debouncedSearch = debounce(() => {
    filters.search = search.value || null;
    load();
}, 400);

function applyFilters() {
    load();
}

async function updateStatus(shipment, status) {
    if (!shipment) return;
    await railStore.updateShipmentStatus(shipment.uuid, status);
}

async function load() {
    await railStore.fetchShipments({
        page:     currentPage.value,
        per_page: pageSize.value,
        order_by: sortField.value,
        direction: sortOrder.value === 1 ? 'asc' : 'desc',
        ...filters,
    });
}

function onPage(event) {
    currentPage.value = event.page + 1;
    pageSize.value = event.rows;
    load();
}

function onSort(event) {
    sortField.value = event.sortField;
    sortOrder.value = event.sortOrder;
    load();
}

async function onShipmentCreated() {
    showCreateDialog.value = false;
    await load();
}

onMounted(load);
</script>
