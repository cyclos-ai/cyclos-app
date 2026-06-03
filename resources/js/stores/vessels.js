import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/plugins/api';
import { useJsonCargoStore } from '@/stores/jsonCargo';

export const useVesselsStore = defineStore('vessels', () => {
    const vessels = ref([]);
    const currentVessel = ref(null);
    const schedule = ref([]);
    const loading = ref(false);
    const liveVessels = ref([]);

    async function fetchVessels(params = {}) {
        loading.value = true;
        try {
            const response = await api.get('/vessels', { params });
            vessels.value = response.data.data || response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function fetchVessel(uuid) {
        loading.value = true;
        try {
            const response = await api.get(`/vessels/${uuid}`);
            currentVessel.value = response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function updateVessel(uuid, data) {
        const response = await api.put(`/vessels/${uuid}`, data);
        const idx = vessels.value.findIndex(v => v.uuid === uuid);
        if (idx !== -1) vessels.value[idx] = response.data;
        if (currentVessel.value?.uuid === uuid) currentVessel.value = response.data;
        return response.data;
    }

    async function fetchSchedule(uuid) {
        loading.value = true;
        try {
            const response = await api.get(`/vessels/${uuid}/schedule`);
            schedule.value = response.data;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Fetch all DB vessels, then enrich with live positions from JSONCargo bulk endpoint.
     * Batches in groups of 100. Merges lat/lon/speed/course/etc back by IMO.
     * Stores result in liveVessels ref and returns it.
     */
    async function fetchLivePositions() {
        loading.value = true;
        try {
            // 1. Get all DB vessels
            const response = await api.get('/vessels');
            const dbVessels = response.data.data || response.data || [];
            vessels.value = dbVessels;

            // 2. Collect unique IMO numbers
            const imoList = [
                ...new Set(
                    dbVessels
                        .map(v => v.imo_number)
                        .filter(imo => imo && String(imo).trim() !== ''),
                ),
            ];

            if (imoList.length === 0) {
                liveVessels.value = dbVessels.map(v => ({ ...v, hasLivePosition: false }));
                return liveVessels.value;
            }

            // 3. Fetch live positions in batches of 100
            const jsonCargoStore = useJsonCargoStore();
            const batchSize = 100;
            const liveMap = {};

            for (let i = 0; i < imoList.length; i += batchSize) {
                const batch = imoList.slice(i, i + batchSize);
                try {
                    const result = await jsonCargoStore.getVesselBulk(batch.join(','), 'imo');
                    // Handle both { vessels: [...] } and { data: { vessels: [...] } }
                    const vesselList =
                        result?.vessels ??
                        result?.data?.vessels ??
                        (Array.isArray(result) ? result : []);
                    for (const lv of vesselList) {
                        const key = String(lv.imo ?? lv.imo_number ?? '').trim();
                        if (key) liveMap[key] = lv;
                    }
                } catch (batchErr) {
                    // Non-fatal: continue with remaining batches
                    console.warn('JSONCargo bulk batch failed:', batchErr?.message);
                }
            }

            // 4. Merge live data back onto DB vessels
            const merged = dbVessels.map(v => {
                const imoKey = String(v.imo_number ?? '').trim();
                const live = imoKey ? liveMap[imoKey] : null;
                if (live && (live.lat != null || live.latitude != null)) {
                    return {
                        ...v,
                        hasLivePosition: true,
                        lat: live.lat ?? live.latitude,
                        lon: live.lon ?? live.longitude,
                        speed: live.speed,
                        course: live.course,
                        heading: live.heading,
                        navigation_status: live.navigation_status,
                        destination: live.destination,
                        eta_UTC: live.eta_UTC,
                        last_position_UTC: live.last_position_UTC,
                    };
                }
                return { ...v, hasLivePosition: false };
            });

            liveVessels.value = merged;
            return merged;
        } finally {
            loading.value = false;
        }
    }

    return {
        vessels,
        currentVessel,
        schedule,
        loading,
        liveVessels,
        fetchVessels,
        fetchVessel,
        updateVessel,
        fetchSchedule,
        fetchLivePositions,
    };
});
