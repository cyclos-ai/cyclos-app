<template>
    <div class="relative">
        <span class="p-input-icon-left w-full">
            <i class="pi pi-search text-surface-400"></i>
            <InputText
                v-model="query"
                placeholder="Search containers, MBLs, vessels..."
                class="w-full pl-8 bg-surface-50 border-surface-200 text-sm"
                @input="onInput"
                @keyup.enter="onSearch"
                @keyup.escape="clear"
            />
        </span>
        <button
            v-if="query"
            class="absolute right-2 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600"
            @click="clear"
        >
            <i class="pi pi-times text-xs"></i>
        </button>

        <!-- Quick results dropdown -->
        <div
            v-if="showResults && results.length"
            class="absolute top-full mt-1 left-0 right-0 bg-white border border-surface-200 rounded-lg shadow-xl z-50 max-h-64 overflow-y-auto"
        >
            <div
                v-for="result in results"
                :key="result.uuid"
                class="flex items-center gap-3 px-4 py-2.5 hover:bg-surface-50 cursor-pointer"
                @click="selectResult(result)"
            >
                <i :class="`pi ${resultIcon(result.type)} text-surface-400 text-sm`"></i>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-surface-900 truncate">{{ result.label }}</p>
                    <p class="text-xs text-surface-500">{{ result.sublabel }}</p>
                </div>
                <span class="ml-auto text-xs text-surface-400 capitalize">{{ result.type }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import InputText from 'primevue/inputtext';
import { debounce } from 'lodash-es';
import api from '@/plugins/api';

const router = useRouter();
const query = ref('');
const results = ref([]);
const showResults = ref(false);

const typeRouteMap = {
    container: 'container-detail',
    vessel:    'vessel-detail',
    mbl:       'mbl-detail',
    booking:   'booking-detail',
};

function resultIcon(type) {
    return { container: 'pi-box', vessel: 'pi-send', mbl: 'pi-file', booking: 'pi-bookmark' }[type] || 'pi-search';
}

const debouncedSearch = debounce(async (q) => {
    if (q.length < 2) {
        results.value = [];
        showResults.value = false;
        return;
    }
    try {
        const response = await api.get('/search', { params: { q } });
        results.value = response.data;
        showResults.value = true;
    } catch {
        results.value = [];
    }
}, 300);

function onInput() {
    debouncedSearch(query.value);
}

function onSearch() {
    if (query.value.trim()) {
        router.push({ name: 'containers', query: { search: query.value } });
        showResults.value = false;
    }
}

function selectResult(result) {
    const routeName = typeRouteMap[result.type];
    if (routeName) {
        router.push({ name: routeName, params: { uuid: result.uuid } });
    }
    showResults.value = false;
    query.value = '';
}

function clear() {
    query.value = '';
    results.value = [];
    showResults.value = false;
}

watch(query, (v) => {
    if (!v) showResults.value = false;
});
</script>
