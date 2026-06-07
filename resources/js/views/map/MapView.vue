<template>
    <div class="flex flex-col h-full -m-6">
        <!-- Top bar -->
        <div class="bg-gray-900 border-b border-gray-700 px-6 py-3 flex items-center justify-between flex-shrink-0 z-10">
            <div class="flex items-center gap-4 flex-wrap">
                <h1 class="font-bold text-white text-lg tracking-wide">Global Vessel Tracking</h1>
                <div class="flex items-center gap-2 flex-wrap">
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

                    <!-- DB vessels with live positions -->
                    <div class="flex-1 overflow-y-auto min-h-0">
                        <p class="px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-widest text-gray-500">Your Vessels</p>
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
                <div ref="mapEl" class="w-full h-full"></div>

                <!-- Loading overlay -->
                <div v-if="loading" class="absolute inset-0 bg-gray-900/60 flex items-center justify-center z-20 pointer-events-none backdrop-blur-sm">
                    <div class="bg-gray-900 border border-gray-700 text-white text-sm px-5 py-3 rounded-xl flex items-center gap-3 shadow-2xl">
                        <i class="pi pi-spin pi-spinner text-teal-400"></i>
                        <span class="text-gray-200">Fetching live positions…</span>
                    </div>
                </div>

                <!-- No Google Maps key overlay -->
                <div
                    v-if="!hasKey"
                    class="absolute inset-0 flex items-center justify-center z-30 pointer-events-none"
                >
                    <div class="bg-gray-900/95 border border-amber-700/50 rounded-2xl px-8 py-6 max-w-sm text-center shadow-2xl backdrop-blur-sm pointer-events-auto">
                        <div class="w-12 h-12 bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                        </div>
                        <p class="text-white font-semibold mb-2">Add a Google Maps API key</p>
                        <p class="text-gray-400 text-sm leading-relaxed">
                            Set <code class="font-mono text-gray-300 bg-gray-800 px-1 rounded">GOOGLE_MAPS_API_KEY</code> on the server to enable the vessel map.
                        </p>
                    </div>
                </div>

                <!-- Empty state -->
                <div
                    v-if="hasKey && !loading && showEmptyState"
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
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { setOptions, importLibrary } from '@googlemaps/js-api-loader';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import { useRouter } from 'vue-router';
import { useVesselsStore } from '@/stores/vessels';

dayjs.extend(relativeTime);

const vesselsStore = useVesselsStore();
const router = useRouter();

const loading = ref(false);
const mapEl = ref(null);
const sidebarOpen = ref(true);
const sidebarSearch = ref('');
const noPositionExpanded = ref(false);
const selectedVesselImo = ref(null);
const fetchAttempted = ref(false);

// Google Maps API key (runtime-injected, no rebuild needed)
const apiKey = window.__GMAPS_KEY__ || import.meta.env.VITE_GOOGLE_MAPS_API_KEY || '';
const hasKey = computed(() => !!apiKey);

// Non-reactive Google Maps objects
let map = null;
let infoWindow = null;
let MarkerCtor = null;
const markers = new Map(); // key: imo_number|uuid -> google.maps.Marker

// ── Dark map style ───────────────────────────────────────────────────────
const darkMapStyle = [
    { elementType: 'geometry', stylers: [{ color: '#1d2530' }] },
    { elementType: 'labels.text.stroke', stylers: [{ color: '#1d2530' }] },
    { elementType: 'labels.text.fill', stylers: [{ color: '#9ca3af' }] },
    { featureType: 'administrative', elementType: 'geometry', stylers: [{ color: '#374151' }] },
    { featureType: 'administrative.country', elementType: 'labels.text.fill', stylers: [{ color: '#cbd5e1' }] },
    { featureType: 'administrative.locality', elementType: 'labels.text.fill', stylers: [{ color: '#cbd5e1' }] },
    { featureType: 'poi', stylers: [{ visibility: 'off' }] },
    { featureType: 'road', stylers: [{ visibility: 'off' }] },
    { featureType: 'transit', stylers: [{ visibility: 'off' }] },
    { featureType: 'landscape', elementType: 'geometry', stylers: [{ color: '#212a36' }] },
    { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#0b1622' }] },
    { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#475569' }] },
];

// ── Derived lists ─────────────────────────────────────────────────────────

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

// ── Stats ──────────────────────────────────────────────────────────────────

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
    return vessel.container_count != null ? vessel.container_count : '—';
}

function escapeHtml(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ── Marker icon (rotated ship arrow Symbol) ─────────────────────────────────

// Ship-arrow path drawn around origin; Symbol.rotation rotates it to heading.
const SHIP_PATH = 'M 0,-9 L 6,7 L 0,3 L -6,7 Z';

function vesselSymbol(course = 0, isSelected = false) {
    return {
        path: SHIP_PATH,
        rotation: course,
        fillColor: isSelected ? '#2dd4bf' : '#06c4a7',
        fillOpacity: 1,
        strokeColor: '#ffffff',
        strokeWeight: 1.5,
        scale: 1.2,
        anchor: new google.maps.Point(0, 0),
    };
}

// ── InfoWindow content ───────────────────────────────────────────────────────

function infoContent(vessel) {
    const navBadge = shortNavStatus(vessel.navigation_status);
    const detailHref = vessel.uuid
        ? router.resolve({ name: 'vessel-detail', params: { uuid: vessel.uuid } }).href
        : null;

    const viewBtn = detailHref
        ? `<a href="${escapeHtml(detailHref)}" class="popup-view-btn" data-vessel-link="1">View Vessel</a>`
        : '';

    return `
        <div class="vessel-popup-content">
            <div class="popup-header">
                <span class="popup-vessel-name">${escapeHtml(vessel.name || 'Unknown Vessel')}</span>
                <span class="popup-nav-badge">${escapeHtml(navBadge)}</span>
            </div>
            <div class="popup-ids">
                <span>IMO ${escapeHtml(vessel.imo_number || '—')}</span>
                <span class="popup-sep">·</span>
                <span>MMSI ${escapeHtml(vessel.mmsi || '—')}</span>
            </div>
            <div class="popup-grid">
                <div class="popup-stat">
                    <span class="popup-stat-label">Speed</span>
                    <span class="popup-stat-value teal">${vessel.speed != null ? escapeHtml(vessel.speed) + ' kn' : '—'}</span>
                </div>
                <div class="popup-stat">
                    <span class="popup-stat-label">Course</span>
                    <span class="popup-stat-value">${vessel.course != null ? escapeHtml(vessel.course) + '°' : '—'}</span>
                </div>
                <div class="popup-stat">
                    <span class="popup-stat-label">Destination</span>
                    <span class="popup-stat-value">${escapeHtml(vessel.destination || '—')}</span>
                </div>
                <div class="popup-stat">
                    <span class="popup-stat-label">ETA</span>
                    <span class="popup-stat-value">${escapeHtml(formatDate(vessel.eta_UTC))}</span>
                </div>
                <div class="popup-stat">
                    <span class="popup-stat-label">Last Report</span>
                    <span class="popup-stat-value muted">${vessel.last_position_UTC ? escapeHtml(fromNow(vessel.last_position_UTC)) : '—'}</span>
                </div>
                <div class="popup-stat">
                    <span class="popup-stat-label">Containers</span>
                    <span class="popup-stat-value teal">${escapeHtml(containerCount(vessel))}</span>
                </div>
            </div>
            ${viewBtn}
        </div>`;
}

function openInfoFor(vessel, marker) {
    if (!infoWindow || !map) return;
    infoWindow.setContent(infoContent(vessel));
    infoWindow.open({ map, anchor: marker });
}

// Intercept clicks on the "View Vessel" link inside the InfoWindow so we do
// SPA navigation instead of a full page reload.
function onInfoDomReady() {
    const link = document.querySelector('a[data-vessel-link="1"]');
    if (!link) return;
    link.addEventListener('click', (e) => {
        e.preventDefault();
        const href = link.getAttribute('href');
        if (href) router.push(href);
    });
}

// ── Marker management ─────────────────────────────────────────────────────────

function vesselKey(v) {
    return String(v.imo_number ?? v.uuid ?? '');
}

function renderMarkers() {
    if (!map || !MarkerCtor) return;

    const vessels = livePositionVessels.value;
    const seen = new Set();

    for (const vessel of vessels) {
        const key = vesselKey(vessel);
        if (!key) continue;
        seen.add(key);

        const position = { lat: Number(vessel.lat), lng: Number(vessel.lon) };
        const isSelected = selectedVesselImo.value === vessel.imo_number;
        const icon = vesselSymbol(vessel.course ?? vessel.heading ?? 0, isSelected);

        let marker = markers.get(key);
        if (marker) {
            marker.setPosition(position);
            marker.setIcon(icon);
            marker.setZIndex(isSelected ? 1000 : 500);
            marker.__vessel = vessel;
        } else {
            marker = new MarkerCtor({
                position,
                map,
                icon,
                title: vessel.name || '',
                zIndex: isSelected ? 1000 : 500,
            });
            marker.__vessel = vessel;
            marker.addListener('click', () => {
                selectedVesselImo.value = marker.__vessel.imo_number;
                marker.setIcon(vesselSymbol(marker.__vessel.course ?? marker.__vessel.heading ?? 0, true));
                openInfoFor(marker.__vessel, marker);
            });
            markers.set(key, marker);
        }
    }

    // Remove stale markers
    for (const [key, marker] of markers) {
        if (!seen.has(key)) {
            marker.setMap(null);
            markers.delete(key);
        }
    }
}

function clearMarkers() {
    for (const marker of markers.values()) {
        google.maps.event.clearInstanceListeners(marker);
        marker.setMap(null);
    }
    markers.clear();
}

// ── Map actions ──────────────────────────────────────────────────────────────

function flyToVessel(vessel) {
    selectedVesselImo.value = vessel.imo_number;
    if (!map || vessel.lat == null || vessel.lon == null) return;
    const position = { lat: Number(vessel.lat), lng: Number(vessel.lon) };
    map.panTo(position);
    map.setZoom(7);
    const marker = markers.get(vesselKey(vessel));
    if (marker) {
        marker.setIcon(vesselSymbol(vessel.course ?? vessel.heading ?? 0, true));
        openInfoFor(vessel, marker);
    }
}

// ── Load / refresh ────────────────────────────────────────────────────────────

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
    renderMarkers();
}

async function initMap() {
    if (!hasKey.value || !mapEl.value) return;

    setOptions({ key: apiKey, v: 'weekly' });
    const [{ Map }, { Marker }] = await Promise.all([
        importLibrary('maps'),
        importLibrary('marker'),
    ]);
    MarkerCtor = Marker;

    map = new Map(mapEl.value, {
        center: { lat: 25.77, lng: -80.18 },
        zoom: 5,
        disableDefaultUI: false,
        streetViewControl: false,
        mapTypeControl: false,
        styles: darkMapStyle,
    });

    infoWindow = new google.maps.InfoWindow();
    infoWindow.addListener('domready', onInfoDomReady);
    infoWindow.addListener('closeclick', () => {
        selectedVesselImo.value = null;
        renderMarkers();
    });
}

// Re-render markers whenever the live vessel list changes.
watch(() => vesselsStore.liveVessels, renderMarkers, { deep: true });

onMounted(async () => {
    if (hasKey.value) {
        try {
            await initMap();
        } catch (err) {
            console.error('Google Maps init failed:', err);
        }
    }
    await refresh();
});

onBeforeUnmount(() => {
    clearMarkers();
    if (infoWindow) {
        google.maps.event.clearInstanceListeners(infoWindow);
        infoWindow.close();
    }
    map = null;
    infoWindow = null;
});
</script>

<style>
/* Dark InfoWindow overrides */
.gm-style .gm-style-iw-c {
    background: #111827 !important;
    border: 1px solid #374151 !important;
    border-radius: 12px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6) !important;
    padding: 0 !important;
}
.gm-style .gm-style-iw-d {
    overflow: hidden !important;
    color: #e5e7eb;
}
.gm-style .gm-style-iw-tc::after {
    background: #111827 !important;
}
/* Close button tint */
.gm-style .gm-style-iw-c button[aria-label="Close"] span,
.gm-style .gm-ui-hover-effect > span {
    background-color: #6b7280 !important;
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
    background: rgba(31, 41, 55, 0.7);
    color: #9ca3af;
    border: 1px solid rgba(75, 85, 99, 0.5);
}
.popup-ids {
    font-family: ui-monospace, monospace;
    font-size: 10px;
    color: #6b7280;
    margin-bottom: 10px;
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
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
    border: none;
    cursor: pointer;
    transition: background 0.15s;
}
.popup-view-btn:hover { background: #0d9488; color: #fff; }
</style>
