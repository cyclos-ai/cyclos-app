<template>
    <div>
        <PageHeader title="Detention" subtitle="Track and manage equipment detention charges">
            <template #actions>
                <DataExport @export="() => {}" />
            </template>
        </PageHeader>

        <div class="flex gap-1 mb-4 bg-gray-100 p-1 rounded-lg w-fit">
            <button
                v-for="tab in tabs"
                :key="tab.value"
                class="px-4 py-1.5 rounded-md text-sm font-medium transition-colors"
                :class="activeTab === tab.value ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                @click="activeTab = tab.value"
            >
                {{ tab.label }}
            </button>
        </div>

        <DataTable
            v-if="activeTab === 'charges'"
            :value="demurrageStore.detentionCharges"
            :loading="demurrageStore.loading"
            data-key="uuid"
            striped-rows
            paginator
            :rows="25"
            class="text-sm"
        >
            <Column field="container_number" header="Container" sortable>
                <template #body="{ data }">
                    <router-link
                        :to="{ name: 'container-detail', params: { uuid: data.container_uuid } }"
                        class="font-mono font-semibold text-blue-600 hover:text-blue-800"
                    >
                        {{ data.container_number }}
                    </router-link>
                </template>
            </Column>
            <Column field="carrier_name" header="Carrier" sortable />
            <Column field="gate_out_date" header="Gate Out" sortable>
                <template #body="{ data }">
                    <span class="text-xs">{{ formatDate(data.gate_out_date) }}</span>
                </template>
            </Column>
            <Column field="empty_return_by" header="Return By" sortable>
                <template #body="{ data }">
                    <span :class="returnByClass(data.empty_return_by)" class="text-sm font-medium">
                        {{ formatDate(data.empty_return_by) }}
                    </span>
                </template>
            </Column>
            <Column field="days_accruing" header="Days" sortable>
                <template #body="{ data }">
                    <span :class="data.days_accruing > 0 ? 'text-red-600 font-bold' : 'text-gray-600'">
                        {{ data.days_accruing || 0 }}
                    </span>
                </template>
            </Column>
            <Column field="total_charges" header="Charges" sortable>
                <template #body="{ data }">
                    <span :class="['font-semibold', data.total_charges > 0 ? 'text-red-700' : 'text-green-700']">
                        ${{ formatCurrency(data.total_charges) }}
                    </span>
                </template>
            </Column>
            <template #empty>
                <div class="py-10 text-center text-gray-400">
                    <i class="pi pi-calendar-times text-3xl mb-2 block"></i>
                    <p>No detention charges found</p>
                </div>
            </template>
        </DataTable>

        <div v-else-if="activeTab === 'alarms'" class="text-center py-16 text-gray-400">
            <i class="pi pi-check-circle text-4xl text-green-400 mb-3 block"></i>
            <p class="font-medium text-gray-600">No active detention alarms</p>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import dayjs from 'dayjs';
import PageHeader from '@/components/PageHeader.vue';
import DataExport from '@/components/DataExport.vue';
import { useDemurrageStore } from '@/stores/demurrage';

const props = defineProps({ defaultTab: { type: String, default: 'charges' } });
const demurrageStore = useDemurrageStore();
const activeTab = ref(props.defaultTab);
const tabs = [
    { label: 'Charges', value: 'charges' },
    { label: 'Alarms', value: 'alarms' },
];

function formatDate(d) { return d ? dayjs(d).format('MMM D, YYYY') : '—'; }
function formatCurrency(v) { return v ? Number(v).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '0.00'; }
function returnByClass(d) {
    if (!d) return 'text-gray-400';
    const days = dayjs(d).diff(dayjs(), 'day');
    if (days < 0) return 'text-red-600';
    if (days <= 3) return 'text-orange-600';
    return 'text-gray-700';
}
onMounted(() => demurrageStore.fetchDetention());
</script>
