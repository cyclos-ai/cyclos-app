<template>
    <div>
        <PageHeader title="Demurrage" subtitle="Track and manage demurrage charges">
            <template #actions>
                <Button label="Calculator" icon="pi pi-calculator" outlined size="small" @click="showCalc = true" />
                <DataExport @export="() => {}" />
            </template>
        </PageHeader>

        <!-- Tab bar -->
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

        <!-- Charges table -->
        <DataTable
            v-if="activeTab === 'charges'"
            :value="demurrageStore.demurrageCharges"
            :loading="demurrageStore.loading"
            data-key="uuid"
            striped-rows
            paginator
            :rows="25"
            class="text-sm"
            row-class="urgencyRow"
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
            <Column field="pod" header="POD" sortable />
            <Column field="last_free_day" header="LFD" sortable>
                <template #body="{ data }">
                    <span :class="lfdClass(data.last_free_day)" class="text-sm font-medium">
                        {{ formatDate(data.last_free_day) }}
                    </span>
                </template>
            </Column>
            <Column field="days_accruing" header="Days Accruing" sortable>
                <template #body="{ data }">
                    <span :class="data.days_accruing > 0 ? 'text-red-600 font-bold' : 'text-gray-600'">
                        {{ data.days_accruing || 0 }}
                    </span>
                </template>
            </Column>
            <Column field="daily_rate" header="Daily Rate" sortable>
                <template #body="{ data }">
                    <span class="text-sm">${{ formatCurrency(data.daily_rate) }}</span>
                </template>
            </Column>
            <Column field="total_charges" header="Total Charges" sortable>
                <template #body="{ data }">
                    <span :class="['font-semibold text-sm', data.total_charges > 0 ? 'text-red-700' : 'text-green-700']">
                        ${{ formatCurrency(data.total_charges) }}
                    </span>
                </template>
            </Column>
            <Column field="urgency" header="Urgency" sortable>
                <template #body="{ data }">
                    <div class="flex items-center gap-1">
                        <span
                            class="w-2.5 h-2.5 rounded-full"
                            :class="urgencyDot(data.last_free_day, data.days_accruing)"
                        ></span>
                        <span class="text-xs capitalize">{{ urgencyLabel(data.last_free_day, data.days_accruing) }}</span>
                    </div>
                </template>
            </Column>
            <template #empty>
                <div class="py-10 text-center text-gray-400">
                    <i class="pi pi-clock text-3xl mb-2 block"></i>
                    <p>No demurrage charges found</p>
                </div>
            </template>
        </DataTable>

        <!-- Alarms tab -->
        <div v-else-if="activeTab === 'alarms'">
            <div v-if="demurrageStore.alarms.length" class="space-y-3">
                <div
                    v-for="alarm in demurrageStore.alarms"
                    :key="alarm.uuid"
                    class="bg-white border rounded-xl p-4 flex items-start gap-4"
                    :class="alarm.severity === 'critical' ? 'border-red-300 bg-red-50' : 'border-yellow-300 bg-yellow-50'"
                >
                    <i
                        :class="['pi text-xl flex-shrink-0 mt-0.5', alarm.severity === 'critical' ? 'pi-exclamation-circle text-red-500' : 'pi-exclamation-triangle text-yellow-500']"
                    ></i>
                    <div class="flex-1">
                        <p class="font-semibold text-gray-900">{{ alarm.title }}</p>
                        <p class="text-sm text-gray-600 mt-0.5">{{ alarm.message }}</p>
                        <p class="text-xs text-gray-400 mt-1">Container: {{ alarm.container_number }} &middot; {{ formatDate(alarm.triggered_at) }}</p>
                    </div>
                    <Button
                        label="Acknowledge"
                        size="small"
                        outlined
                        @click="demurrageStore.acknowledgeAlarm(alarm.uuid)"
                    />
                </div>
            </div>
            <div v-else class="text-center py-16 text-gray-400">
                <i class="pi pi-check-circle text-4xl text-green-400 mb-3 block"></i>
                <p class="font-medium text-gray-600">No active alarms</p>
                <p class="text-sm mt-1">All containers are within free time</p>
            </div>
        </div>

        <!-- Calculator Dialog -->
        <Dialog v-model:visible="showCalc" header="Demurrage Calculator" modal class="w-[500px]">
            <div class="space-y-4 pt-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Container</label>
                    <InputText v-model="calc.container_number" placeholder="Container number" class="w-full" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Arrival Date</label>
                        <DatePicker v-model="calc.arrival_date" date-format="yy-mm-dd" show-icon class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Return Date</label>
                        <DatePicker v-model="calc.return_date" date-format="yy-mm-dd" show-icon class="w-full" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Free Days</label>
                        <InputNumber v-model="calc.free_days" :min="0" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Daily Rate ($)</label>
                        <InputNumber v-model="calc.daily_rate" :min="0" mode="currency" currency="USD" class="w-full" />
                    </div>
                </div>

                <!-- Result -->
                <div v-if="calcResult" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-500">Total Days</span><p class="font-bold text-lg">{{ calcResult.total_days }}</p></div>
                        <div><span class="text-gray-500">Billable Days</span><p class="font-bold text-lg text-red-600">{{ calcResult.billable_days }}</p></div>
                        <div class="col-span-2 border-t border-blue-200 pt-3">
                            <span class="text-gray-500">Estimated Charges</span>
                            <p class="font-bold text-2xl text-blue-700">${{ formatCurrency(calcResult.total_charges) }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" text @click="showCalc = false" />
                <Button label="Calculate" icon="pi pi-calculator" :loading="calculating" @click="calculate" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import DatePicker from 'primevue/datepicker';
import dayjs from 'dayjs';
import PageHeader from '@/components/PageHeader.vue';
import DataExport from '@/components/DataExport.vue';
import { useDemurrageStore } from '@/stores/demurrage';

const props = defineProps({
    defaultTab: { type: String, default: 'charges' },
});

const demurrageStore = useDemurrageStore();
const activeTab = ref(props.defaultTab);
const showCalc = ref(false);
const calculating = ref(false);
const calcResult = ref(null);

const tabs = [
    { label: 'Charges', value: 'charges' },
    { label: 'Alarms', value: 'alarms' },
];

const calc = reactive({
    container_number: '',
    arrival_date: null,
    return_date: null,
    free_days: 5,
    daily_rate: 150,
});

function formatDate(d) { return d ? dayjs(d).format('MMM D, YYYY') : '—'; }
function formatCurrency(v) { return v ? Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0.00'; }

function lfdClass(lfd) {
    if (!lfd) return 'text-gray-400';
    const days = dayjs(lfd).diff(dayjs(), 'day');
    if (days < 0) return 'text-red-600';
    if (days <= 2) return 'text-orange-500';
    if (days <= 5) return 'text-yellow-600';
    return 'text-green-600';
}

function urgencyDot(lfd, accruing) {
    if (accruing > 0) return 'bg-red-500';
    if (!lfd) return 'bg-gray-300';
    const days = dayjs(lfd).diff(dayjs(), 'day');
    if (days <= 2) return 'bg-orange-500';
    if (days <= 5) return 'bg-yellow-400';
    return 'bg-green-500';
}

function urgencyLabel(lfd, accruing) {
    if (accruing > 0) return 'accruing';
    if (!lfd) return 'unknown';
    const days = dayjs(lfd).diff(dayjs(), 'day');
    if (days <= 2) return 'urgent';
    if (days <= 5) return 'approaching';
    return 'ok';
}

async function calculate() {
    calculating.value = true;
    try {
        const arrival = calc.arrival_date ? dayjs(calc.arrival_date).format('YYYY-MM-DD') : null;
        const returnDate = calc.return_date ? dayjs(calc.return_date).format('YYYY-MM-DD') : dayjs().format('YYYY-MM-DD');
        const totalDays = dayjs(returnDate).diff(dayjs(arrival), 'day');
        const billable = Math.max(0, totalDays - calc.free_days);
        calcResult.value = {
            total_days: totalDays,
            billable_days: billable,
            total_charges: billable * calc.daily_rate,
        };
    } finally {
        calculating.value = false;
    }
}

onMounted(() => {
    demurrageStore.fetchDemurrage();
    demurrageStore.fetchAlarms();
});
</script>
