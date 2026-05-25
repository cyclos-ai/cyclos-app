<template>
    <div>
        <PageHeader title="SKUs">
            <template #actions>
                <Button label="Add SKU" icon="pi pi-plus" size="small" @click="showCreateDialog = true" />
                <DataExport @export="() => {}" />
            </template>
        </PageHeader>
        <div class="mb-4 flex flex-wrap gap-3">
            <InputText v-model="search" placeholder="Search SKUs..." class="w-64" @input="onSearch" />
            <Select v-model="filterCategory" :options="categories" option-label="label" option-value="value" placeholder="All Categories" show-clear class="w-48" />
        </div>
        <DataTable :value="items" :loading="loading" data-key="uuid" striped-rows paginator :rows="25" class="text-sm">
            <Column field="sku_code" header="SKU Code" sortable>
                <template #body="{ data }">
                    <router-link :to="{ name: 'sku-detail', params: { uuid: data.uuid } }" class="font-mono font-semibold text-blue-600 hover:text-blue-800">{{ data.sku_code }}</router-link>
                </template>
            </Column>
            <Column field="description" header="Description" sortable />
            <Column field="category" header="Category">
                <template #body="{ data }"><Tag :value="data.category" severity="secondary" /></template>
            </Column>
            <Column field="unit_of_measure" header="UOM" />
            <Column field="weight_kg" header="Weight (kg)">
                <template #body="{ data }"><span class="text-sm">{{ data.weight_kg ? data.weight_kg.toLocaleString() : '—' }}</span></template>
            </Column>
            <Column field="volume_cbm" header="Volume (CBM)">
                <template #body="{ data }"><span class="text-sm">{{ data.volume_cbm ? data.volume_cbm.toFixed(3) : '—' }}</span></template>
            </Column>
            <Column field="active" header="Status">
                <template #body="{ data }"><Tag :value="data.active ? 'Active' : 'Inactive'" :severity="data.active ? 'success' : 'secondary'" /></template>
            </Column>
            <template #empty>
                <div class="py-10 text-center text-gray-400">
                    <i class="pi pi-box text-3xl mb-2 block"></i>
                    <p>No SKUs found</p>
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
const filterCategory = ref(null);
const showCreateDialog = ref(false);

const categories = [
    { label: 'Raw Material', value: 'raw_material' },
    { label: 'Finished Good', value: 'finished_good' },
    { label: 'Packaging', value: 'packaging' },
    { label: 'Component', value: 'component' },
];

async function fetchItems() {
    loading.value = true;
    try {
        const params = {};
        if (search.value) params.search = search.value;
        if (filterCategory.value) params.category = filterCategory.value;
        const r = await api.get('/skus', { params });
        items.value = r.data.data || r.data;
    } catch { items.value = []; }
    finally { loading.value = false; }
}

const onSearch = debounce(fetchItems, 300);

onMounted(fetchItems);
</script>
