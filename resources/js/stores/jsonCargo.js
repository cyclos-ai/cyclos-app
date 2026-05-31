import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/plugins/api';

export const useJsonCargoStore = defineStore('jsonCargo', () => {
    // ── State ──
    const configured = ref(false);
    const shippingLines = ref([]);
    const scacMapping = ref({});
    const apiStats = ref(null);

    const containerCache = ref({});   // keyed by container number
    const vesselCache = ref({});      // keyed by identifier
    const portCache = ref([]);
    const terminalCache = ref([]);
    const bolCache = ref({});         // keyed by BOL number

    const loading = ref(false);
    const error = ref(null);

    // ── Computed ──
    const isConfigured = computed(() => configured.value);
    const requestsRemaining = computed(() => apiStats.value?.requests_available ?? null);

    // ================================================================
    // Meta / Status
    // ================================================================

    async function fetchStatus() {
        try {
            const response = await api.get('/jsoncargo/status');
            const data = response.data.data;
            configured.value = data.configured;
            shippingLines.value = data.shipping_lines;
            scacMapping.value = data.scac_mapping;
            return data;
        } catch (err) {
            console.error('JSONCargo status check failed:', err);
            throw err;
        }
    }

    async function fetchApiStats() {
        try {
            const response = await api.get('/jsoncargo/stats');
            apiStats.value = response.data.data;
            return apiStats.value;
        } catch (err) {
            console.error('JSONCargo stats fetch failed:', err);
            throw err;
        }
    }

    // ================================================================
    // Container Tracking
    // ================================================================

    /**
     * Track a single container by number.
     * @param {string} trackingNumber - e.g. "ZCSU7244544"
     * @param {string|null} shippingLine - e.g. "ZIM" (auto-detected if null)
     */
    async function trackContainer(trackingNumber, shippingLine = null) {
        loading.value = true;
        error.value = null;
        try {
            const params = shippingLine ? { shipping_line: shippingLine } : {};
            const response = await api.get(`/jsoncargo/containers/${trackingNumber}`, { params });
            const data = response.data.data;
            containerCache.value[trackingNumber.toUpperCase()] = data;
            return data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Container tracking failed';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Track multiple containers in batch.
     * @param {Array<{number: string, shipping_line?: string}>} containers
     */
    async function trackContainerBatch(containers) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.post('/jsoncargo/containers/batch', { containers });
            const data = response.data.data;

            // Merge tracked results into cache
            if (data.tracked) {
                Object.entries(data.tracked).forEach(([num, detail]) => {
                    containerCache.value[num.toUpperCase()] = detail;
                });
            }

            return data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Batch tracking failed';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Refresh container tracking data (clears server cache).
     * @param {string} trackingNumber
     * @param {string|null} shippingLine
     */
    async function refreshContainer(trackingNumber, shippingLine = null) {
        loading.value = true;
        error.value = null;
        try {
            const params = shippingLine ? { shipping_line: shippingLine } : {};
            const response = await api.post(`/jsoncargo/containers/${trackingNumber}/refresh`, null, { params });
            const data = response.data.data;
            containerCache.value[trackingNumber.toUpperCase()] = data;
            return data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Refresh failed';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Get containers associated with a Bill of Lading.
     * @param {string} bolNumber
     * @param {string} shippingLine - required
     */
    async function getContainersByBol(bolNumber, shippingLine) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.get(`/jsoncargo/containers/bol/${bolNumber}`, {
                params: { shipping_line: shippingLine },
            });
            const data = response.data.data;
            bolCache.value[bolNumber.toUpperCase()] = data;
            return data;
        } catch (err) {
            error.value = err.response?.data?.message || 'BOL lookup failed';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    // ================================================================
    // Vessel Tracking
    // ================================================================

    /**
     * Get basic live vessel tracking.
     * @param {Object} params - { uuid, mmsi, or imo }
     */
    async function getVesselBasic(params) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.get('/jsoncargo/vessels/basic', { params });
            const data = response.data.data;
            const key = params.imo || params.mmsi || params.uuid;
            if (key) vesselCache.value[key] = { ...vesselCache.value[key], basic: data };
            return data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Vessel tracking failed';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Get pro vessel tracking with departure/arrival ports.
     * @param {Object} params - { uuid, mmsi, or imo }
     */
    async function getVesselPro(params) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.get('/jsoncargo/vessels/pro', { params });
            const data = response.data.data;
            const key = params.imo || params.mmsi || params.uuid;
            if (key) vesselCache.value[key] = { ...vesselCache.value[key], pro: data };
            return data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Vessel pro tracking failed';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Bulk track up to 100 vessels.
     * @param {string} ids - comma-separated identifiers
     * @param {string} type - 'uuid', 'mmsi', or 'imo'
     */
    async function getVesselBulk(ids, type = 'imo') {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.get('/jsoncargo/vessels/bulk', {
                params: { ids, type },
            });
            return response.data.data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Bulk vessel tracking failed';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Search vessels by name, type, specs.
     * @param {Object} params - search filters
     */
    async function findVessels(params) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.get('/jsoncargo/vessels/find', { params });
            return response.data.data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Vessel search failed';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Get detailed vessel specs.
     * @param {Object} params - { uuid, mmsi, or imo }
     */
    async function getVesselSpecs(params) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.get('/jsoncargo/vessels/specs', { params });
            const data = response.data.data;
            const key = params.imo || params.mmsi || params.uuid;
            if (key) vesselCache.value[key] = { ...vesselCache.value[key], specs: data };
            return data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Vessel specs fetch failed';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    // ================================================================
    // Port & Terminal
    // ================================================================

    /**
     * Search ports by name, coordinates, country.
     * @param {Object} params - { name, country_iso, lat, lon, radius, port_type, fuzzy, page, limit }
     */
    async function findPorts(params) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.get('/jsoncargo/ports/find', { params });
            const data = response.data.data;
            portCache.value = data;
            return data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Port search failed';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Find terminals by UN/LOCODE.
     * @param {string} unlocode - e.g. "USEVG"
     */
    async function findTerminals(unlocode) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.get('/jsoncargo/terminals/find', {
                params: { unlocode },
            });
            const data = response.data.data;
            terminalCache.value = data;
            return data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Terminal search failed';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    // ================================================================
    // Helpers
    // ================================================================

    /**
     * Get cached container data without API call.
     */
    function getCachedContainer(trackingNumber) {
        return containerCache.value[trackingNumber?.toUpperCase()] ?? null;
    }

    /**
     * Get cached vessel data without API call.
     */
    function getCachedVessel(identifier) {
        return vesselCache.value[identifier] ?? null;
    }

    /**
     * Resolve SCAC to JSONCargo shipping line name.
     */
    function resolveShippingLine(scac) {
        return scacMapping.value[scac?.toUpperCase()] ?? null;
    }

    function $reset() {
        configured.value = false;
        shippingLines.value = [];
        scacMapping.value = {};
        apiStats.value = null;
        containerCache.value = {};
        vesselCache.value = {};
        portCache.value = [];
        terminalCache.value = [];
        bolCache.value = {};
        loading.value = false;
        error.value = null;
    }

    return {
        // State
        configured,
        shippingLines,
        scacMapping,
        apiStats,
        containerCache,
        vesselCache,
        portCache,
        terminalCache,
        bolCache,
        loading,
        error,
        // Computed
        isConfigured,
        requestsRemaining,
        // Meta
        fetchStatus,
        fetchApiStats,
        // Container
        trackContainer,
        trackContainerBatch,
        refreshContainer,
        getContainersByBol,
        // Vessel
        getVesselBasic,
        getVesselPro,
        getVesselBulk,
        findVessels,
        getVesselSpecs,
        // Port & Terminal
        findPorts,
        findTerminals,
        // Helpers
        getCachedContainer,
        getCachedVessel,
        resolveShippingLine,
        $reset,
    };
});
