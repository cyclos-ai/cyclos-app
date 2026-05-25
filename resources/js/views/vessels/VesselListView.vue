<template>
    <div>
        <PageHeader title="Vessels">
            <template #actions>
                <InputText v-model="search" placeholder="Search vessels..." size="small" class="w-64" />
            </template>
        </PageHeader>

        <DataTable
            :value="filteredVessels"
            :loading="vesselsStore.loading"
            data-key="uuid"
            striped-rows
            hover
            paginator
            :rows="25"
            class="text-sm"
        >
            <Column field="name" header="Vessel Name" sortable>
                <template #body="{ data }">
                    <router-link
                        :to="{ name: 'vessel-detail', params: { uuid: data.uuid } }"
                        class="font-medium text-blue-600 hover:text-blue-800"
                    >
                        {{ data.name }}
                    </router-link>
                </template>
            </Column>
            <Column field="imo_number" header="IMO" sortable>
                <template #body="{ data }">
                    <span class="font-mono text-xs">{{ data.imo_number || '—' }}</span>
                </template>
            </Column>
            <Column field="carrier_name" header="Carrier" sortable />
            <Column field="voyage_number" header="Voyage" />
            <Column field="status" header="Status" sortable>
                <template #body="{ data }">
                    <StatusBadge :status="data.status" size="small" />
                </template>
            </Column>
            <Column field="departure_port" header="From" />
            <Column field="arrival_port" header="To" />
            <Column field="eta" header="ETA" sortable>
                <template #body="{ data }">
                    <span class="text-xs">{{ data.eta ? dayjs(data.eta).format('MMM D, YYYY') : '—' }}</span>
                </template>
            </Column>
            <Column field="container_count" header="Containers" sortable>
                <template #body="{ data }">
                    <span class="font-semibold text-blue-600">{{ data.container_count || 0 }}</span>
                </template>
            </Column>
            <Column header="">
                <template #body="{ data }">
                    <router-link :to="{ name: 'vessel-detail', params: { uuid: data.uuid } }">
                        <Button icon="pi pi-eye" text size="small" rounded />
                    </router-link>
                </template>
            </Column>

            <template #empty>
                <div class="py-10 text-center text-gray-400">
                    <i class="pi pi-send text-3xl mb-2 block"></i>
                    <p>No vessels found</p>
                </div>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import dayjs from 'dayjs';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { useVesselsStore } from '@/stores/vessels';

const vesselsStore = useVesselsStore();
const search = ref('');

const filteredVessels = computed(() => {
    if (!search.value) return vesselsStore.vessels;
    const q = search.value.toLowerCase();
    return vesselsStore.vessels.filter(v =>
        v.name?.toLowerCase().includes(q) ||
        v.imo_number?.toLowerCase().includes(q) ||
        v.voyage_number?.toLowerCase().includes(q),
    );
});

onMounted(() => vesselsStore.fetchVessels());
</script>
