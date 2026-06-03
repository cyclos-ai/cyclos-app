<template>
    <div>
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-bold text-gray-900">Inbound Dray Visibility</h1>
                        <i class="pi pi-info-circle text-gray-400 text-sm cursor-pointer" title="All active full drayage moves."></i>
                    </div>
                    <p class="text-sm text-gray-500 mt-0.5">All active full drayage moves.</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <Button
                        label="New Drayage Move"
                        icon="pi pi-plus"
                        size="small"
                        @click="showCreateDialog = true"
                    />
                    <Button
                        :label="selectionMode ? 'Cancel Selection' : 'Actions'"
                        :icon="selectionMode ? 'pi pi-times' : 'pi pi-bolt'"
                        :severity="selectionMode ? 'secondary' : 'success'"
                        size="small"
                        @click="toggleSelectionMode"
                    />
                    <span class="relative">
                        <i class="pi pi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                        <InputText
                            v-model="search"
                            placeholder="Search containers, MBL..."
                            size="small"
                            class="pl-8 w-56"
                            @input="onSearchInput"
                        />
                    </span>
                    <Button icon="pi pi-filter" outlined size="small" severity="secondary" @click="showFilterPanel = !showFilterPanel" />
                    <Button icon="pi pi-sliders-h" outlined size="small" severity="secondary" />
                    <Button icon="pi pi-ellipsis-v" text size="small" rounded severity="secondary" @click="topMenu.toggle($event)" />
                    <Menu ref="topMenu" :model="topMenuItems" :popup="true" />
                </div>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <StatCard
                label="This Week's Remaining Deliveries"
                :value="stats.remaining_deliveries ?? 0"
                icon="pi-truck"
                icon-color="#3b82f6"
                icon-bg="bg-blue-50"
            />
            <StatCard
                label="Awaiting Pickup from Terminal"
                :value="stats.awaiting_pickup ?? 0"
                icon="pi-exclamation-circle"
                icon-color="#ef4444"
                icon-bg="bg-red-50"
            />
            <StatCard
                label="Delivered to DC"
                :value="stats.delivered_to_dc ?? 0"
                icon="pi-check-circle"
                icon-color="#22c55e"
                icon-bg="bg-green-50"
            />
            <StatCard
                label="All Allocations"
                :value="stats.all_allocations ?? 0"
                icon="pi-chart-bar"
                icon-color="#374151"
                icon-bg="bg-gray-100"
            />
            <StatCard
                label="Empty Date Marked"
                :value="stats.empty_date_marked ?? 0"
                icon="pi-check-circle"
                icon-color="#22c55e"
                icon-bg="bg-green-50"
            />
        </div>

        <!-- Bulk actions bar -->
        <Transition name="slide-down">
            <div v-if="selectedRows.length" class="flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-lg px-4 py-2 mb-4">
                <span class="text-sm text-blue-700 font-medium">{{ selectedRows.length }} selected</span>
                <Button label="Assign Carrier" size="small" text @click="bulkAssignCarrier" />
                <Button label="Export Selected" size="small" text @click="exportSelected" />
                <Button label="Deselect All" size="small" text severity="secondary" @click="selectedRows = []" />
            </div>
        </Transition>

        <!-- Toast for copy notification -->
        <Toast position="bottom-right" />

        <!-- Create Drayage Move Dialog -->
        <Dialog v-model:visible="showCreateDialog" header="New Drayage Move" modal class="w-[520px]">
            <form @submit.prevent="submitCreateDrayage" class="space-y-4 pt-2">
                <!-- OCR auto-fill panel -->
                <div class="rounded-lg border border-dashed border-surface-300 bg-surface-50/50 p-3">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 text-sm font-medium text-teal-600"
                        @click="showDrayageOcr = !showDrayageOcr"
                    >
                        <i class="pi pi-file-import"></i>
                        Auto-fill from document (optional)
                        <i :class="showDrayageOcr ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" class="ml-auto text-xs"></i>
                    </button>
                    <div v-if="showDrayageOcr" class="mt-3">
                        <DocumentDropZone @extracted="onDrayageExtracted" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Container Number <span class="text-red-500">*</span></label>
                        <InputText v-model="drayageForm.container_number" placeholder="MSCU1234567" class="w-full" />
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Location</label>
                        <InputText v-model="drayageForm.delivery_location" placeholder="Final destination / DC address" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Scheduled Date</label>
                        <DatePicker v-model="drayageForm.scheduled_drop_date" placeholder="Select date" class="w-full" date-format="mm/dd/yy" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">MBL Number</label>
                        <InputText v-model="drayageForm.mbl_number" placeholder="MAEU123456789" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ocean Carrier SCAC</label>
                        <InputText v-model="drayageForm.ocean_carrier_scac" placeholder="MAEU" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Container Type</label>
                        <InputText v-model="drayageForm.container_type" placeholder="40HC" class="w-full" />
                    </div>
                </div>
                <Message v-if="createDrayageError" severity="error" :closable="false">{{ createDrayageError }}</Message>
            </form>
            <template #footer>
                <Button label="Cancel" text @click="showCreateDialog = false" />
                <Button label="Create Move" icon="pi pi-check" :loading="creatingDrayage" @click="submitCreateDrayage" />
            </template>
        </Dialog>

        <!-- DataTable -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <DataTable
                v-model:selection="selectedRows"
                :value="rows"
                :loading="loading"
                data-key="uuid"
                scrollable
                scroll-height="calc(100vh - 380px)"
                :rows="pageSize"
                :total-records="totalRecords"
                lazy
                paginator
                :rows-per-page-options="[25, 50, 100]"
                paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
                current-page-report-template="Showing {first} to {last} of {totalRecords}"
                size="small"
                class="text-xs inbound-dray-table"
                row-hover
                :row-class="rowClass"
                @page="onPage"
                @sort="onSort"
            >
                <!-- Selection column -->
                <Column v-if="selectionMode" selection-mode="multiple" frozen header-style="width:3rem;min-width:3rem" />

                <!-- Container # -->
                <Column field="container_number" header="Container #" frozen sortable style="min-width:130px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">Container #</span>
                            <InputText v-model="colFilters.container_number" placeholder="Filter..." size="small" class="w-full text-xs" @input="debouncedFetch" />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <span
                            class="font-mono font-semibold text-blue-600 hover:text-blue-800 cursor-pointer select-all"
                            @click="copyToClipboard(data.container_number, 'Container #')"
                            title="Click to copy"
                        >
                            {{ data.container_number || '—' }}
                        </span>
                    </template>
                </Column>

                <!-- MBL -->
                <Column field="mbl_number" header="MBL" sortable style="min-width:130px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">MBL</span>
                            <InputText v-model="colFilters.mbl_number" placeholder="Filter..." size="small" class="w-full text-xs" @input="debouncedFetch" />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <span
                            class="font-mono text-blue-600 hover:text-blue-800 cursor-pointer"
                            @click="copyToClipboard(data.mbl_number, 'MBL')"
                            title="Click to copy"
                        >
                            {{ data.mbl_number || '—' }}
                        </span>
                    </template>
                </Column>

                <!-- Container Status -->
                <Column field="status" header="Status" sortable style="min-width:130px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">Status</span>
                            <Select
                                v-model="colFilters.status"
                                :options="statusOptions"
                                option-label="label"
                                option-value="value"
                                placeholder="All"
                                size="small"
                                class="w-full text-xs"
                                show-clear
                                @change="fetchRows"
                            />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <StatusBadge :status="data.status" size="small" />
                    </template>
                </Column>

                <!-- Notes -->
                <Column field="notes" header="Notes" style="min-width:140px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">Notes</span>
                            <InputText v-model="colFilters.notes" placeholder="Filter..." size="small" class="w-full text-xs" @input="debouncedFetch" />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <span class="text-gray-600 text-xs truncate max-w-[120px] block" :title="data.notes">{{ data.notes || '—' }}</span>
                    </template>
                </Column>

                <!-- Dray Notes (editable) -->
                <Column field="dray_notes" header="Dray Notes" style="min-width:160px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">Dray Notes</span>
                            <div class="w-full h-[26px]"></div>
                        </div>
                    </template>
                    <template #body="{ data }">
                        <EditableCell
                            :uuid="data.uuid"
                            field="dray_notes"
                            :value="data.dray_notes"
                            type="text"
                            placeholder="Add note..."
                            @save="onInlineEdit"
                        />
                    </template>
                </Column>

                <!-- Ocean Carrier SCAC -->
                <Column field="ocean_carrier_scac" header="Ocean Carrier SCAC" sortable style="min-width:150px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">Ocean Carrier SCAC</span>
                            <InputText v-model="colFilters.ocean_carrier_scac" placeholder="Filter..." size="small" class="w-full text-xs" @input="debouncedFetch" />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <span class="text-gray-700 font-mono text-xs">{{ data.ocean_carrier_scac || '—' }}</span>
                    </template>
                </Column>

                <!-- Container Type -->
                <Column field="container_type" header="Container Type" sortable style="min-width:130px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">Container Type</span>
                            <InputText v-model="colFilters.container_type" placeholder="Filter..." size="small" class="w-full text-xs" @input="debouncedFetch" />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <span class="text-xs text-gray-700">{{ data.container_type || '—' }}</span>
                    </template>
                </Column>

                <!-- Mother Vessel -->
                <Column field="vessel_name" header="Mother Vessel" sortable style="min-width:150px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">Mother Vessel</span>
                            <InputText v-model="colFilters.vessel_name" placeholder="Filter..." size="small" class="w-full text-xs" @input="debouncedFetch" />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <span class="text-xs text-gray-700">{{ data.vessel_name || '—' }}</span>
                    </template>
                </Column>

                <!-- Voyage -->
                <Column field="voyage" header="Voyage" sortable style="min-width:100px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">Voyage</span>
                            <InputText v-model="colFilters.voyage" placeholder="Filter..." size="small" class="w-full text-xs" @input="debouncedFetch" />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <span class="text-xs text-gray-700">{{ data.voyage || '—' }}</span>
                    </template>
                </Column>

                <!-- Vessel ETA -->
                <Column field="vessel_eta" header="Vessel ETA" sortable style="min-width:140px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">Vessel ETA</span>
                            <DatePicker v-model="colFilters.vessel_eta" placeholder="Filter..." size="small" class="w-full text-xs" date-format="mm/dd/yy" @update:model-value="fetchRows" />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <span class="text-xs text-gray-700">{{ formatDate(data.vessel_eta) }}</span>
                    </template>
                </Column>

                <!-- Available Date -->
                <Column field="available_date" header="Available Date" sortable style="min-width:140px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">Available Date</span>
                            <DatePicker v-model="colFilters.available_date" placeholder="Filter..." size="small" class="w-full text-xs" date-format="mm/dd/yy" @update:model-value="fetchRows" />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <span class="text-xs" :class="isOverdue(data.available_date) ? 'text-red-600 font-medium' : 'text-gray-700'">
                            {{ formatDate(data.available_date) }}
                        </span>
                    </template>
                </Column>

                <!-- Est. Dem. LFD -->
                <Column field="est_dem_lfd" header="Est. Dem. LFD" sortable style="min-width:130px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">Est. Dem. LFD</span>
                            <DatePicker v-model="colFilters.est_dem_lfd" placeholder="Filter..." size="small" class="w-full text-xs" date-format="mm/dd/yy" @update:model-value="fetchRows" />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <span class="text-xs font-medium" :class="lfdUrgencyClass(data.est_dem_lfd)">
                            {{ formatDate(data.est_dem_lfd) }}
                        </span>
                    </template>
                </Column>

                <!-- Scheduled Drop Date (editable, green highlight) -->
                <Column field="scheduled_drop_date" header="Scheduled Drop Date" sortable style="min-width:160px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">Scheduled Drop Date</span>
                            <DatePicker v-model="colFilters.scheduled_drop_date" placeholder="Filter..." size="small" class="w-full text-xs" date-format="mm/dd/yy" @update:model-value="fetchRows" />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <EditableCell
                            :uuid="data.uuid"
                            field="scheduled_drop_date"
                            :value="data.scheduled_drop_date"
                            type="date"
                            cell-class="bg-green-50 text-green-800"
                            @save="onInlineEdit"
                        />
                    </template>
                </Column>

                <!-- Requested Stack -->
                <Column field="requested_stack" header="Requested Stack" sortable style="min-width:140px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">Requested Stack</span>
                            <InputText v-model="colFilters.requested_stack" placeholder="Filter..." size="small" class="w-full text-xs" @input="debouncedFetch" />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <span
                            v-if="data.requested_stack"
                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                            :class="stackTagClass(data.requested_stack)"
                        >
                            {{ data.requested_stack }}
                        </span>
                        <span v-else class="text-gray-400 text-xs">—</span>
                    </template>
                </Column>

                <!-- Pickup from Terminal (editable) -->
                <Column field="pickup_from_terminal" header="Pickup from Terminal" sortable style="min-width:160px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">Pickup from Terminal</span>
                            <DatePicker v-model="colFilters.pickup_from_terminal" placeholder="Filter..." size="small" class="w-full text-xs" date-format="mm/dd/yy" @update:model-value="fetchRows" />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <EditableCell
                            :uuid="data.uuid"
                            field="pickup_from_terminal"
                            :value="data.pickup_from_terminal"
                            type="date"
                            @save="onInlineEdit"
                        />
                    </template>
                </Column>

                <!-- DO Sent Date -->
                <Column field="do_sent_date" header="DO Sent Date" sortable style="min-width:130px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">DO Sent Date</span>
                            <DatePicker v-model="colFilters.do_sent_date" placeholder="Filter..." size="small" class="w-full text-xs" date-format="mm/dd/yy" @update:model-value="fetchRows" />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <span class="text-xs text-gray-700">{{ formatDate(data.do_sent_date) }}</span>
                    </template>
                </Column>

                <!-- Delivery Date (editable with time) -->
                <Column field="delivery_date" header="Delivery Date" sortable style="min-width:160px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">Delivery Date</span>
                            <DatePicker v-model="colFilters.delivery_date" placeholder="Filter..." size="small" class="w-full text-xs" date-format="mm/dd/yy" @update:model-value="fetchRows" />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <EditableCell
                            :uuid="data.uuid"
                            field="delivery_date"
                            :value="data.delivery_date"
                            type="datetime"
                            @save="onInlineEdit"
                        />
                    </template>
                </Column>

                <!-- Dray Carrier -->
                <Column field="dray_carrier" header="Dray Carrier" sortable style="min-width:140px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">Dray Carrier</span>
                            <InputText v-model="colFilters.dray_carrier" placeholder="Filter..." size="small" class="w-full text-xs" @input="debouncedFetch" />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <span class="text-xs text-gray-700">{{ data.dray_carrier || '—' }}</span>
                    </template>
                </Column>

                <!-- Tender Status (editable) -->
                <Column field="tender_status" header="Tender Status" sortable style="min-width:140px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">Tender Status</span>
                            <InputText v-model="colFilters.tender_status" placeholder="Filter..." size="small" class="w-full text-xs" @input="debouncedFetch" />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <EditableCell
                            :uuid="data.uuid"
                            field="tender_status"
                            :value="data.tender_status"
                            type="text"
                            placeholder="Set status..."
                            @save="onInlineEdit"
                        />
                    </template>
                </Column>

                <!-- Dray Carrier SCAC (purple badge) -->
                <Column field="dray_carrier_scac" header="Dray Carrier SCAC" sortable style="min-width:150px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">Dray Carrier SCAC</span>
                            <InputText v-model="colFilters.dray_carrier_scac" placeholder="Filter..." size="small" class="w-full text-xs" @input="debouncedFetch" />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <span
                            v-if="data.dray_carrier_scac"
                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-purple-100 text-purple-700"
                        >
                            {{ data.dray_carrier_scac }}
                        </span>
                        <span v-else class="text-gray-400 text-xs">—</span>
                    </template>
                </Column>

                <!-- Empty Date (editable) -->
                <Column field="empty_date" header="Empty Date" sortable style="min-width:140px">
                    <template #header>
                        <div class="flex flex-col gap-1 w-full">
                            <span class="font-semibold text-xs">Empty Date</span>
                            <DatePicker v-model="colFilters.empty_date" placeholder="Filter..." size="small" class="w-full text-xs" date-format="mm/dd/yy" @update:model-value="fetchRows" />
                        </div>
                    </template>
                    <template #body="{ data }">
                        <EditableCell
                            :uuid="data.uuid"
                            field="empty_date"
                            :value="data.empty_date"
                            type="date"
                            @save="onInlineEdit"
                        />
                    </template>
                </Column>

                <template #empty>
                    <div class="py-16 text-center text-gray-400">
                        <i class="pi pi-truck text-4xl mb-3 block"></i>
                        <p class="text-sm font-medium">No drayage moves found</p>
                        <p class="text-xs mt-1">Adjust filters or check back later</p>
                    </div>
                </template>
            </DataTable>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, defineComponent } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import Message from 'primevue/message';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Menu from 'primevue/menu';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { debounce } from 'lodash-es';
import dayjs from 'dayjs';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import DocumentDropZone from '@/components/documents/DocumentDropZone.vue';
import { useApi } from '@/composables/useApi';
import api from '@/plugins/api';

// ─── Inline-editable cell sub-component ────────────────────────────────────
const EditableCell = defineComponent({
    name: 'EditableCell',
    components: { InputText, DatePicker },
    props: {
        uuid:      { type: String, required: true },
        field:     { type: String, required: true },
        value:     { type: [String, Number, null], default: null },
        type:      { type: String, default: 'text' }, // text | date | datetime
        placeholder: { type: String, default: '—' },
        cellClass: { type: String, default: '' },
    },
    emits: ['save'],
    setup(props, { emit }) {
        const editing   = ref(false);
        const localVal  = ref(props.value);

        function startEdit() {
            localVal.value = props.value;
            editing.value  = true;
        }

        function commit() {
            if (localVal.value !== props.value) {
                emit('save', { uuid: props.uuid, field: props.field, value: localVal.value });
            }
            editing.value = false;
        }

        function onKeydown(e) {
            if (e.key === 'Enter') commit();
            if (e.key === 'Escape') { editing.value = false; }
        }

        const displayValue = computed(() => {
            if (!props.value) return null;
            if (props.type === 'date')     return dayjs(props.value).format('MM/DD/YY');
            if (props.type === 'datetime') return dayjs(props.value).format('MM/DD/YY HH:mm');
            return props.value;
        });

        return { editing, localVal, startEdit, commit, onKeydown, displayValue };
    },
    template: `
        <div :class="cellClass" class="min-h-[22px] rounded px-1 py-0.5 -mx-1">
            <template v-if="editing">
                <InputText
                    v-if="type === 'text'"
                    v-model="localVal"
                    size="small"
                    class="w-full text-xs h-6"
                    autofocus
                    @blur="commit"
                    @keydown="onKeydown"
                />
                <DatePicker
                    v-else
                    v-model="localVal"
                    :show-time="type === 'datetime'"
                    size="small"
                    class="w-full text-xs"
                    date-format="mm/dd/yy"
                    autofocus
                    @blur="commit"
                    @keydown="onKeydown"
                    @date-select="commit"
                />
            </template>
            <span
                v-else
                class="cursor-pointer text-xs hover:underline hover:text-blue-700"
                :class="displayValue ? 'text-gray-800' : 'text-gray-400 italic'"
                @click="startEdit"
            >
                {{ displayValue || placeholder }}
            </span>
        </div>
    `,
});

// ─── Main component logic ────────────────────────────────────────────────────
const toast         = useToast();
const { execute }   = useApi();

// Create drayage move dialog
const showCreateDialog  = ref(false);
const showDrayageOcr    = ref(false);
const creatingDrayage   = ref(false);
const createDrayageError = ref('');
const drayageForm = reactive({
    container_number:   '',
    delivery_location:  '',
    scheduled_drop_date: null,
    mbl_number:         '',
    ocean_carrier_scac: '',
    container_type:     '',
});

function onDrayageExtracted(data) {
    if (data.container_numbers?.length) drayageForm.container_number = data.container_numbers[0];
    if (data.final_destination) drayageForm.delivery_location = data.final_destination;
    if (data.eta) drayageForm.scheduled_drop_date = new Date(data.eta);
    if (data.mbl_number) drayageForm.mbl_number = data.mbl_number;
    if (data.carrier_scac) drayageForm.ocean_carrier_scac = data.carrier_scac;
    if (data.container_type) drayageForm.container_type = data.container_type;
    showDrayageOcr.value = false;
    createDrayageError.value = '';
}

async function submitCreateDrayage() {
    if (!drayageForm.container_number.trim()) {
        createDrayageError.value = 'Container number is required.';
        return;
    }
    creatingDrayage.value = true;
    createDrayageError.value = '';
    try {
        const payload = { ...drayageForm };
        if (payload.scheduled_drop_date) {
            payload.scheduled_drop_date = dayjs(payload.scheduled_drop_date).format('YYYY-MM-DD');
        }
        await api.post('/drayage', payload);
        showCreateDialog.value = false;
        Object.assign(drayageForm, {
            container_number: '', delivery_location: '', scheduled_drop_date: null,
            mbl_number: '', ocean_carrier_scac: '', container_type: '',
        });
        showDrayageOcr.value = false;
        toast.add({ severity: 'success', summary: 'Created', detail: 'Drayage move created', life: 3000 });
        await fetchRows();
    } catch (err) {
        createDrayageError.value = err.response?.data?.message || 'Failed to create drayage move.';
    } finally {
        creatingDrayage.value = false;
    }
}

const topMenu        = ref(null);
const loading        = ref(false);
const rows           = ref([]);
const totalRecords   = ref(0);
const selectedRows   = ref([]);
const selectionMode  = ref(false);
const search         = ref('');
const currentPage    = ref(1);
const pageSize       = ref(50);
const sortField      = ref(null);
const sortOrder      = ref(null);
const showFilterPanel = ref(false);

const stats = reactive({
    remaining_deliveries: null,
    awaiting_pickup:      null,
    delivered_to_dc:      null,
    all_allocations:      null,
    empty_date_marked:    null,
});

const colFilters = reactive({
    container_number:    '',
    mbl_number:          '',
    status:              null,
    notes:               '',
    ocean_carrier_scac:  '',
    container_type:      '',
    vessel_name:         '',
    voyage:              '',
    vessel_eta:          null,
    available_date:      null,
    est_dem_lfd:         null,
    scheduled_drop_date: null,
    requested_stack:     '',
    pickup_from_terminal:null,
    do_sent_date:        null,
    delivery_date:       null,
    dray_carrier:        '',
    tender_status:       '',
    dray_carrier_scac:   '',
    empty_date:          null,
});

const statusOptions = [
    { label: 'In Transit',    value: 'in_transit' },
    { label: 'On Vessel',     value: 'on_vessel' },
    { label: 'At Terminal',   value: 'at_terminal' },
    { label: 'Delivered',     value: 'delivered' },
    { label: 'Empty Return',  value: 'empty_return' },
    { label: 'Customs Hold',  value: 'customs_hold' },
];

const topMenuItems = [
    { label: 'Export CSV',   icon: 'pi pi-file', command: () => {} },
    { label: 'Export Excel', icon: 'pi pi-file-excel', command: () => {} },
    { separator: true },
    { label: 'Manage Columns', icon: 'pi pi-table', command: () => {} },
];

// Determine if a container's scheduled drop date is this week
function isThisWeek(dateStr) {
    if (!dateStr) return false;
    const d     = dayjs(dateStr);
    const start = dayjs().startOf('week');
    const end   = dayjs().endOf('week');
    return d.isAfter(start) && d.isBefore(end);
}

function rowClass(data) {
    return isThisWeek(data.scheduled_drop_date) ? 'bg-yellow-50' : '';
}

function stackTagClass(stack) {
    if (!stack) return '';
    const map = {
        A: 'bg-blue-100 text-blue-700',
        B: 'bg-green-100 text-green-700',
        C: 'bg-orange-100 text-orange-700',
        D: 'bg-red-100 text-red-700',
    };
    return map[stack.charAt(0).toUpperCase()] || 'bg-gray-100 text-gray-700';
}

function lfdUrgencyClass(lfd) {
    if (!lfd) return 'text-gray-400';
    const days = dayjs(lfd).diff(dayjs(), 'day');
    if (days < 0)  return 'text-red-600';
    if (days <= 2) return 'text-orange-600';
    if (days <= 5) return 'text-yellow-600';
    return 'text-gray-700';
}

function formatDate(date) {
    return date ? dayjs(date).format('MM/DD/YY') : '—';
}

function isOverdue(date) {
    return date && dayjs(date).isBefore(dayjs(), 'day');
}

function toggleSelectionMode() {
    selectionMode.value = !selectionMode.value;
    if (!selectionMode.value) selectedRows.value = [];
}

async function copyToClipboard(text, label) {
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        toast.add({ severity: 'success', summary: 'Copied to clipboard', detail: `${label}: ${text}`, life: 2000 });
    } catch {
        toast.add({ severity: 'error', summary: 'Copy failed', detail: 'Could not access clipboard', life: 2000 });
    }
}

function buildParams() {
    const params = {
        page_num:  currentPage.value,
        page_size: pageSize.value,
    };

    if (search.value)      params.q = search.value;
    if (sortField.value)   params.sort = sortField.value;
    if (sortOrder.value)   params.direction = sortOrder.value === 1 ? 'asc' : 'desc';

    // Column filters
    Object.entries(colFilters).forEach(([key, val]) => {
        if (val === null || val === '' || val === undefined) return;
        if (val instanceof Date) {
            params[key] = dayjs(val).format('YYYY-MM-DD');
        } else {
            params[key] = val;
        }
    });

    return params;
}

async function fetchRows() {
    loading.value = true;
    try {
        const data = await execute('get', '/drayage/inbound', buildParams());
        rows.value         = data?.data ?? data ?? [];
        totalRecords.value = data?.meta?.total ?? rows.value.length;
    } catch {
        rows.value = [];
    } finally {
        loading.value = false;
    }
}

async function loadStats() {
    try {
        const data = await execute('get', '/drayage/overview-stats');
        Object.assign(stats, data?.data ?? data ?? {});
    } catch {
        // keep zeros
    }
}

const debouncedFetch = debounce(fetchRows, 350);

function onSearchInput() {
    currentPage.value = 1;
    debouncedFetch();
}

function onPage(event) {
    currentPage.value = event.page + 1;
    pageSize.value    = event.rows;
    fetchRows();
}

function onSort(event) {
    sortField.value = event.sortField;
    sortOrder.value = event.sortOrder;
    fetchRows();
}

async function onInlineEdit({ uuid, field, value }) {
    try {
        await api.patch(`/drayage/${uuid}`, { [field]: value });
        // Update local row
        const row = rows.value.find(r => r.uuid === uuid);
        if (row) row[field] = value;
        toast.add({ severity: 'success', summary: 'Saved', detail: `${field.replace(/_/g, ' ')} updated`, life: 1800 });
    } catch {
        toast.add({ severity: 'error', summary: 'Save failed', detail: 'Could not save changes', life: 2500 });
    }
}

function bulkAssignCarrier() {
    // future: open assign-carrier dialog
}

function exportSelected() {
    // future: export selected UUIDs
}

onMounted(async () => {
    await Promise.allSettled([fetchRows(), loadStats()]);
});
</script>

<style scoped>
/* Ensure header filter inputs don't overflow */
:deep(.p-datatable-thead th) {
    vertical-align: top;
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
}

:deep(.p-datatable-thead th .p-column-header-content) {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
    width: 100%;
}

:deep(.p-datatable-tbody td) {
    padding-top: 0.375rem;
    padding-bottom: 0.375rem;
}

/* Row highlight for this-week scheduled drops */
:deep(.bg-yellow-50 td) {
    background-color: #fefce8 !important;
}

/* slide-down transition for bulk action bar */
.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.2s ease;
}
.slide-down-enter-from,
.slide-down-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
