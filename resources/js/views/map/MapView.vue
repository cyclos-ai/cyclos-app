<template>
    <div class="flex flex-col h-full -m-6">
        <!-- Top bar -->
        <div class="bg-gray-900 border-b border-gray-700 px-6 py-3 flex items-center justify-between flex-shrink-0 z-10">
            <div class="flex items-center gap-4">
                <h1 class="font-bold text-white text-lg tracking-wide">Global Vessel Tracking</h1>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 bg-teal-900/60 text-teal-300 text-xs font-medium px-2.5 py-1 rounded-full border border-teal-700/50">
                        <span class="w-1.5 h-1.5 rounded-full bg-teal-400 inline-block"></span>
                        Tracked: {{ trackedCount }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-blue-900/60 text-blue-300 text-xs font-medium px-2.5 py-1 rounded-full border border-blue-700/50">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400 inline-block"></span>
                        At Sea: {{ atSeaCount }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-amber-900/60 text-amber-300 text-xs font-medium px-2.5 py-1 rounded-full border border-amber-700/50">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 inline-block"></span>
                        In Port: {{ inPortCount }}
                    </span>
                    <span v-if="noPositionCount > 0" class="inline-flex items-center gap-1.5 bg-gray-800/60 text-gray-400 text-xs font-medium px-2.5 py-1 rounded-full border border-gray-600/50">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-500 inline-block"></span>
                        No Signal: {{ noPositionCount }}
                    </span>
                </div>
            </div>
            <button
                class="inline-flex items-center gap-2 bg-teal-600 hover:bg-teal-500 active:bg-teal-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="loading"
                @click="refresh"
            >
                <i class="pi text-sm" :class="loading ? 'pi-spin pi-spinner' : 'pi-refresh'"></i>
                {{ loading ? 'Refreshing…' : 'Refresh Positions' }}
            </button>
        </div>

        <!-- Body: sidebar + map -->
        <div class="flex flex-1 min-h-0 overflow-hidden">
            <!-- Sidebar -->
            <div
                class="flex-shrink-0 bg-gray-900 border-r border-gray-700 flex flex-col overflow-hidden transition-all duration-200"
                :class="sidebarOpen ? 'w-[300px]' : 'w-0'"
            >
                <div v-show="sidebarOpen" class="flex flex-col h-full overflow-hidden">
                    <!-- Sidebar header -->
                    <div class="px-3 pt-3 pb-2 border-b border-gray-700 flex-shrink-0">
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2">Live Vessels</p>
                        <span class="p-input-icon-left w-full flex items-center relative">
                            <i class="pi pi-search absolute left-2.5 text-gray-500 text-xs z-10"></i>
                            <input
                                v-model="sidebarSearch"
                                placeholder="Name, IMO, destination…"
                                class="w-full bg-gray-800 border border-gray-600 text-gray-200 text-xs placeholder-gray-500 rounded-md pl-7 pr-3 py-1.5 focus:outline-none focus:border-teal-500"
                            />
                        </span>
                    </div>

                    <!-- Live vessels list -->
                    <div class="flex-1 overflow-y-auto min-h-0">
                        <!-- Live position cards -->
                        <div
                            v-for="vessel in filteredLiveVessels"
                            :key="vessel.uuid || vessel.imo_number"
                            class="px-3 py-2.5 border-b border-gray-800 cursor-pointer transition-colors hover:bg-gray-800/70"
                            :class="selectedVesselImo === (vessel.imo_number) ? 'bg-teal-900/40 border-l-2 border-l-teal-500' : ''"
                            @click="flyToVessel(vessel)"
                        >
                            <div class="flex items-start justify-between gap-2 mb-1">
                                <span class="font-semibold text-white text-sm leading-tight truncate">{{ vessel.name }}</span>
                                <span
                                    class="flex-shrink-0 text-[10px] font-mono px-1.5 py-0.5 rounded"
                                    :class="navStatusClass(vessel.navigation_status)"
                                >
                                    {{ shortNavStatus(vessel.navigation_status) }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-gray-400 mb-1">
                                <span v-if="vessel.speed != null" class="text-teal-400 font-semibold">{{ vessel.speed }} kn</span>
                                <span v-if="vessel.destination" class="truncate">→ {{ vessel.destination }}</span>
                            </div>
                            <div class="text-[10px] text-gray-500">
                                <span v-if="vessel.eta_UTC">ETA {{ formatDate(vessel.eta_UTC) }}</span>
                                <span v-if="vessel.last_position_UTC" class="ml-2">· {{ fromNow(vessel.last_position_UTC) }}</span>
                            </div>
                        </div>

                        <!-- No-position section -->
                        <div v-if="noPositionVessels.length > 0">
                            <button
                                class="w-full px-3 py-2 flex items-center justify-between text-xs text-gray-500 hover:text-gray-300 hover:bg-gray-800/50 transition-colors border-b border-gray-800"
                                @click="noPositionExpanded = !noPositionExpanded"
                            >
                                <span class="font-medium">Position unavailable ({{ noPositionVessels.length }})</span>
                                <i class="pi text-[10px]" :class="noPositionExpanded ? 'pi-chevron-up' : 'pi-chevron-down'"></i>
                            </button>
                            <template v-if="noPositionExpanded">
                                <div
                                    v-for="vessel in noPositionVessels"
                                    :key="vessel.uuid || vessel.imo_number"
                                    class="px-3 py-2 border-b border-gray-800/60"
                                >
                                    <p class="text-xs text-gray-400 truncate">{{ vessel.name || '—' }}</p>
                                    <p class="text-[10px] text-gray-600 font-mono">IMO {{ vessel.imo_number || 'N/A' }}</p>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar toggle tab -->
            <button
                class="absolute left-0 top-1/2 -translate-y-1/2 z-[500] bg-gray-800 hover:bg-gray-700 border border-gray-600 text-gray-400 hover:text-white rounded-r-md px-1 py-3 transition-colors"
                :style="{ left: sidebarOpen ? '300px' : '0px' }"
                style="position: absolute; transition: left 0.2s"
                @click="sidebarOpen = !sidebarOpen"
            >
                <i class="pi text-[10px]" :class="sidebarOpen ? 'pi-chevron-left' : 'pi-chevron-right'"></i>
            </button>

            <!-- Map -->
            <div class="flex-1 relative overflow-hidden">
                <l-map
                    ref="mapRef"
                    :zoom="3"
                    :center="[20, 0]"
                    class="w-full h-full"
                    :options="{ zoomControl: true, attributionControl: false }"
                    @ready="onMapReady"
                >
                    <!-- CARTO Dark Matter tiles -->
                    <l-tile-layer
                        url="https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png"
                        attribution="&copy; <a href='https://www.openstreetmap.org/copyright'>OpenStreetMap</a> contributors &copy; <a href='https://carto.com/attributions'>CARTO</a>"
                        :subdomains="['a','b','c','d']"
                    />

                    <!-- Live vessel markers using L.divIcon + rotated SVG ship -->
                    <l-marker
                        v-for="vessel in livePositionVessels"
                        :key="vessel.imo_number || vessel.uuid"
                        :lat-lng="[vessel.lat, vessel.lon]"
                        :icon="vesselDivIcon(vessel.course ?? vessel.heading ?? 0, selectedVesselImo === vessel.imo_number)"
                        :z-index-offset="selectedVesselImo === vessel.imo_number ? 1000 : 0"
                        @click="selectedVesselImo = vessel.imo_number"
                    >
                        <l-popup :options="{ maxWidth: 280, className: 'vessel-popup-dark' }">
                            <div class="vessel-popup-content">
                                <!-- Vessel name header -->
                                <div class="popup-header">
                                    <span class="popup-vessel-name">{{ vessel.name }}</span>
                                    <span
                                        class="popup-nav-badge"
                                        :class="navStatusClass(vessel.navigation_status)"
                                    >{{ shortNavStatus(vessel.navigation_status) }}</span>
                                </div>

                                <!-- IMO / MMSI -->
                                <div class="popup-ids">
                                    <span>IMO {{ vessel.imo_number || '—' }}</span>
                                    <span class="popup-sep">·</span>
                                    <span>MMSI {{ vessel.mmsi || '—' }}</span>
                                </div>

                                <!-- Stats grid -->
                                <div class="popup-grid">
                                    <div class="popup-stat">
                                        <span class="popup-stat-label">Speed</span>
                                        <span class="popup-stat-value teal">{{ vessel.speed != null ? vessel.speed + ' kn' : '—' }}</span>
                                    </div>
                                    <div class="popup-stat">
                                        <span class="popup-stat-label">Course</span>
                                        <span class="popup-stat-value">{{ vessel.course != null ? vessel.course + '°' : '—' }}</span>
                                    </div>
                                    <div class="popup-stat">
                                        <span class="popup-stat-label">Destination</span>
                                        <span class="popup-stat-value">{{ vessel.destination || '—' }}</span>
                                    </div>
                                    <div class="popup-stat">
                                        <span class="popup-stat-label">ETA</span>
                                        <span class="popup-stat-value">{{ formatDate(vessel.eta_UTC) }}</span>
                                    </div>
                                    <div class="popup-stat">
                                        <span class="popup-stat-label">Last Report</span>
                                        <span class="popup-stat-value muted">{{ vessel.last_position_UTC ? fromNow(vessel.last_position_UTC) : '—' }}</span>
                                    </div>
                                    <div class="popup-stat">
                                        <span class="popup-stat-label">Containers</span>
                                        <span class="popup-stat-value teal">{{ containerCount(vessel) }}</span>
                                    </div>
                                </div>

                                <!-- View vessel link -->
                                <router-link
                                    v-if="vessel.uuid"
                                    :to="{ name: 'vessel-detail', params: { uuid: vessel.uuid } }"
                                    class="popup-view-btn"
                                >
                                    View Vessel
                                </router-link>
                            </div>
                        </l-popup>
                    </l-marker>
                </l-map>

                <!-- Loading overlay -->
                <div v-if="loading" class="absolute inset-0 bg-gray-900/60 flex items-center justify-center z-20 pointer-events-none backdrop-blur-sm">
                    <div class="bg-gray-900 border border-gray-700 text-white text-sm px-5 py-3 rounded-xl flex items-center gap-3 shadow-2xl">
                        <i class="pi pi-spin pi-spinner text-teal-400"></i>
                        <span class="text-gray-200">Fetching live positions…</span>
                    </div>
                </div>

                <!-- Empty / unconfigured state -->
                <div
                    v-if="!loading && showEmptyState"
                    class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none"
                >
                    <div class="bg-gray-900/90 border border-gray-700 rounded-2xl px-8 py-6 max-w-sm text-center shadow-2xl backdrop-blur-sm pointer-events-auto">
                        <div class="w-12 h-12 bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="pi pi-map text-2xl text-gray-500"></i>
                        </div>
                        <p class="text-white font-semibold mb-2">No live vessel positions available</p>
                        <p class="text-gray-400 text-sm leading-relaxed">
                            Live tracking requires JSONCargo to be configured and vessels to have IMO numbers.
                        </p>
                        <button
                            class="mt-4 inline-flex items-center gap-2 bg-teal-600 hover:bg-teal-500 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
                            @click="refresh"
                        >
                            <i class="pi pi-refresh text-xs"></i>
                            Retry
                        </button>
                    </div>
                </div>

                <!-- Attribution -->
                <div class="absolute bottom-2 right-2 z-10 text-[9px] text-gray-600 pointer-events-none">
                    © OpenStreetMap · © CARTO
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { LMap, LTileLayer, LMarker, LPopup } from '@vue-leaflet/vue-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import ProgressSpinner from 'primevue/progressspinner';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import { useVesselsStore } from '@/stores/vessels';

dayjs.extend(relativeTime);

const vesselsStore = useVesselsStore();
const loading = ref(false);
const mapRef = ref(null);
const mapReady = ref(false);
const sidebarOpen = ref(true);
const sidebarSearch = ref('');
const noPositionExpanded = ref(false);
const selectedVesselImo = ref(null);
const fetchAttempted = ref(false);

// ── Derived lists ───────────────────────────────────────────────────────────

const livePositionVessels = computed(() =>
    (vesselsStore.liveVessels ?? []).filter(
        v => v.hasLivePosition && v.lat != null && v.lon != null,
    ),
);

const noPositionVessels = computed(() =>
    (vesselsStore.liveVessels ?? []).filter(v => !v.hasLivePosition),
);

const filteredLiveVessels = computed(() => {
    const q = sidebarSearch.value.toLowerCase();
    if (!q) return livePositionVessels.value;
    return livePositionVessels.value.filter(v =>
        v.name?.toLowerCase().includes(q) ||
        String(v.imo_number ?? '').includes(q) ||
        v.destination?.toLowerCase().includes(q),
    );
});

// ── Stats ───────────────────────────────────────────────────────────────────

const trackedCount = computed(() => livePositionVessels.value.length);

const AT_SEA_STATUSES = new Set([
    'underway using engine',
    'under way using engine',
    'underway sailing',
    'under way sailing',
]);

const atSeaCount = computed(() =>
    livePositionVessels.value.filter(v => {
        const s = (v.navigation_status ?? '').toLowerCase();
        return AT_SEA_STATUSES.has(s) || (v.speed != null && Number(v.speed) > 1);
    }).length,
);

const inPortCount = computed(() =>
    livePositionVessels.value.filter(v => {
        const s = (v.navigation_status ?? '').toLowerCase();
        return s.includes('moored') || s.includes('anchor') || s.includes('at anchor') || (v.speed != null && Number(v.speed) <= 1);
    }).length,
);

const noPositionCount = computed(() => noPositionVessels.value.length);

const showEmptyState = computed(() =>
    fetchAttempted.value && livePositionVessels.value.length === 0,
);

// ── Vessel icon factory ─────────────────────────────────────────────────────

function vesselDivIcon(course = 0, isSelected = false) {
    const color = isSelected ? '#2dd4bf' : '#06c4a7';
    const glow = isSelected
        ? 'filter: drop-shadow(0 0 5px #2dd4bf) drop-shadow(0 0 10px #0d948880);'
        : 'filter: drop-shadow(0 0 3px #06c4a780);';

    // Upward-pointing arrowhead (north = 0°, rotated by course)
    const svg = `
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="-11 -11 22 22"
             style="transform: rotate(${course}deg); ${glow}">
            <polygon points="0,-9 6,7 0,3 -6,7"
                     fill="${color}" stroke="white" stroke-width="1.5" stroke-linejoin="round"/>
        </svg>`;

    return L.divIcon({
        html: svg,
        iconSize: [22, 22],
        iconAnchor: [11, 11],
        popupAnchor: [0, -12],
        className: '',
    });
}

// ── Nav status helpers ──────────────────────────────────────────────────────

function shortNavStatus(status) {
    if (!status) return '—';
    const s = status.toLowerCase();
    if (s.includes('underway') || s.includes('under way')) return 'At Sea';
    if (s.includes('anchor')) return 'Anchored';
    if (s.includes('moored')) return 'Moored';
    if (s.includes('not under command')) return 'NUC';
    if (s.includes('restricted')) return 'Restricted';
    if (s.includes('aground')) return 'Aground';
    return status.length > 12 ? status.slice(0, 12) + '…' : status;
}

function navStatusClass(status) {
    const s = (status ?? '').toLowerCase();
    if (s.includes('underway') || s.includes('under way')) {
        return 'bg-teal-900/70 text-teal-300 border border-teal-700/50';
    }
    if (s.includes('anchor') || s.includes('moored')) {
        return 'bg-amber-900/70 text-amber-300 border border-amber-700/50';
    }
    return 'bg-gray-800/70 text-gray-400 border border-gray-600/50';
}

// ── Formatters ──────────────────────────────────────────────────────────────

function formatDate(d) {
    if (!d) return '—';
    return dayjs(d).format('MMM D, HH:mm');
}

function fromNow(d) {
    if (!d) return '—';
    return dayjs(d).fromNow();
}

function containerCount(vessel) {
    // The vessel object from DB may carry container_count if the API returns it
    return vessel.container_count != null ? vessel.container_count : '—';
}

// ── Map actions ─────────────────────────────────────────────────────────────

function onMapReady() {
    mapReady.value = true;
}

function flyToVessel(vessel) {
    selectedVesselImo.value = vessel.imo_number;
    const map = mapRef.value?.leafletObject;
    if (map && vessel.lat != null && vessel.lon != null) {
        map.flyTo([vessel.lat, vessel.lon], 6, { duration: 1.2 });
    }
}

// ── Load / refresh ──────────────────────────────────────────────────────────

async function refresh() {
    loading.value = true;
    try {
        await vesselsStore.fetchLivePositions();
    } catch (err) {
        console.error('Live position fetch failed:', err);
    } finally {
        loading.value = false;
        fetchAttempted.value = true;
    }
}

onMounted(refresh);
</script>

<style>
/* Dark popup overrides — match RailMapView pattern */
.vessel-popup-dark .leaflet-popup-content-wrapper {
    background: #111827;
    border: 1px solid #374151;
    border-radius: 12px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6) !important;
    padding: 0;
}
.vessel-popup-dark .leaflet-popup-tip-container {
    display: none;
}
.vessel-popup-dark .leaflet-popup-content {
    margin: 0;
    color: #e5e7eb;
}

/* Popup inner layout */
.vessel-popup-content {
    padding: 12px 14px;
    min-width: 220px;
    font-family: inherit;
}
.popup-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 4px;
}
.popup-vessel-name {
    font-weight: 700;
    font-size: 14px;
    color: #f9fafb;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.popup-nav-badge {
    flex-shrink: 0;
    font-size: 9px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.popup-ids {
    font-family: ui-monospace, monospace;
    font-size: 10px;
    color: #6b7280;
    margin-bottom: 10px;
    display: flex;
    gap: 6px;
    align-items: center;
}
.popup-sep { color: #374151; }
.popup-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px 10px;
    margin-bottom: 10px;
}
.popup-stat {
    display: flex;
    flex-direction: column;
    gap: 1px;
}
.popup-stat-label {
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #6b7280;
}
.popup-stat-value {
    font-size: 12px;
    color: #d1d5db;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.popup-stat-value.teal { color: #2dd4bf; font-weight: 600; }
.popup-stat-value.muted { color: #9ca3af; font-style: italic; }

.popup-view-btn {
    display: block;
    width: 100%;
    text-align: center;
    background: #0f766e;
    color: #ccfbf1;
    font-size: 11px;
    font-weight: 600;
    padding: 6px 0;
    border-radius: 6px;
    text-decoration: none;
    transition: background 0.15s;
}
.popup-view-btn:hover { background: #0d9488; color: #fff; }

/* Leaflet base popup reset */
.leaflet-popup-content-wrapper {
    border-radius: 10px !important;
    box-shadow: 0 4px 24px rgba(0,0,0,0.18) !important;
}
.leaflet-popup-tip {
    box-shadow: none !important;
}
</style>
