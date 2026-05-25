import { ref, computed } from 'vue';
import api from '@/plugins/api';

/**
 * Core composable for API calls with loading/error state management.
 */
export function useApi() {
    const loading = ref(false);
    const error = ref(null);
    const data = ref(null);

    async function execute(method, url, payload = null, config = {}) {
        loading.value = true;
        error.value = null;

        try {
            const response = method === 'get' || method === 'delete'
                ? await api[method](url, { params: payload, ...config })
                : await api[method](url, payload, config);

            data.value = response.data;
            return response.data;
        } catch (err) {
            error.value = err.response?.data || { message: err.message };
            throw err;
        } finally {
            loading.value = false;
        }
    }

    return { loading, error, data, execute };
}

/**
 * Paginated list composable.
 */
export function usePagination(endpoint, defaultParams = {}) {
    const items = ref([]);
    const loading = ref(false);
    const error = ref(null);
    const pagination = ref({
        current_page: 1,
        per_page: 25,
        total: 0,
        last_page: 1,
    });

    const params = ref({ ...defaultParams });

    async function fetch(page = 1, perPage = 25, extraParams = {}) {
        loading.value = true;
        error.value = null;

        try {
            const response = await api.get(endpoint, {
                params: {
                    ...params.value,
                    ...extraParams,
                    page,
                    per_page: perPage,
                },
            });

            const responseData = response.data;
            items.value = responseData.data || responseData;
            if (responseData.meta) {
                pagination.value = {
                    current_page: responseData.meta.current_page,
                    per_page: responseData.meta.per_page,
                    total: responseData.meta.total,
                    last_page: responseData.meta.last_page,
                };
            }
            return responseData;
        } catch (err) {
            error.value = err.response?.data || { message: err.message };
            throw err;
        } finally {
            loading.value = false;
        }
    }

    function setParam(key, value) {
        params.value[key] = value;
    }

    function resetParams() {
        params.value = { ...defaultParams };
    }

    const hasNextPage = computed(() => pagination.value.current_page < pagination.value.last_page);
    const hasPrevPage = computed(() => pagination.value.current_page > 1);

    return {
        items,
        loading,
        error,
        pagination,
        params,
        fetch,
        setParam,
        resetParams,
        hasNextPage,
        hasPrevPage,
    };
}

/**
 * Filter composable for building query filters.
 */
export function useFilter(endpoint, defaultFilters = {}) {
    const filters = ref({ ...defaultFilters });
    const { items, loading, error, pagination, fetch: baseFetch } = usePagination(endpoint);

    async function fetch(page = 1, perPage = 25) {
        const activeFilters = Object.fromEntries(
            Object.entries(filters.value).filter(([, v]) => v !== null && v !== '' && v !== undefined),
        );
        return baseFetch(page, perPage, activeFilters);
    }

    function setFilter(key, value) {
        filters.value[key] = value;
    }

    function clearFilter(key) {
        filters.value[key] = defaultFilters[key] ?? null;
    }

    function clearAllFilters() {
        filters.value = { ...defaultFilters };
    }

    const hasActiveFilters = computed(() =>
        Object.entries(filters.value).some(([k, v]) => v !== null && v !== '' && v !== undefined && v !== defaultFilters[k]),
    );

    return {
        filters,
        items,
        loading,
        error,
        pagination,
        fetch,
        setFilter,
        clearFilter,
        clearAllFilters,
        hasActiveFilters,
    };
}
