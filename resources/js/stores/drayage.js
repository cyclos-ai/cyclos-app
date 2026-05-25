import { defineStore } from 'pinia';
import api from '@/plugins/api';

export const useDrayageStore = defineStore('drayage', {
    state: () => ({
        overviewStats: {
            arriving_today: 0,
            must_out_gate: 0,
            must_return: 0,
            at_terminal_holds: 0,
        },
        containersAtTerminal: [],
        inboundContainers: [],
        inboundPagination: { current_page: 1, per_page: 50, total: 0, last_page: 1 },
        volumeByCarrier: { labels: [], datasets: [] },
        volumeByDestination: { labels: [], datasets: [] },
        currentDrayage: null,
        drayageEvents: [],
        uploadBatches: [],
        loading: false,
        error: null,
    }),

    getters: {
        totalAllocations: (state) => state.inboundPagination.total,
    },

    actions: {
        async fetchOverviewStats() {
            try {
                const { data } = await api.get('/drayage/overview-stats');
                this.overviewStats = data.data || data;
                return this.overviewStats;
            } catch (error) {
                console.error('Failed to fetch overview stats:', error);
                return this.overviewStats;
            }
        },

        async fetchContainersAtTerminal() {
            try {
                const { data } = await api.get('/drayage/containers-at-terminal');
                this.containersAtTerminal = data.data || [];
                return this.containersAtTerminal;
            } catch (error) {
                console.error('Failed to fetch containers at terminal:', error);
                return [];
            }
        },

        async fetchVolumeByCarrier() {
            try {
                const { data } = await api.get('/drayage/volume-by-carrier');
                this.volumeByCarrier = data.data || data;
                return this.volumeByCarrier;
            } catch (error) {
                console.error('Failed to fetch volume by carrier:', error);
                return this.volumeByCarrier;
            }
        },

        async fetchVolumeByDestination() {
            try {
                const { data } = await api.get('/drayage/volume-by-destination');
                this.volumeByDestination = data.data || data;
                return this.volumeByDestination;
            } catch (error) {
                console.error('Failed to fetch volume by destination:', error);
                return this.volumeByDestination;
            }
        },

        async fetchInboundContainers(params = {}) {
            this.loading = true;
            try {
                const { data } = await api.get('/drayage/inbound', { params: { page_size: 50, ...params } });
                this.inboundContainers = data.data || [];
                if (data.meta) {
                    this.inboundPagination = {
                        current_page: data.meta.current_page,
                        per_page: data.meta.per_page,
                        total: data.meta.total,
                        last_page: data.meta.last_page,
                    };
                }
                return this.inboundContainers;
            } catch (error) {
                console.error('Failed to fetch inbound containers:', error);
                return [];
            } finally {
                this.loading = false;
            }
        },

        async updateDrayageField(uuid, field, value) {
            try {
                const { data } = await api.patch(`/drayage/${uuid}`, { [field]: value });
                const index = this.inboundContainers.findIndex(c => c.uuid === uuid);
                if (index !== -1) {
                    this.inboundContainers[index] = { ...this.inboundContainers[index], ...data.data };
                }
                return data.data;
            } catch (error) {
                console.error('Failed to update drayage field:', error);
                throw error;
            }
        },

        async fetchDrayageDetail(uuid) {
            this.loading = true;
            try {
                const { data } = await api.get(`/drayage/${uuid}`);
                this.currentDrayage = data.data || data;
                return this.currentDrayage;
            } catch (error) {
                console.error('Failed to fetch drayage detail:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async advanceDrayageStep(uuid, step, notes = '') {
            try {
                const { data } = await api.patch(`/drayage/${uuid}/step`, {
                    step,
                    notes,
                    timestamp: new Date().toISOString(),
                });
                this.currentDrayage = data.data || data;
                return this.currentDrayage;
            } catch (error) {
                console.error('Failed to advance drayage step:', error);
                throw error;
            }
        },

        async fetchDrayageEvents(uuid) {
            try {
                const { data } = await api.get(`/drayage/${uuid}/events`);
                this.drayageEvents = data.data || [];
                return this.drayageEvents;
            } catch (error) {
                console.error('Failed to fetch drayage events:', error);
                return [];
            }
        },

        async uploadCSV(formData) {
            try {
                const { data } = await api.post('/uploads/csv', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                    onUploadProgress: (progressEvent) => {
                        this.uploadProgress = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                    },
                });
                return data.data || data;
            } catch (error) {
                console.error('Failed to upload CSV:', error);
                throw error;
            }
        },

        async fetchUploadBatches() {
            try {
                const { data } = await api.get('/uploads/batches');
                this.uploadBatches = data.data || [];
                return this.uploadBatches;
            } catch (error) {
                console.error('Failed to fetch upload batches:', error);
                return [];
            }
        },
    },
});
