<template>
    <div>
        <PageHeader title="Distribution Centers">
            <template #actions>
                <Button label="Add DC" icon="pi pi-plus" size="small" @click="showCreateDialog = true" />
                <DataExport @export="() => {}" />
            </template>
        </PageHeader>
        <div class="mb-4 flex flex-wrap gap-3">
            <InputText v-model="search" placeholder="Search distribution centers..." class="w-72" @input="onSearch" />
            <Select v-model="filterCountry" :options="countryOptions" option-label="label" option-value="value" placeholder="All Countries" show-clear class="w-48" />
        </div>
        <DataTable :value="items" :loading="loading" data-key="uuid" striped-rows paginator :rows="25" class="text-sm">
            <Column field="name" header="DC Name" sortable>
                <template #body="{ data }">
                    <router-link :to="{ name: 'dc-detail', params: { uuid: data.uuid } }" class="font-semibold text-blue-600 hover:text-blue-800">{{ data.name }}</router-link>
                </template>
            </Column>
            <Column field="code" header="Code">
                <template #body="{ data }"><span class="font-mono text-xs">{{ data.code || '—' }}</span></template>
            </Column>
            <Column field="dc_type" header="Type">
                <template #body="{ data }"><Tag :value="data.dc_type || '—'" severity="secondary" /></template>
            </Column>
            <Column field="country" header="Country" sortable />
            <Column field="city" header="City" />
            <Column field="capacity_sqft" header="Capacity (sqft)">
                <template #body="{ data }"><span class="text-sm">{{ data.capacity_sqft ? data.capacity_sqft.toLocaleString() : '—' }}</span></template>
            </Column>
            <Column field="active_shipment_count" header="Active Shipments">
                <template #body="{ data }"><span class="font-semibold text-blue-600">{{ data.active_shipment_count || 0 }}</span></template>
            </Column>
            <Column field="active" header="Status">
                <template #body="{ data }"><Tag :value="data.active ? 'Active' : 'Inactive'" :severity="data.active ? 'success' : 'secondary'" /></template>
            </Column>
            <template #empty>
                <div class="py-10 text-center text-gray-400">
                    <i class="pi pi-warehouse text-3xl mb-2 block"></i>
                    <p>No distribution centers found</p>
                </div>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { debounce } from 'lodash-es';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import PageHeader from '@/components/PageHeader.vue';
import DataExport from '@/components/DataExport.vue';
import api from '@/plugins/api';

const items = ref([]);
const loading = ref(false);
const search = ref('');
const filterCountry = ref(null);
const showCreateDialog = ref(false);

const countryOptions = [
    { label: 'United States', value: 'US' },
    { label: 'Canada', value: 'CA' },
    { label: 'Mexico', value: 'MX' },
    { label: 'United Kingdom', value: 'GB' },
    { label: 'Germany', value: 'DE' },
    { label: 'Netherlands', value: 'NL' },
];

async function fetchItems() {
    loading.value = true;
    try {
        const params = {};
        if (search.value) params.search = search.value;
        if (filterCountry.value) params.country = filterCountry.value;
        const r = await api.get('/distribution-centers', { params });
        items.value = r.data.data || r.data;
    } catch { items.value = []; }
    finally { loading.value = false; }
}

const onSearch = debounce(fetchItems, 300);

onMounted(fetchItems);
</script>
